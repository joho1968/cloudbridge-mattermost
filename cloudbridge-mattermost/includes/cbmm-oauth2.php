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
 * Copyright (C) 2020-2024 Joaquim Homrighausen; all rights reserved.
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
    wp_die( __('OAuth2 is not active', 'cloudbridge-mattermost' ), CBMM_PLUGINNAME_HUMAN );
}
if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
    error_log( basename( __FILE__ ) . ': $_REQUEST before OAuth2 ' . var_export( $_REQUEST, true ) );
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
} catch (Exception $e) {
    error_log( basename( __FILE__ ) . ': Unable to initialize OAuth2 client [' . $e->getMessage() . ']' );
    echo '<h3>' . esc_html__( 'Unable to initialize OAuth2 client', 'cloudbridge-mattermost' ) . '</h3>';
    die ();
}

if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
    error_log( basename( __FILE__ ) . ': $_REQUEST after OAuth2 ' . var_export( $_REQUEST, true ) );
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
} elseif ( ! isset( $_GET['code'] ) ) {
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
        if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
            error_log( basename( __FILE__ ) . ': Checking transient [' . $our_transient . ']' );
        }
        $wp_transient = get_transient( $our_transient );
        if ( $wp_transient === false ) {
            $our_time = time();
            $transient_data = array( 'time' => $our_time,
                                     'mode' => $mode_string );
            if ( set_transient( $our_transient, $transient_data, CBMM_OAUTH_TRANSIENT_TIMER ) ) {
                $db_transient = get_transient( $our_transient );
                if ( $db_transient === false ) {
                    $db_time = false;
                    if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                        error_log( basename( __FILE__ ) . '(' . __LINE__ .'): Unable to locate transient "' . $our_transient . '"' );
                    }
                } else {
                    if ( empty( $db_transient['time'] ) ) {
                        if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                            error_log( basename( __FILE__ ) . '(' . __LINE__ .'): No time field in "' . var_export( $db_transient, true ) . '", ignorning' );
                        }
                    } else {
                        $db_time = $db_transient['time'];
                        if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                            error_log( basename( __FILE__ ) . '(' . __LINE__ .'): $our_time="' . $our_time . '" $db_time="' . $db_time . '"' );
                        }
                    }
                }
                if ( $db_time !== $our_time ) {
                    // Silently ignore this and try again
                    if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                        error_log( basename( __FILE__ ) . ': Transient exist, but with different value, retrying' );
                    }
                } else {
                    // Update transient with possible re-direct data
                    if ( ! empty( $_REQUEST['redirect_to'] ) ) {
                        $transient_data['redirect'] = $_REQUEST['redirect_to'];
                        set_transient( $our_transient, $transient_data, CBMM_OAUTH_TRANSIENT_TIMER + 1);
                        if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                            error_log( basename( __FILE__ ) . ': "' . $our_transient . '" updated to "' . serialize( $transient_data ) . '"' );
                        }
                    } elseif ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                        error_log( basename( __FILE__ ) . ': "' . $our_transient . '" not updated, no re-direction data' );
                        error_log( basename( __FILE__ ) . ': REQUEST data ' . var_export( $_REQUEST, true ) );
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
    if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
        error_log( basename( __FILE__ ) . '(' . __LINE__ .'): Re-directing to "' . $auth_url . '"' );
    }
    nocache_headers();
    header( 'Location: ' . $auth_url );
    die();
} elseif ( empty( $_GET ['state'] ) ) {
    error_log( basename( __FILE__ ) . ': Invalid OAuth2 state [' . $e->getMessage() . ']' );
    cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADSTATE );
    die();
} else {
    $our_transient = CBMM_OAUTH_TRANSIENT_PREFIX . filter_var( $_GET['state'], FILTER_SANITIZE_STRING );
    $transient_data = get_transient( $our_transient );
    if ( $transient_data === false ) {
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
    } else {
        if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
            error_log( basename( __FILE__ ) . '(' . __LINE__ .'): Transient "' . $our_transient . '" deleted' );
        }
    }
    // Try to get an access token (using the authorization code grant)
    try {
        $token = $provider->getAccessToken( 'authorization_code', ['code' => $_GET['code']] );
    } catch( Exception $e ) {
        error_log( basename( __FILE__ ) . ': Unable to retrieve OAuth2 access token [' . $e->getMessage() . ']' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOTOKEN );
    }
    // Check for expired token
    if ( $token->hasExpired() ) {
        error_log( basename( __FILE__ ) . ': External OAuth2 token has expired [' . $e->getMessage() . ']' );
        echo '<h3>' . esc_html__( 'External OAuth2 token has expired', 'cloudbridge-mattermost' ) . '</h3>';
        die();
    }
    // Fetch user details
    $ownerDetails = $provider->getResourceOwner( $token );
    $o_a = $ownerDetails->toArray();
    if ( empty( $o_a['email'] ) || empty( $o_a['email_verified'] ) ) {
        error_log( basename( __FILE__ ) . ': Mattermost account indicates no valid e-mail address' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOEMAIL );
    }
    if ( empty( $o_a['id'] ) ) {
        error_log( basename( __FILE__ ) . ': Mattermost account indicates no ID' );
        cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_NOID );
    }
    if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
        error_log( basename( __FILE__ ) . ': O_A ' . var_export( $o_a, true ) );
    }
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
            error_log( basename( __FILE__ ) . ': E-mail address "' . $o_a['email'] . '" already exist in WordPress, registration failed' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADCRED );
        }
        $wp_login = get_user_by( 'login', $o_a['email'] );
        if ( $wp_login !== false ) {
            error_log( basename( __FILE__ ) . ': Username "' . $o_a['email'] . '" already exist in WordPress, registration failed' );
            cbmm_admin_error_redirect( CBMM_OAUTH_REDERR_BADCRED );
        }
        // Make sure username is not already used in WordPress
        $wp_username = $o_a['username'];
        $wp_user = get_user_by( 'login', $wp_username  );
        if ( $wp_user !== false ) {
            // Username does exist in WordPress already
            error_log( basename( __FILE__ ) . ': Username "' . $wp_username . '" already exist in WordPress, attempting to create new username' );
            // Possibly allow for username_id
            if ( $cbmm->cbmm_config_get_oauth2_register_use_mm_id_for_uuname() ) {
                $wp_username = sanitize_user( $wp_username . '_' . $o_a['id'] );
                $wp_user = get_user_by( 'login', $wp_username  );
                if ( $wp_user !== false ) {
                    error_log( basename( __FILE__ ) . ': Username "' . $wp_username . '" already exist in WordPress, registration failed' );
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
            error_log( basename( __FILE__ ) . ': Unable to create user via Mattermost ("' . $o_a['email'] .'", "' . $wp_username . '")' );
            if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
                error_log( basename( __FILE__ ) . ': wp_insert_user() => "' . $wp_user->get_error_message() . '"' );
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
                error_log( basename( __FILE__ ) . ': Unable to locate user with username "' . $wp_username . '"' );
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
    if ( empty( $transient_data['redirect'] ) ) {
        $redirect_to = ( $is_administrator ? admin_url() : home_url() );
    } elseif ( esc_url_raw( $transient_data['redirect'] ) !== $transient_data['redirect'] || ! wp_http_validate_url( $transient_data['redirect'] ) ) {
        $redirect_to = ( $is_administrator ? admin_url() : home_url() );
    } else {
        $redirect_to = $transient_data['redirect'];
    }
    if ( defined( 'CBMM_OAUTH_DEBUG' ) ) {
        error_log( basename( __FILE__ ) . ': redirect_to="' . $redirect_to . '"' );
    }
    // Setup WordPress session
    wp_set_current_user( $wp_user->ID, $wp_user->user_login );
	wp_set_auth_cookie( $wp_user->ID, true );
    do_action( 'wp_login', $wp_user->user_login, $wp_user );
    // Carry on, we're logged in
    nocache_headers();
    wp_redirect( $redirect_to );
    die ();
}
