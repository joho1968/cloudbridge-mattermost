<?php
/**
 * Cloudbridge Mattermost OAuth 2.0 Client implementation
 *
 * @since      2.0.0
 * @package    Cloudbridge Mattermost
 * @subpackage cloudbridge-mattermost/includes
 * @author     Joaquim Homrighausen <joho@webbplatsen.se>
 *
 * class-cbmm-oauth2.php
 * Copyright (C) 2020 Joaquim Homrighausen; all rights reserved.
 * Development sponsored by WebbPlatsen i Sverige AB, www.webbplatsen.se
 *
 * This file is part of Cloudbridge Mattermost. Cloudbridge Mattermost is free software.
 *
 * You may redistribute it and/or modify it under the terms of the
 * GNU General Public License version 2, as published by the Free Software
 * Foundation.
 *
 * Cloudbridge Mattermost is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with the Cloudbridge Mattermost package. If not, write to:
 *  The Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor
 *  Boston, MA  02110-1301, USA.
 */
namespace CloudbridgeMattermost;

@ include $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) || ! defined( 'ABSPATH' ) ) {
    $error_message = 'WordPress has not been initialized';
    error_log( basename( __FILE__ ) . ': ' . $error_message . ' [' . $_SERVER['DOCUMENT_ROOT'] . ']' );
    echo '<h3>' . $error_message . '</h3>';
    die ();
}

if ( ! defined ( 'CBMM_VERSION' ) ) {
    error_log( basename( __FILE__ ) . ': We should not be called without the plugin loaded ' );
    wp_die( __( 'This file should not be called directly', 'cloudbridge-mattermost' ), 'Cloudbridge Mattermost' );
}


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
    header( 'Location: ' . wp_login_url() . '?cbmm_oauth2_failed=' . (int)$code );
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
if ( ! $cbmm->cbmm_oauth2_active() ) {
    // This shouldn't happen
    error_log( basename( __FILE__ ) . ': OAuth2 is not active' );
    wp_die( __('OAuth2 is not active', CBMM_PLUGINNAME_SLUG), CBMM_PLUGINNAME_HUMAN );
}


// OAuth Client
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
} catch (Exception $e) {
    error_log( basename( __FILE__ ) . ': Unable to initialize OAuth2 client [' . $e->getMessage() . ']' );
    echo '<h3>' . esc_html__( 'Unable to initialize OAuth2 client', CBMM_PLUGINNAME_SLUG ) . '</h3>';
    die ();
}

// Figure out where we are in the auth process
if ( ! empty( $_GET['error'] ) ) {
    switch( $_GET['error'] ) {
        case 'access_denied':
            // We don't treat anything here in a specific way, for now
        default:
            error_log( basename( __FILE__ ) . ': OAuth2 authentication failure [' . $_GET ['error'] . ']' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_AUTHFAIL );
            break;
    }
} elseif ( empty( $_GET['code'] ) ) {
    // Re-direct to OAuth2 provider (Mattermost). We need to create a "unique"
    // transient if we're not using PHP sessions. So we loop here. To avoid a
    // complete deadlock situation, we do this a maximum of 1000 times.
    $good_transient = false;
    for ( $i = 0; $i < 1000; $i++ ) {
        // Force new 'state' (random string) to be generated
        $auth_url = $provider->getAuthorizationUrl();
        $our_state = $provider->getState();
        $our_transient = CBMM_OAUTH_TRANSIENT_PREFIX . $our_state;
        if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
            error_log( basename( __FILE__ ) . ': Checking transient [' . $our_transient . ']' );
        }
        $wp_transient = get_transient( $our_transient );
        if ( $wp_transient === false ) {
            $our_time = time();
            if ( set_transient( $our_transient, $our_time, CBMM_OAUTH_TRANSIENT_TIMER ) ) {
                $db_time = get_transient( $our_transient );
                if ( $db_time !== $our_time ) {
                    // Silently ignore this and try again
                    if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                        error_log( basename( __FILE__ ) . ': Transient exist, but with different value, retrying' );
                    }
                } else {
                    // Update transient with possible re-direct data
                    if ( ! empty( $_REQUEST['redirect_to'] ) ) {
                        set_transient( $our_transient, $_REQUEST['redirect_to'], CBMM_OAUTH_TRANSIENT_TIMER + 1 );
                        if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                            error_log( basename( __FILE__ ) . ': "' . $our_transient . '" updated with "' . $_REQUEST['redirect_to'] . '"' );
                        }
                    } elseif ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                        error_log( basename( __FILE__ ) . ': "' . $our_transient . '" not updated, no re-direction data' );
                    }
                    // Good to go
                    $good_transient = true;
                }
            } else {
                // Silently ignore this and try again
                if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                    error_log( basename( __FILE__ ) . ': Unable to set transient, retrying' );
                }
            }
        } else {
            // Silently ignore this and try again
            if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
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
    nocache_headers();
    header( 'Location: ' . $auth_url );
    die ();
} elseif ( empty( $_GET ['state'] ) ) {
    error_log( basename( __FILE__ ) . ': Invalid OAuth2 state [' . $e->getMessage() . ']' );
    cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADSTATE );
    die ();
} else {
    $our_transient = CBMM_OAUTH_TRANSIENT_PREFIX . filter_var( $_GET['state'], FILTER_SANITIZE_STRING );
    $wp_transient = get_transient( $our_transient );
    if ( $wp_transient === false ) {
        if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
            error_log( basename( __FILE__ ) . ': Unable to locate transient [' . $our_transient . ']' );
        }
        error_log( basename( __FILE__ ) . ': Unable to verify OAuth2 callback' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOVERIFY );
    }
    // Invalidate (delete) transient, we no longer need it
    if ( ! delete_transient( $our_transient ) ) {
        // Stealthily ignore this error :-)
        if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
            error_log( basename( __FILE__ ) . ': Unable to remove validated transient [' . $our_transient . ']' );
        }
    }
    // Try to get an access token (using the authorization code grant)
    try {
        $token = $provider->getAccessToken ('authorization_code', ['code' => $_GET['code']] );
    } catch (Exception $e) {
        error_log( basename( __FILE__ ) . ': Unable to retrieve OAuth2 access token [' . $e->getMessage() . ']' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOTOKEN );
    }
    // Check for expired token
    if ($token->hasExpired ()) {
        error_log( basename( __FILE__ ) . ': External OAuth2 token has expired [' . $e->getMessage() . ']' );
        echo '<h3>' . esc_html__( 'External OAuth2 token has expired', CBMM_PLUGINNAME_SLUG ) . '</h3>';
        die ();
    }
    // Fetch user details
    $ownerDetails = $provider->getResourceOwner($token);
    $o_a = $ownerDetails->toArray ();
    if ( empty( $o_a['email'] ) || empty( $o_a['email_verified'] ) ) {
        error_log( basename( __FILE__ ) . ': Mattermost account indicates no valid e-mail address' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOEMAIL );
    }
    // Attempt to fetch user from WordPress, using e-mail
    $wp_user = get_user_by( 'email', $o_a['email'] );
    if ( $wp_user === false ) {
        // Possibly match on username if we're allowed to
        if ( $cbmm->cbmm_config_get_oauth2_allow_usernames() ) {
            $wp_user = get_user_by( 'login', $o_a['username'] );
            if ( $wp_user === false ) {
                error_log( basename( __FILE__ ) . ': Unable to locate user with username ' . $o_a['username'] );
                cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOUSER );
            }
        } else {
            // Not allowed to match on usernames, bail
            error_log( basename( __FILE__ ) . ': Unable to locate user with e-mail address ' . $o_a['email'] );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOUSER );
        }
    }
    if ( empty( $wp_user->roles ) ) {
        error_log( basename( __FILE__ ) . ': User has no active WordPress roles, "' . $wp_user->user_login . '"' );
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
        error_log( basename( __FILE__ ) . ': User role does not match allowed OAuth2 roles, "' . $wp_user->user_login . '"' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOROLE );
    }
    // Validate our (possible) redirection URL
    if ( empty( $wp_transient ) ) {
        $wp_transient = ( $is_administrator ? admin_url() : home_url() );
    } elseif ( esc_url_raw( $wp_transient ) !== $wp_transient || ! wp_http_validate_url( $wp_transient ) ) {
        $wp_transient = ( $is_administrator ? admin_url() : home_url() );
    }
    // Setup WordPress session
    wp_set_current_user( $wp_user->ID, $wp_user->user_login );
	wp_set_auth_cookie( $wp_user->ID, true );
    do_action( 'wp_login', $wp_user->user_login, $wp_user );
    // Carry on, we're logged in
    nocache_headers();
    wp_redirect( $wp_transient );
    die ();
}
