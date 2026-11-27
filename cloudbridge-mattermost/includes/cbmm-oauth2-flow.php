<?php
/** Core-login OAuth implementation; never a public standalone endpoint. */
namespace CloudbridgeMattermost;

defined( 'ABSPATH' ) || die( '-1' );
if ( ! CBMM_OAuth::core_route() ) { CBMM_OAuth::reject(); }

/**
 * Re-direct to WordPress login with error code
 */
function cbmm_admin_error_redirect( int $code ) {
    if ( empty( $code ) ) {
        $code = 1;// General failure
    }
    // We can't use admin_url() here because WordPress will attempt to
    // preserve parameters we send to it, and place them inside the
    // redirect_to ?get parameter. Odd, but it's the way it is :-)
    nocache_headers();
    wp_safe_redirect( wp_login_url() . '?cbmm_oauth2_failed=' . (int) $code );
    die();
}


// You will notice that many error messages are duplicated here. We want all
// logged error messages to be in English, and all displayed error messages to
// be in the translated language (if possible). If there's a better way to do
// this, we're all ears :-)


// Uncomment this for debugging
// define( 'CBMM_OAUTH_DEBUG', true );

// We need our plugin instance
$cbmm = Cloudbridge_Mattermost::getInstance();
require_once __DIR__ . '/class-cbmm-local-auth.php';
if ( ! $cbmm->cbmm_oauth2_active() ) {
    // This shouldn't happen
    error_log( basename( __FILE__ ) . ': OAuth2 is not active' );
    wp_die( esc_html__('OAuth2 is not active', 'cloudbridge-mattermost' ), 'Cloudbridge Mattermost' );
}
if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
    error_log( basename( __FILE__ ) . ': Initializing OAuth2 client' );
}

// Keep the browser secret out of OAuth URLs, transients, and debug logs.
// A separate cookie name/path avoids collisions with other sites on this host.
$callback_url = $cbmm->cbmm_config_get_oauth2_callback_url();
$browser_cookie_name = CBMM_OAuth::browser_cookie_name();
$browser_secret = $_COOKIE[$browser_cookie_name] ?? '';
if ( ! is_string( $browser_secret ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $browser_secret ) ) {
    $browser_secret = '';
}

// OAuth Client, Kudos to https://oauth2-client.thephpleague.com
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';

use League\OAuth2\Client\Provider\GenericProvider;

try {
    $provider = new \League\OAuth2\Client\Provider\GenericProvider([
        'clientId'                => $cbmm->cbmm_config_get_oauth2_client_id(),
        'clientSecret'            => $cbmm->cbmm_config_get_oauth2_client_secret(),
        'redirectUri'             => $cbmm->cbmm_config_get_oauth2_callback_url(),
        'urlAuthorize'            => $cbmm->cbmm_config_get_oauth2_url() . '/oauth/authorize',
        'urlAccessToken'          => $cbmm->cbmm_config_get_oauth2_url() . '/oauth/access_token',
        'urlResourceOwnerDetails' => $cbmm->cbmm_config_get_oauth2_url() . '/api/v4/users/me', /* ?format=json', */
        'prompt'                  => 'consent',
    ]);
} catch ( \Exception $e ) {
    error_log( basename( __FILE__ ) . ': Unable to initialize OAuth2 client' );
    echo '<h3>' . esc_html__( 'Unable to initialize OAuth2 client', 'cloudbridge-mattermost' ) . '</h3>';
    die ();
}

if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
    error_log( basename( __FILE__ ) . ': OAuth2 client initialized' );
}

// Figure out where we are in the auth process
if ( ! empty( $_GET['error'] ) ) {
    switch( $_GET['error'] ) {
        case 'access_denied':
            // We don't treat anything here in a specific way, for now
        default:
            error_log( basename( __FILE__ ) . ': OAuth2 authentication failure' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
            break;
    }
} elseif ( ! isset( $_GET['code'] ) ) {
    if ( isset( $_GET['state'] ) ) {
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADSTATE );
    }
    $local_policy = CBMM_Local_Auth::policy();
    if ( is_wp_error( $local_policy ) || ( $local_policy['mode'] === 'required' && ! CBMM_Local_Auth::ready() ) ) {
        error_log( basename( __FILE__ ) . ': Local login verification is not configured or available' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
    }
    $state_fingerprint = CBMM_Local_Auth::state_fingerprint();
    if ( ! is_string( $state_fingerprint ) ) { cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL ); }
    // Reuse the session cookie so another tab can finish its own pending flow.
    // The transient, not the cookie, limits each flow to fifteen minutes.
    if ( $browser_secret === '' ) {
        try {
            $browser_secret = bin2hex( random_bytes( 32 ) );
        } catch ( \Exception $e ) {
            error_log( basename( __FILE__ ) . ': Unable to generate OAuth2 browser secret' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOSESSION );
        }
        $cookie_path = wp_parse_url( site_url( 'wp-login.php', 'login' ), PHP_URL_PATH );
        if ( ! is_string( $cookie_path ) || $cookie_path === '' ) {
            $cookie_path = '/';
        }
        if ( headers_sent() || ! setcookie( $browser_cookie_name, $browser_secret, [
            'expires'  => 0,
            'path'     => $cookie_path,
            'secure'   => is_ssl() || wp_parse_url( $callback_url, PHP_URL_SCHEME ) === 'https',
            'httponly' => true,
            'samesite' => 'Lax',
        ] ) ) {
            error_log( basename( __FILE__ ) . ': Unable to set OAuth2 browser cookie' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOSESSION );
        }
    }
    // Figure out what we're trying to do
    if ( ! empty( $_REQUEST['cbmm_register'] ) && $_REQUEST['cbmm_register'] === 'yes' ) {
        $wordpress_register = true;
    } else {
        $wordpress_register = false;
    }
    if ( $wordpress_register ) {
        $mode_string = 'R';// register
    } else {
        $mode_string = 'L';// login
    }
    // Re-direct to OAuth2 provider (Mattermost). We need to create a "unique"
    // transient if we're not using PHP sessions. So we loop here. To avoid a
    // complete deadlock situation, we do this a maximum of 1000 times.
    $good_transient = false;
    for ( $i = 0; $i < 1000; $i++ ) {
        // Force new 'state' (random string) to be generated
        $auth_url = $provider->getAuthorizationUrl();
        $our_state = $provider->getState();
        $our_transient = CBMM_OAUTH_TRANSIENT_PREFIX . $our_state;
        if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
            error_log( basename( __FILE__ ) . ': Checking pending OAuth2 flow' );
        }
        $wp_transient = get_transient( $our_transient );
        if ( $wp_transient === false ) {
            $our_time = time();
            $transient_data = array( 'schema_version' => CBMM_OAuth::SCHEMA,
                                     'configuration' => $state_fingerprint,
                                     'time' => $our_time,
                                     'mode' => $mode_string,
                                     'local_policy' => $local_policy,
                                     'browser_hash' => hash( 'sha256', $browser_secret ) );
            if ( set_transient( $our_transient, $transient_data, CBMM_OAUTH_TRANSIENT_TIMER ) ) {
                $db_transient = get_transient( $our_transient );
	            $db_time = false;
                if ( $db_transient === false ) {
                    if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
                        error_log( basename( __FILE__ ) . '(' . __LINE__ .'): Unable to locate pending OAuth2 flow' );
                    }
                } else {
                    if ( empty( $db_transient['time'] ) ) {
                        if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
                            error_log( basename( __FILE__ ) . '(' . __LINE__ .'): Pending OAuth2 flow has no valid timestamp' );
                        }
                    } else {
                        $db_time = $db_transient['time'];
                        if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
                            error_log( basename( __FILE__ ) . '(' . __LINE__ .'): Pending OAuth2 flow timestamp checked' );
                        }
                    }
                }
                if ( $db_time !== $our_time ) {
                    // Silently ignore this and try again
                    if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
                        error_log( basename( __FILE__ ) . ': Transient exist, but with different value, retrying' );
                    }
                } else {
                    // Update transient with possible re-direct data
                    if ( ! empty( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ) {
                        $transient_data['redirect'] = wp_unslash( $_REQUEST['redirect_to'] );
                        set_transient( $our_transient, $transient_data, CBMM_OAUTH_TRANSIENT_TIMER + 1);
                        if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
                            error_log( basename( __FILE__ ) . ': Pending OAuth2 redirect updated' );
                        }
                    } elseif ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
                        error_log( basename( __FILE__ ) . ': No OAuth2 redirect requested' );
                        error_log( basename( __FILE__ ) . ': OAuth2 initiation complete' );
                    }
                    // Good to go
                    $good_transient = true;
                }
            } else {
                // Silently ignore this and try again
                if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
                    error_log( basename( __FILE__ ) . ': Unable to set transient, retrying' );
                }
            }
        } else {
            // Silently ignore this and try again
            if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
                error_log( basename( __FILE__ ) . ': Transient exist, retrying' );
            }
        }
        if ( $good_transient ) {
            // Good to go
            break;
        }
    }// for
    // Make sure we handle this (possibly rate) situation gracefully
    if ( ! $good_transient ) {
        error_log( basename( __FILE__ ) . ': Unable to generate unique session token' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOSESSION );
    }
    // Re-direct to Mattermost for authentication
    if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
        error_log( basename( __FILE__ ) . '(' . __LINE__ .'): Redirecting to OAuth2 provider' );
    }
    nocache_headers();
    header( 'Location: ' . $auth_url );
    die();
} elseif ( ! is_string( $_GET['code'] ) || $_GET['code'] === '' || strlen( $_GET['code'] ) > 4096 ||
           empty( $_GET['state'] ) || ! is_string( $_GET['state'] ) || strlen( $_GET['state'] ) > 128 ||
           ! preg_match( '/\A[A-Za-z0-9_-]+\z/', $_GET['state'] ) ) {
    error_log( basename( __FILE__ ) . ': Invalid OAuth2 state' );
    cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADSTATE );
    die();
} else {
    $our_transient = CBMM_OAUTH_TRANSIENT_PREFIX . $_GET['state'];
    $transient_data = get_transient( $our_transient );
    if ( $transient_data === false ) {
        if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
            error_log( basename( __FILE__ ) . ': Unable to locate pending OAuth2 flow' );
        }
        error_log( basename( __FILE__ ) . ': Unable to verify OAuth2 callback' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOVERIFY );
    }
    // Reject missing/mismatched cookies and pre-update flows before contacting
    // Mattermost. A different browser must not consume the legitimate flow.
    if ( ! is_array( $transient_data ) || ( $transient_data['schema_version'] ?? null ) !== CBMM_OAuth::SCHEMA || $browser_secret === '' ||
         empty( $transient_data['browser_hash'] ) || ! is_string( $transient_data['browser_hash'] ) ||
         ! hash_equals( $transient_data['browser_hash'], hash( 'sha256', $browser_secret ) ) ) {
        error_log( basename( __FILE__ ) . ': Unable to verify OAuth2 browser binding' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOVERIFY );
    }
    // Proceed only if this request consumes the flow. A failed deletion can
    // mean another callback already claimed it, or storage is unavailable.
    $local_policy = CBMM_Local_Auth::reconcile( $transient_data['local_policy'] ?? null );
    if ( is_wp_error( $local_policy ) || ( $local_policy['mode'] === 'required' && ! CBMM_Local_Auth::ready() ) ) {
        error_log( basename( __FILE__ ) . ': Local login verification policy changed or is unavailable' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
    }
    $fingerprint = CBMM_Local_Auth::state_fingerprint();
    if ( ! is_string( $fingerprint ) || ! is_string( $transient_data['configuration'] ?? null ) ||
         ! hash_equals( $fingerprint, $transient_data['configuration'] ) ) {
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
    }
    if ( ! delete_transient( $our_transient ) ) {
        error_log( basename( __FILE__ ) . ': Unable to consume OAuth2 flow' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOVERIFY );
    } else {
        if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
            error_log( basename( __FILE__ ) . '(' . __LINE__ .'): Pending OAuth2 flow consumed' );
        }
    }
    // Try to get an access token (using the authorization code grant)
    try {
	    $token = $provider->getAccessToken( 'authorization_code', ['code' => wp_unslash( $_GET['code'] ) ] );
	    // Check for expired token
	    if ( $token->hasExpired() ) {
		    error_log( basename( __FILE__ ) . ': External OAuth2 token has expired' );
		    echo '<h3>' . esc_html__( 'External OAuth2 token has expired', 'cloudbridge-mattermost' ) . '</h3>';
		    die();
	    }
    } catch( \Throwable $e ) {
        // Provider errors can contain response data or credentials. Keep logs generic.
        error_log( basename( __FILE__ ) . ': Unable to retrieve OAuth2 access token' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOTOKEN );
		die();
    }
    // Fetch and validate provider data before it reaches WordPress string APIs
    // or user insertion. A failed/malformed response must not create a session.
    try {
        $ownerDetails = $provider->getResourceOwner( $token );
        $o_a = $ownerDetails->toArray();
    } catch ( \Throwable $e ) {
        error_log( basename( __FILE__ ) . ': Unable to retrieve OAuth2 account details' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
    }
    if ( ! is_array( $o_a ) ) {
        error_log( basename( __FILE__ ) . ': Invalid OAuth2 account details' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
    }
    if ( empty( $o_a['email'] ) || ! is_string( $o_a['email'] ) || ! is_email( $o_a['email'] ) ||
         ! isset( $o_a['email_verified'] ) || $o_a['email_verified'] !== true ) {
        error_log( basename( __FILE__ ) . ': Mattermost account indicates no valid e-mail address' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOEMAIL );
    }
    if ( empty( $o_a['id'] ) || ! is_string( $o_a['id'] ) || trim( $o_a['id'] ) === '' ) {
        error_log( basename( __FILE__ ) . ': Mattermost account indicates no ID' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOID );
    }
    if ( empty( $o_a['username'] ) || ! is_string( $o_a['username'] ) || trim( $o_a['username'] ) === '' ) {
        error_log( basename( __FILE__ ) . ': Invalid OAuth2 account username' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
    }
    foreach ( ['first_name', 'last_name'] as $name_field ) {
        if ( isset( $o_a[$name_field] ) && ! is_string( $o_a[$name_field] ) ) {
            error_log( basename( __FILE__ ) . ': Invalid OAuth2 account name' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
        }
    }
    if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
        error_log( basename( __FILE__ ) . ': Verified OAuth2 profile received' );
    }
    $oauth_verified_at = time();
    // Figure out what we're actually doing
    if ( ! empty( $transient_data['mode'] ) && $transient_data['mode'] === 'R' ) {
        $is_wordpress_register = true;
    } else {
        $is_wordpress_register = false;
    }
    // Handle registration flow first, @since 2.2.0 ---------------------------
    if ( $is_wordpress_register ) {
        // Make sure we actually allow registering via Mattermost
        if ( ! $cbmm->cbmm_config_get_oauth2_allow_register() ) {
            error_log( basename( __FILE__ ) . ': New user registration via Mattermost is not enabled' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOREG );
        }
        // Make sure this e-mail address is not already in use in WordPress
        $wp_email = get_user_by( 'email', $o_a['email'] );
        if ( $wp_email !== false ) {
            error_log( basename( __FILE__ ) . ': Provider email already exists in WordPress; registration rejected' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADCRED );
        }
        $wp_login = get_user_by( 'login', $o_a['email'] );
        if ( $wp_login !== false ) {
            error_log( basename( __FILE__ ) . ': Provider account conflicts with an existing WordPress login' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADCRED );
        }
        // Make sure username is not already used in WordPress
        $wp_username = $o_a['username'];
        $wp_user = get_user_by( 'login', $wp_username  );
        if ( $wp_user !== false ) {
            // Username does exist in WordPress already
            error_log( basename( __FILE__ ) . ': Provider username collision; trying the configured suffix' );
            // Possibly allow for username_id
            if ( $cbmm->cbmm_config_get_oauth2_register_use_mm_id_for_uuname() ) {
                $wp_username = sanitize_user( $wp_username . '_' . $o_a['id'] );
                $wp_user = get_user_by( 'login', $wp_username  );
                if ( $wp_user !== false ) {
                    error_log( basename( __FILE__ ) . ': Provider username collision; registration rejected' );
                    cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADCRED );
                }
            } else {
                cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADCRED );
            }
        }
        // Generate some data to insert
        $wp_random_password = wp_generate_password();
        if ( ! empty( $o_a['first_name'] ) ) {
            $display_name = $o_a['first_name'];
            if ( ! empty( $o_a['last_name'] ) ) {
                $display_name .= ' ' . $o_a['last_name'];
            }
        } elseif ( ! empty( $o_a['last_name'] ) ) {
            $display_name = $o_a['last_name'];
        } else {
            $display_name = '';
        }
        $wp_user = wp_insert_user(
                       array(
                           'user_pass' => $wp_random_password,
                           'user_login' => $wp_username,
                           'user_email' => $o_a['email'],
                           'first_name' => ( empty( $o_a['first_name'] ) ? '':$o_a['first_name'] ),
                           'last_name' => ( empty( $o_a['last_name'] ) ? '':$o_a['last_name'] ),
                       )
                   );
        // Check result of 'insert user'
        if ( is_wp_error( $wp_user ) ) {
            // Something's not right
            error_log( basename( __FILE__ ) . ': Unable to create WordPress user from verified provider identity' );
            if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
                error_log( basename( __FILE__ ) . ': WordPress user insertion returned an error' );
            }
            // Abort and "bounce back"
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADCRED );
        }
    }
    // Handle login flow second -----------------------------------------------

    // Attempt to fetch user from WordPress, using e-mail
    $wp_user = get_user_by( 'email', $o_a['email'] );
    if ( $wp_user === false ) {
        // Possibly match on username if we're allowed to
        if ( $cbmm->cbmm_config_get_oauth2_allow_usernames() ) {
            // Possibly allow for username_id
            if ( $cbmm->cbmm_config_get_oauth2_register_use_mm_id_for_uuname() ) {
                $wp_username = sanitize_user( $o_a['username'] . '_' . $o_a['id'] );
            } else {
                $wp_username = sanitize_user( $o_a['username'] );
            }
            $wp_user = get_user_by( 'login', $wp_username );
            if ( $wp_user === false ) {
                error_log( basename( __FILE__ ) . ': No matching WordPress username' );
                cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOUSER );
            }
        } else {
            // Not allowed to match on usernames, bail
            error_log( basename( __FILE__ ) . ': No matching WordPress email address' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOUSER );
        }
    }
    if ( empty( $wp_user->roles ) ) {
        error_log( basename( __FILE__ ) . ': WordPress user has no active roles' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOROLE );
    }
    // Check roles
    $login_roles = $cbmm->cbmm_config_get_oauth2_login_roles();
    $good_role = false;
    $is_administrator = false;
    foreach ( $wp_user->roles as $role ) {
        if ( $role == 'administrator' ) {
            $is_administrator = true;
        }
        if ( in_array( $role, $login_roles ) ) {
            $good_role = true;
            break;
        }
    }
    if ( ! $good_role ) {
        error_log( basename( __FILE__ ) . ': WordPress role is not allowed for OAuth2' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOROLE );
    }
    // Validate and constrain redirect URL to same-host
    $default_redirect = ( $is_administrator ? admin_url() : home_url() );
    if ( empty( $transient_data['redirect'] ) ) {
        $redirect_to = $default_redirect;
    } else {
        $redirect_candidate = $transient_data['redirect'];
        // wp_validate_redirect() falls back to default if invalid
        $validated = wp_validate_redirect( $redirect_candidate, $default_redirect );
        // Enforce same host as site (defense in depth)
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $redir_host = wp_parse_url( $validated, PHP_URL_HOST );
        if ( empty( $redir_host ) || $redir_host !== $site_host ) {
            $redirect_to = $default_redirect;
        } else {
            $redirect_to = $validated;
        }
    }
    if ( defined( 'CBMM_OAUTH_DEBUG' ) && CBMM_OAUTH_DEBUG ) {
        error_log( basename( __FILE__ ) . ': OAuth2 destination validated' );
    }
    // Required integration is terminal: CB2FA owns MFA and final session creation.
    // No exception or unexpected return can reach the independent cookie block.
    $current_policy = CBMM_Local_Auth::reconcile( $local_policy );
    if ( is_wp_error( $current_policy ) ) {
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
    }
    if ( $current_policy['mode'] === 'required' ) {
        CBMM_Local_Auth::login( $wp_user, $o_a, $current_policy, $redirect_to, $oauth_verified_at );
        error_log( basename( __FILE__ ) . ': Required local login verification did not complete' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
    }
    // Explicit independent mode still runs the complete core provider chain.
    $secure_cookie = null;
    $wp_user = $cbmm->cbmm_oauth2_authenticate_user( $wp_user, $secure_cookie );
    if ( is_wp_error( $wp_user ) || ! CBMM_Local_Auth::independent_allowed( $wp_user ) ||
         is_wp_error( CBMM_Local_Auth::reconcile( $current_policy ) ) ) {
        error_log( basename( __FILE__ ) . ': Local authentication policy rejected OAuth2 login' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
    }
    // Setup WordPress session
    wp_set_current_user( $wp_user->ID, $wp_user->user_login );
	wp_set_auth_cookie( $wp_user->ID, true, $secure_cookie );
    do_action( 'wp_login', $wp_user->user_login, $wp_user );
    // Carry on, we're logged in
    nocache_headers();
    wp_safe_redirect( $redirect_to );
    die ();
}
