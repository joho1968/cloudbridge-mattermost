<?php
/** Local login policy and trusted-PHP CB2FA adapter. @package CloudbridgeMattermost */
namespace CloudbridgeMattermost;

defined( 'ABSPATH' ) || die( '-1' );

class CBMM_Local_Auth {
    const OPTION = 'cbmm-oauth2-local-policy';
    private static $registered = null;
    private static $proofs = [];

    private static function error() {
        return new \WP_Error( 'cbmm_local_policy', __( 'Unable to complete local login verification. Please start again or contact the site administrator.', 'cloudbridge-mattermost' ) );
    }

    /** Normalize only explicitly configured policies; discovery never writes one. */
    private static function validate_policy( $policy ) {
        if ( ! is_array( $policy ) || count( $policy ) !== 5 ||
             ( $policy['schema_version'] ?? null ) !== 1 ||
             ! in_array( $policy['mode'] ?? null, ['independent', 'required'], true ) ||
             ! is_string( $policy['revision'] ?? null ) ||
             ! preg_match( '/\A[A-Za-z0-9_.:-]{1,128}\z/', $policy['revision'] ) ) {
            return self::error();
        }
        $required = $policy['mode'] === 'required';
        if ( ( $policy['mfa_plugin'] ?? null ) !== ( $required ? 'cloudbridge-2fa' : '' ) ||
             ( $policy['api_version'] ?? null ) !== ( $required ? 2 : 0 ) ) {
            return self::error();
        }
        return [ 'schema_version' => 1, 'mode' => $policy['mode'],
                 'mfa_plugin' => $policy['mfa_plugin'], 'api_version' => $policy['api_version'],
                 'revision' => $policy['revision'] ];
    }

    public static function policy() {
        wp_cache_delete( 'alloptions', 'options' );
        wp_cache_delete( 'notoptions', 'options' );
        wp_cache_delete( self::OPTION, 'options' );
        return self::validate_policy( get_option( self::OPTION, null ) );
    }

    /** Settings API owns the nonce; capability and strict selection are checked here. */
    public static function sanitize_policy( $input ) {
        $old = get_option( self::OPTION, null );
        if ( ! current_user_can( 'manage_options' ) ) {
            return $old;
        }
        // Core can sanitize a new option twice. Accept our validated array too.
        if ( is_array( $input ) ) {
            $validated = self::validate_policy( $input );
            if ( ! is_wp_error( $validated ) ) {
                return $validated;
            }
        }
        if ( ! is_string( $input ) || ! in_array( $input, ['independent', 'required'], true ) ) {
            add_settings_error( self::OPTION, 'cbmm_local_policy', __( 'Choose a local login verification policy before using OAuth.', 'cloudbridge-mattermost' ) );
            return $old;
        }
        $current = self::validate_policy( $old );
        if ( ! is_wp_error( $current ) && $current['mode'] === $input ) {
            return $current;
        }
        return [ 'schema_version' => 1, 'mode' => $input,
                 'mfa_plugin' => $input === 'required' ? 'cloudbridge-2fa' : '',
                 'api_version' => $input === 'required' ? 2 : 0, 'revision' => wp_generate_uuid4() ];
    }

    public static function policy_field() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $policy = self::policy();
        $mode = is_wp_error( $policy ) ? '' : $policy['mode'];
        echo '<select id="cbmm-oauth2-local-policy" name="cbmm-oauth2-local-policy">';
        foreach ( [ '' => __( 'Choose a policy', 'cloudbridge-mattermost' ),
                    'independent' => __( 'Existing WordPress login policy', 'cloudbridge-mattermost' ),
                    'required' => __( 'Require Cloudbridge 2FA integration', 'cloudbridge-mattermost' ) ] as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $mode, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select><p class="description">' . esc_html__( 'Review your site’s login restrictions, then choose and save a policy. Required verification uses Cloudbridge 2FA 2.1.0 or later with external login API 2. Changing the policy restarts pending Mattermost sign-ins.', 'cloudbridge-mattermost' ) . '</p>';
    }

    public static function reconcile( $pinned ) {
        $pinned = self::validate_policy( $pinned );
        $current = self::policy();
        if ( is_wp_error( $pinned ) || is_wp_error( $current ) || $pinned !== $current ) {
            return self::error();
        }
        return $current;
    }

    private static function peer() {
        if ( ! class_exists( '\cloudbridge2fa\Cloudbridge_2FA', false ) ) {
            return self::error();
        }
        $peer = \cloudbridge2fa\Cloudbridge_2FA::getInstance();
        foreach ( ['cb2fa_external_login_api_version', 'cb2fa_register_external_login_provider', 'cb2fa_external_login', 'cb2fa_complete_external_request', 'cb2fa_get_challenge'] as $method ) {
            if ( ! is_callable( [$peer, $method] ) ) {
                return self::error();
            }
        }
        if ( $peer->cb2fa_external_login_api_version() !== 2 ) {
            return self::error();
        }
        return $peer;
    }

    /** Core-only availability guard; no registration or policy inference here. */
    public static function completion_peer() {
        try { return self::peer(); }
        catch ( \Throwable $error ) { return self::error(); }
    }

    /** Nonsecret, site-keyed current configuration binding for OAuth state. */
    public static function state_fingerprint() {
        return self::configuration_hash( self::configuration() );
    }

    /** Registration must also run on CB2FA verification bootstraps. */
    public static function register_provider() {
        if ( self::$registered !== null ) {
            return;
        }
        try {
            $peer = self::peer();
            if ( ! is_wp_error( $peer ) && $peer->cb2fa_register_external_login_provider( 'cloudbridge-mattermost', [
                'api_version' => 2,
                'primary_action' => CBMM_OAuth::ACTION,
                'completion_action' => CBMM_OAuth::COMPLETION,
                'consume_primary_proof' => [__CLASS__, 'consume_primary_proof'],
                'revalidate' => [__CLASS__, 'revalidate'],
            ] ) === true ) {
                self::$registered = $peer;
            }
        } catch ( \Throwable $error ) {
            // No provider exception details or credentials in public logs.
            self::$registered = null;
        }
    }

    public static function ready() {
        try {
            $peer = self::peer();
            return ! is_wp_error( $peer ) && self::$registered === $peer;
        } catch ( \Throwable $error ) {
            return false;
        }
    }

    /** Fresh security options, including changes made in other request workers. */
    private static function configuration() {
        wp_cache_delete( 'alloptions', 'options' );
        wp_cache_delete( 'notoptions', 'options' );
        $config = [];
        foreach ( [self::OPTION, 'cbmm-oauth2-base-url', 'cbmm-oauth2-client-id', 'cbmm-oauth2-client-secret',
                   'cbmm-oauth2-login-roles', 'cbmm-oauth2-allow-usernames', 'cbmm-oauth2-use-mmidforuuname',
                   'cbmm-oauth2-allow-register', 'cbmm-oauth2-force-register', 'home', 'siteurl'] as $key ) {
            wp_cache_delete( $key, 'options' );
            $config[$key] = get_option( $key, '' );
        }
        return $config;
    }

    private static function configuration_hash( array $config ) {
        // Identity/configuration fingerprints must reject invalid UTF-8, not repair it.
        $encoded = json_encode( $config );
        if ( ! is_string( $encoded ) ) {
            return self::error();
        }
        return hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );
    }

    private static function selection( array $profile, array $config ) {
        $user = get_user_by( 'email', $profile['email'] );
        $mode = 'email';
        $lookup = $profile['email'];
        if ( $user === false && ! empty( $config['cbmm-oauth2-allow-usernames'] ) ) {
            $mode = 'username';
            $lookup = $profile['username'];
            if ( ! empty( $config['cbmm-oauth2-use-mmidforuuname'] ) ) {
                $lookup .= '_' . $profile['id'];
            }
            $lookup = sanitize_user( $lookup );
            $user = get_user_by( 'login', $lookup );
        }
        return [ 'user' => $user, 'mode' => $mode, 'lookup' => $lookup ];
    }

    private static function allowed( $user, array $config ) {
        foreach ( ['cbmm-oauth2-base-url', 'cbmm-oauth2-client-id', 'cbmm-oauth2-client-secret', 'cbmm-oauth2-login-roles'] as $key ) {
            if ( ! is_string( $config[$key] ) || $config[$key] === '' ) {
                return false;
            }
        }
        $roles = json_decode( $config['cbmm-oauth2-login-roles'], true, 2 );
        if ( ! is_array( $roles ) ) {
            return false;
        }
        foreach ( $roles as $role ) {
            if ( ! is_string( $role ) || $role === '' ) {
                return false;
            }
        }
        return $user instanceof \WP_User && $user->ID > 0 && is_array( $roles ) &&
               ! empty( $user->roles ) && (bool) array_intersect( $user->roles, $roles );
    }

    public static function independent_allowed( \WP_User $user ) {
        return self::allowed( $user, self::configuration() );
    }

    private static function destination_valid( $url ) {
        if ( ! is_string( $url ) || strlen( $url ) > 2048 || preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
            return false;
        }
        $parts = wp_parse_url( $url );
        $home = wp_parse_url( home_url( '/' ) );
        if ( ! is_array( $parts ) || ! is_array( $home ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ||
             ! in_array( $parts['scheme'] ?? '', ['http', 'https'], true ) ||
             $parts['scheme'] !== ( $home['scheme'] ?? '' ) ||
             strtolower( $parts['host'] ?? '' ) !== strtolower( $home['host'] ?? '' ) ||
             ( $parts['port'] ?? ( $parts['scheme'] === 'https' ? 443 : 80 ) ) !==
             ( $home['port'] ?? ( ( $home['scheme'] ?? '' ) === 'https' ? 443 : 80 ) ) ) {
            return false;
        }
        return wp_validate_redirect( $url, '' ) === $url;
    }

    public static function consume_primary_proof( string $handle ) {
        $proof = self::$proofs[$handle] ?? null;
        unset( self::$proofs[$handle] );
        return is_array( $proof ) ? $proof : self::error();
    }

    /** Called only after the callback has validated OAuth proof/account/roles. */
    public static function login( \WP_User $user, array $profile, array $pinned, string $destination, int $verified_at ) {
        $handle = null;
        try {
            $config = self::configuration();
            $policy = self::reconcile( $pinned );
            if ( $verified_at <= 0 || $verified_at > time() || time() - $verified_at > 60 ||
                 is_wp_error( $policy ) || $policy['mode'] !== 'required' || ! self::ready() ||
                 ! self::allowed( $user, $config ) || ! self::destination_valid( $destination ) ||
                 ( get_current_user_id() && get_current_user_id() !== (int)$user->ID ) ) {
                return self::error();
            }
            $selection = self::selection( $profile, $config );
            if ( ! $selection['user'] instanceof \WP_User || (int) $selection['user']->ID !== (int) $user->ID ) {
                return self::error();
            }
            $binding = [ 'schema_version' => 1, 'subject' => $profile['id'], 'email' => $profile['email'],
                         'username' => $profile['username'], 'mode' => $selection['mode'], 'lookup' => $selection['lookup'],
                         'wp_login' => $user->user_login, 'wp_email' => $user->user_email,
                         'configuration' => self::configuration_hash( $config ) ];
            if ( ! self::binding_valid( $binding ) ) {
                return self::error();
            }
            $proof = [ 'user_id' => (int) $user->ID, 'blog_id' => get_current_blog_id(),
                       'verified_at' => $verified_at, 'valid_until' => $verified_at + 900,
                       'redirect_to' => $destination, 'rememberme' => true,
                       'account_binding' => $binding, 'requirement' => $policy ];
            $handle = bin2hex( random_bytes( 32 ) );
            self::$proofs[$handle] = $proof;
            // Accepted calls render/redirect and exit. Every returning path rejects.
            self::$registered->cb2fa_external_login( 'cloudbridge-mattermost', $handle );
        } catch ( \Throwable $error ) {
            // Do not leak tokens, proof, identity details or a provider exception.
        } finally {
            if ( $handle !== null ) {
                unset( self::$proofs[$handle] );
            }
        }
        return self::error();
    }

    private static function binding_valid( $binding ) {
        if ( ! is_array( $binding ) || count( $binding ) !== 9 || ( $binding['schema_version'] ?? null ) !== 1 ) {
            return false;
        }
        foreach ( ['subject', 'email', 'username', 'mode', 'lookup', 'wp_login', 'wp_email', 'configuration'] as $key ) {
            if ( ! is_string( $binding[$key] ?? null ) || ( $binding[$key] === '' && $key !== 'wp_email' ) ||
                 strlen( $binding[$key] ) > 512 || ! preg_match( '//u', $binding[$key] ) ) {
                return false;
            }
        }
        return is_email( $binding['email'] ) && in_array( $binding['mode'], ['email', 'username'], true ) &&
               (bool) preg_match( '/\A[a-f0-9]{64}\z/', $binding['configuration'] );
    }

    /** No HTTP, rendering, authentication or session side effects in this callback. */
    public static function revalidate( \WP_User $user, array $context, string $phase ) {
        try {
            $config = self::configuration();
            $policy = self::reconcile( $context['requirement'] ?? null );
            $binding = $context['account_binding'] ?? null;
            $now = time();
            if ( ! in_array( $phase, ['begin', 'complete'], true ) || is_wp_error( $policy ) ||
                 $policy['mode'] !== 'required' || ! self::ready() ||
                 ( $context['provider_id'] ?? null ) !== 'cloudbridge-mattermost' ||
                 ( get_current_user_id() && get_current_user_id() !== (int)$user->ID ) ||
                 ( $context['blog_id'] ?? null ) !== get_current_blog_id() ||
                 ! is_int( $context['user_id'] ?? null ) || $context['user_id'] !== (int) $user->ID ||
                 ! is_int( $context['verified_at'] ?? null ) || $context['verified_at'] <= 0 || $context['verified_at'] > $now ||
                 ! is_int( $context['valid_until'] ?? null ) || $context['valid_until'] <= $now ||
                 $context['valid_until'] - $context['verified_at'] > 900 ||
                 ! is_int( $context['deadline'] ?? null ) || $context['deadline'] <= $now ||
                 $context['deadline'] > $context['valid_until'] || ( $context['rememberme'] ?? null ) !== true ||
                 ! self::destination_valid( $context['redirect_to'] ?? null ) || ! self::binding_valid( $binding ) ) {
                return self::error();
            }
            clean_user_cache( $user->ID );
            $fresh = get_user_by( 'id', $user->ID );
            $hash = self::configuration_hash( $config );
            if ( ! self::allowed( $fresh, $config ) || ! is_string( $hash ) ||
                 ! hash_equals( $binding['configuration'], $hash ) ||
                 $fresh->user_login !== $binding['wp_login'] || $fresh->user_email !== $binding['wp_email'] ) {
                return self::error();
            }
            $selection = self::selection( ['id' => $binding['subject'], 'email' => $binding['email'], 'username' => $binding['username']], $config );
            if ( ! $selection['user'] instanceof \WP_User || (int) $selection['user']->ID !== (int) $fresh->ID ||
                 $selection['mode'] !== $binding['mode'] || $selection['lookup'] !== $binding['lookup'] ) {
                return self::error();
            }
            return true;
        } catch ( \Throwable $error ) {
            return self::error();
        }
    }
}
