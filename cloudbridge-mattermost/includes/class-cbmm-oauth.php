<?php
/** Core-login OAuth routes and legacy callback relay. @package CloudbridgeMattermost */
namespace CloudbridgeMattermost;

defined( 'ABSPATH' ) || die( '-1' );

class CBMM_OAuth {
    const ACTION = 'cbmm_oauth2';
    const COMPLETION = 'cb2fa_complete';
    const SCHEMA = 2;

    public static function hooks() {
        add_action( 'login_init', [__CLASS__, 'guard'], -100 );
        add_action( 'login_form_' . self::ACTION, [__CLASS__, 'dispatch'] );
        // Append after the peer's bootstrap-time terminal handler, regardless
        // of plugin load order. A missing handler must never fall into sign-on.
        add_action( 'init', [__CLASS__, 'completion_fallback_hook'], PHP_INT_MAX );
    }

    public static function completion_fallback_hook() {
        add_action( 'login_form_' . self::COMPLETION, [__CLASS__, 'completion_fallback'], PHP_INT_MAX );
    }

    public static function url() {
        return add_query_arg( ['action' => self::ACTION, 'cbmm_oauth_route' => '2'], site_url( 'wp-login.php', 'login' ) );
    }

    public static function browser_cookie_name() {
        return 'cbmm_oauth_browser_v2_' . substr( hash( 'sha256', self::url() ), 0, 16 );
    }

    /** Core-only terminal rejection; never redirect a completion into login. */
    public static function reject( int $status = 409, string $allow = 'POST' ) {
        status_header( $status );
        nocache_headers();
        header( 'Referrer-Policy: no-referrer' );
        header( 'Content-Type: text/html; charset=UTF-8' );
        if ( $status === 405 ) { header( 'Allow: ' . ( $allow === 'GET' ? 'GET' : 'POST' ) ); }
        echo '<!doctype html><html><body><p>' . esc_html__( 'Unable to complete local login verification. Please start again or contact the site administrator.', 'cloudbridge-mattermost' ) . '</p><a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Log In' ) . '</a></body></html>';
        exit;
    }

    private static function original_action( string $expected ) {
        foreach ( [$_GET, $_POST] as $input ) {
            if ( array_key_exists( 'action', $input ) && ( ! is_string( $input['action'] ) || $input['action'] !== $expected ) ) {
                return false;
            }
        }
        return ( $_REQUEST['action'] ?? null ) === $expected && ( $GLOBALS['action'] ?? null ) === $expected;
    }

    public static function core_route() {
        return ( $GLOBALS['pagenow'] ?? '' ) === 'wp-login.php' && did_action( 'login_init' ) && ! doing_action( 'login_init' )
            && current_filter() === 'login_form_' . self::ACTION
            && realpath( (string)( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) === realpath( ABSPATH . 'wp-login.php' )
            && ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'GET' && self::original_action( self::ACTION );
    }

    /** Availability/request checks survive unsuccessful provider registration. */
    public static function guard() {
        $completion = false;
        $primary = false;
        foreach ( [$_GET, $_POST] as $input ) {
            if ( ( $input['action'] ?? null ) === self::ACTION || array_key_exists( 'cbmm_oauth_route', $input ) ) { $primary = true; }
            if ( ( $input['action'] ?? null ) === self::COMPLETION ) { $completion = true; }
            foreach ( ['cb2fa_challenge', 'cb2fa_pincode', 'cb2fa_switch_factor'] as $key ) {
                if ( array_key_exists( $key, $input ) ) { $completion = true; }
            }
        }
        // API 2 also uses cb2fa_request on the initial native password form.
        // An active compatible controller owns malformed native-form retries too.
        // Without it, a standalone 64-hex token must retain absence protection;
        // explicit actions and challenge/factor fields always remain completion.
        $native_post = ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST'
            && ( $_REQUEST['action'] ?? 'login' ) === 'login'
            && is_string( $_POST['log'] ?? null ) && is_string( $_POST['pwd'] ?? null );
        foreach ( [$_GET, $_POST] as $input ) {
            if ( array_key_exists( 'cb2fa_request', $input ) &&
                 ( ! $native_post || ( is_string( $input['cb2fa_request'] ) && preg_match( '/\A[a-f0-9]{64}\z/', $input['cb2fa_request'] )
                    && is_wp_error( CBMM_Local_Auth::completion_peer() ) ) ) ) {
                $completion = true;
            }
        }
        if ( ! $primary && ! $completion ) { return; }
        if ( $primary && $completion ) { self::reject(); }
        if ( $completion ) {
            if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { self::reject( 405 ); }
            if ( ! self::original_action( self::COMPLETION ) ) { self::reject(); }
            $peer = CBMM_Local_Auth::completion_peer();
            if ( is_wp_error( $peer ) ) { self::reject( 503 ); }
            // The provider is selected only from server-owned state. Other API 2
            // providers must not inherit CBMM's independent/unconfigured policy.
            $token = $_POST['cb2fa_challenge'] ?? '';
            try {
                $pending = is_string( $token ) && preg_match( '/\A[a-f0-9]{64}\z/', $token ) ? $peer->cb2fa_get_challenge( $token ) : false;
            } catch ( \Throwable $error ) { self::reject( 503 ); }
            if ( is_array( $pending ) && ( $pending['interactive_login']['context']['provider_id'] ?? null ) === 'cloudbridge-mattermost' ) {
                $policy = CBMM_Local_Auth::policy();
                if ( is_wp_error( $policy ) || $policy['mode'] !== 'required' || ! CBMM_Local_Auth::ready() ) { self::reject(); }
            }
            return;
        }
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'GET' ) {
            self::reject( 405, 'GET' );
        }
        if ( ! self::original_action( self::ACTION ) || ( isset( $_GET['cbmm_oauth_route'] ) && $_GET['cbmm_oauth_route'] !== '2' ) ) { self::reject(); }
        foreach ( ['code' => 4096, 'state' => 128, 'error' => 128, 'redirect_to' => 2048, 'cbmm_register' => 3] as $key => $limit ) {
            if ( array_key_exists( $key, $_GET ) ) {
                if ( ! is_string( $_GET[$key] ) ) { self::reject( 400 ); }
                $value = wp_unslash( $_GET[$key] );
                if ( strlen( $value ) > $limit || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) { self::reject( 400 ); }
            }
        }
    }

    public static function completion_fallback() {
        self::reject( is_wp_error( CBMM_Local_Auth::completion_peer() ) ? 503 : 409 );
    }

    public static function dispatch() {
        if ( ! self::core_route() ) { self::reject(); }
        nocache_headers();
        header( 'Referrer-Policy: no-referrer' );
        require __DIR__ . '/cbmm-oauth2-flow.php';
        self::reject();
    }

    /** The registered redirect URI remains here; all verification is on core. */
    public static function relay() {
        nocache_headers();
        header( 'Referrer-Policy: no-referrer' );
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'GET' ) {
            status_header( 405 ); header( 'Allow: GET' ); exit;
        }
        $limits = ['code' => 4096, 'state' => 128, 'error' => 128, 'redirect_to' => 2048, 'cbmm_register' => 3];
        $query = [];
        foreach ( $_GET as $key => $value ) {
            if ( ! isset( $limits[$key] ) || ! is_string( $value ) ) { self::reject( 400 ); }
            $value = wp_unslash( $value );
            if ( strlen( $value ) > $limits[$key] || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) { self::reject( 400 ); }
            $query[$key] = $value;
        }
        if ( ( isset( $query['state'] ) && ! preg_match( '/\A[A-Za-z0-9_-]{1,128}\z/', $query['state'] ) )
            || ( isset( $query['code'] ) && ( $query['code'] === '' || ! isset( $query['state'] ) ) )
            || ( isset( $query['error'] ) && isset( $query['code'] ) )
            || ( isset( $query['cbmm_register'] ) && $query['cbmm_register'] !== 'yes' ) ) { self::reject( 400 ); }
        $target = add_query_arg( array_map( 'rawurlencode', $query ), self::url() );
        $registered = Cloudbridge_Mattermost::getInstance()->cbmm_config_get_oauth2_callback_url();
        $a = wp_parse_url( $target ); $b = wp_parse_url( $registered );
        if ( ! is_array( $a ) || ! is_array( $b ) || ! in_array( $a['scheme'] ?? '', ['http', 'https'], true )
            || ( $a['scheme'] ?? null ) !== ( $b['scheme'] ?? null ) || strtolower( $a['host'] ?? '' ) !== strtolower( $b['host'] ?? '' )
            || ( $a['port'] ?? ( $a['scheme'] === 'https' ? 443 : 80 ) ) !== ( $b['port'] ?? ( $b['scheme'] === 'https' ? 443 : 80 ) )
            || wp_validate_redirect( $target, '' ) !== $target ) { self::reject( 400 ); }
        wp_safe_redirect( $target, 303 );
        exit;
    }
}
