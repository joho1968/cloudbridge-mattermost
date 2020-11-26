<?php
/**
 * Cloudbridge Mattermost
 *
 * @link              https://code.webbplatsen.net/wordpress/cloudbridge-mattermost/
 * @since             1.0.0
 * @package           Cloudbridge Mattermost
 * @author            Joaquim Homrighausen <joho@webbplatsen.se>
 *
 * @wordpress-plugin
 * Plugin Name:       Cloudbridge Mattermost
 * Plugin URI:        https://code.webbplatsen.net/wordpress/cloudbridge-mattermost/
 * Description:       Provides integration between Mattermost and WordPress
 * Version:           1.1.0
 * Author:            WebbPlatsen, Joaquim Homrighausen <joho@webbplatsen.se>
 * Author URI:        https://webbplatsen.se/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cloudbridge-mattermost
 * Domain Path:       /languages
 *
 * cloudbridge-mattermost.php
 * Copyright (C) 2020 Joaquim Homrighausen; all rights reserved.
 * Development sponsored by WebbPlatsen i Sverige AB, www.webbplatsen.se
 */
namespace CloudbridgeMattermost;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}


define( 'CBMM_VERSION',                 '1.1.0'                  );
define( 'CBMM_REV',                     1                        );
define( 'CBMM_PLUGINNAME_HUMAN',        'Cloudbridge Mattermost' );
define( 'CBMM_PLUGINNAME_SLUG',         'cloudbridge-mattermost' );
define( 'CBMM_ALERT_SUCCESS',           1                        );
define( 'CBMM_ALERT_FAILURE',           2                        );
define( 'CBMM_ALERT_RESET_PASSWORD',    3                        ); // @since 1.1.0
define( 'CBMM_ALERT_PLUGIN_ACTIVATE',   4                        ); // @since 1.1.0
define( 'CBMM_ALERT_PLUGIN_DEACTIVATE', 5                        ); // @since 1.1.0
define( 'CBMM_ALERT_PLUGIN_UNINSTALL',  6                        ); // @since 1.1.0
define( 'CBMM_ALERT_USER_ADD',          7                        ); // @since 1.1.0
define( 'CBMM_ALERT_USER_DELETE',       8                        ); // @since 1.1.0
define( 'CBMM_EMOJI_DEFAULT_NOTICE',    ':unlock:'               );
define( 'CBMM_EMOJI_DEFAULT_WARNING',   ':stop_sign:'            );
define( 'CBMM_EMOJI_DEFAULT_LINK',      ':fast_forward:'         );
define( 'CBMM_EMOJI_DEFAULT_BELL',      ':bell:'                 ); // @since 1.1.0


class Cloudbridge_Mattermost {
	public static $instance = null;
	protected $plugin_name;
	protected $version;
    protected $cbmm_wp_roles = null;
    protected $cbmm_wp_roles_enus = null;                       // @since 1.1.0
    protected $cbmm_settings_tab = '';
    protected $cbmm_action_plugin;                              // @since 1.1.0
	protected $cbmm_notify_activate_plugin;                     // @since 1.1.0
	protected $cbmm_notify_deactivate_plugin;                   // @since 1.1.0
	protected $cbmm_notify_install_plugin;                      // @since 1.1.0
	protected $cbmm_notify_uninstall_plugin;                    // @since 1.1.0
	protected $cbmm_notice_emoji; // Historical head trip, it should have been "login" :-)
	protected $cbmm_warning_emoji;
	protected $cbmm_link_emoji;
	protected $cbmm_bell_emoji;                                 // @since 1.1.0
	protected $cbmm_link_admin;
	protected $cbmm_mm_webhook;
	protected $cbmm_mm_username;
	protected $cbmm_mm_channel;
	protected $cbmm_mm_mention;
    protected $cbmm_mm_roles_notify;
    protected $cbmm_mm_roles_warn;
    protected $cbmm_mm_unknown_warn;
    protected $cbmm_mm_roles_password_reset;                    // @since 1.1.0
    protected $cbmm_mm_roles_password_reset_skip_email;         // @since 1.1.0
    protected $cbmm_mm_roles_user_add;                          // @since 1.1.0
    protected $cbmm_mm_roles_user_delete;                       // @since 1.1.0
    protected $cbmm_force_locale_ENUS;                          // @since 1.1.0
	protected $cbmm_settings_remove;

	public static function getInstance()
	{
		null === self::$instance AND self::$instance = new self();
		return self::$instance;
	}
	/**
	 * Start me up ...
	 */
	public function __construct() {
		if ( defined( 'CBMM_VERSION' ) ) {
			$this->version = CBMM_VERSION;
		} else {
			$this->version = '1.1.0';
		}
		$this->plugin_name = CBMM_PLUGINNAME_SLUG;
        // Fetch options and setup defaults
        $this->cbmm_site_label = $this->cbmm_get_option( 'cbmm-site-label', true );
        $this->cbmm_mm_webhook = $this->cbmm_get_option( 'cbmm-mm-webhook', false );
        $this->cbmm_mm_roles_notify = $this->cbmm_get_option( 'cbmm-roles-notify', true );
        $this->cbmm_mm_roles_warn = $this->cbmm_get_option( 'cbmm-roles-warn', true );
        $this->cbmm_mm_unknown_warn = $this->cbmm_get_option( 'cbmm-unknown-warn', true );
        $this->cbmm_mm_roles_password_reset = $this->cbmm_get_option( 'cbmm-roles-password-reset', false );
        $this->cbmm_mm_roles_password_reset_skip_email = $this->cbmm_get_option( 'cbmm-roles-password-skip-email', false );
        $this->cbmm_notify_activate_plugin = $this->cbmm_get_option( 'cbmm-notify-activate-plugin', false );
        $this->cbmm_notify_deactivate_plugin = $this->cbmm_get_option( 'cbmm-notify-deactivate-plugin', false );
        $this->cbmm_notify_uninstall_plugin = $this->cbmm_get_option( 'cbmm-notify-uninstall-plugin', false );
        $this->cbmm_mm_roles_user_add = $this->cbmm_get_option( 'cbmm-roles-user-add', false );
        $this->cbmm_mm_roles_user_delete = $this->cbmm_get_option( 'cbmm-roles-user-delete', false );

        $this->cbmm_link_admin = $this->cbmm_get_option( 'cbmm-link-admin', true );
        $this->cbmm_bell_emoji = $this->cbmm_get_option( 'cbmm-bell-emoji', true );
        $this->cbmm_notice_emoji = $this->cbmm_get_option( 'cbmm-notice-emoji', true );
        $this->cbmm_warning_emoji = $this->cbmm_get_option( 'cbmm-warning-emoji', true );
        $this->cbmm_link_emoji = $this->cbmm_get_option( 'cbmm-link-emoji', true );
        $this->cbmm_mm_username = $this->cbmm_get_option( 'cbmm-mm-username', false );
        $this->cbmm_mm_channel = $this->cbmm_get_option( 'cbmm-mm-channel', false );
        $this->cbmm_mm_mention = $this->cbmm_get_option( 'cbmm-mm-mention', false );

        $this->cbmm_force_locale_ENUS = $this->cbmm_get_option( 'cbmm-force-locale-enus', false );
        $this->cbmm_settings_remove = $this->cbmm_get_option( 'cbmm-settings-remove', false );

        $this->cbmm_settings_tab = ( ! empty( $_GET['tab'] ) ? $_GET['tab'] : '' );
        if ( ! in_array( $this->cbmm_settings_tab, ['notify', 'emoji', 'advanced', 'about'] ) ) {
            $this->cbmm_settings_tab = '';
        }
	}

    /**
     * Fetch filemtime() of filen and return it.
     *
     * Fetch filemtime() of $filename and return it, upon error, $this->version
     * is returned instead. This could possibly simply return $this->version in
     * production.
     *
	 * @since  1.0.0
     * @param  string $filename The file for which we want filemtime()
     * @return string
     */
    protected function resource_mtime( $filename ) {
        $filetime = @ filemtime( $filename );
        if ( $filetime === false ) {
            $filetime = $this->version;
        }
        return ( $filetime );
    }

    /**
     * Setup CSS.
     *
	 * @since 1.0.0
     */
    public function cbmm_setup_css() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cloudbridge-mattermost.css', array(), $this->resource_mtime( dirname(__FILE__).'/css/cloudbridge-mattermost.css' ), 'all' );
    }

    /**
     * Allow (some) strings to be forced to en_US.
     *
     * Some Mattermost environments may want only English language notifications
     * depending on who's monitoring them. Thus we allow the CBMM plugin text
     * to be in whatever locale it's requested to be in, while at the same time
     * allowing notifications to be in English.
     *
     * @since 1.1.0
     * @param string $lang_string The string to be translated/untranslated
     * @return string The string to use by the calling function
     */
    protected function cbmm_get_lang_string( string $lang_string, bool $force_override = false ) : string {
        if ( $this->cbmm_force_locale_ENUS || $force_override ) {
            return( $lang_string );
        } else {
            return( __($lang_string, $this->plugin_name ) );
        }
    }

    /**
     * Fetch setting with default value.
     *
     * @since 1.0.0
     */
    protected function cbmm_get_option( string $option_name, bool $auto_logic = false ) {
        switch( $option_name ) {
            case 'cbmm-site-label':
                $option_val = get_option( 'cbmm-site-label', '' );
                if ( empty( $option_val ) && $auto_logic ) {
                    $option_val = trim( get_bloginfo( 'name' ) );
                    if ( empty( $option_val ) ) {
                        $option_val = trim( $_SERVER['SERVER_NAME'] );
                        if ( empty( $option_val ) ) {
                            $option_val = 'IP:' . $_SERVER['SERVER_ADDR'];
                        }
                    }
                }
                if ( $auto_logic ) {
                    $default_val = '(' . $this->cbmm_get_lang_string( 'Unknown' ) . ')';
                } else {
                    $default_val = '';
                }
                break;
            case 'cbmm-bell-emoji':
                $default_val = ( $auto_logic ? CBMM_EMOJI_DEFAULT_BELL : '' );
                break;
            case 'cbmm-notice-emoji':
                $default_val = ( $auto_logic ? CBMM_EMOJI_DEFAULT_NOTICE : '' );
                break;
            case 'cbmm-warning-emoji':
                $default_val = ( $auto_logic ? CBMM_EMOJI_DEFAULT_WARNING : '' );
                break;
            case 'cbmm-link-emoji':
                $default_val = ( $auto_logic ? CBMM_EMOJI_DEFAULT_LINK : '' );
                break;
            case 'cbmm-roles-notify':
            case 'cbmm-roles-warn':
            case 'cbmm-roles-password-reset':
            case 'cbmm-roles-password-skip-email':
            case 'cbmm-roles-user-add':
            case 'cbmm-roles-user-delete':
                // Default is in JSON format
                $default_val = ( $auto_logic ? '["administrator"]' : '' );
                break;
            case 'cbmm-unknown-warn':
            case 'cbmm-link-admin':
                $default_val = ( $auto_logic ? '0' : '' );
                break;
            default:
                $default_val = '';
                break;
        } // switch
        if ( $option_name != 'cbmm-site-label' ) {
            $option_val = get_option ( $option_name, $default_val );
        }
        if ( empty( $option_val ) ) {
            $option_val = $default_val;
        }
        return( $option_val );
    }

    /**
     * Fetch WordPress roles.
     *
     * Fetch WordPress roles with WP names and human names, if possible. One could
     * argue that we can just fetch a list of role names from WP, but we may miss
     * roles with no names ... or not? :-)
     *
     * @since 1.0.0
     * @param boolean $force_locale Mirrors the "Force en_US locale"
     * @return array List of roles and their human names
     */
    protected function cbmm_get_wp_roles( bool $force_locale ) : array {
        if ( $force_locale ) {
            if ( $this->cbmm_wp_roles_enus != null ) {
                return( $this->cbmm_wp_roles_enus );
            }
        } else {
            if ( $this->cbmm_wp_roles !== null ) {
                return( $this->cbmm_wp_roles );
            }
        }
        $wp_roles = wp_roles();
        if ( is_object( $wp_roles ) ) {
            // not sure why WP_Roles::get_roles_data() returns false
            // $roles = $wp_roles->get_roles_data();
            $roles = array_keys( $wp_roles->roles );
            $role_names = $role_names_en = $wp_roles->get_names();
        } else {
            $roles = false;
            $role_names = $role_names_en = array();
        }
        $return_roles = array();
        if ( is_array( $roles ) ) {
            foreach( $roles as $role_k => $role_v ) {
                if ( ! empty( $role_names_en[$role_v] ) ) {
                    $return_roles_en[$role_v] = $role_names_en[$role_v];
                } else {
                    $return_roles_en[$role_v] = cbmm_get_lang_string( 'Unknown role', true ) . ' (' . $role_v . ')';
                }
                if ( ! empty( $role_names[$role_v] ) ) {
                    $return_roles[$role_v] = translate_user_role( $role_names[$role_v] );
                } else {
                    $return_roles[$role_v] = cbmm_get_lang_string( 'Unknown role', false ) . ' (' . $role_v . ')';
                }
            }
        } else {
            error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): wp_roles() returned empty' );
        }
        $this->cbmm_wp_roles = $return_roles;
        $this->cbmm_wp_roles_enus = $return_roles_en;
        if ( $force_locale ) {
            return( $return_roles_en );
        }
        return( $return_roles );
    }

    /**
     * Setup WordPress admin menu.
     *
     * @since  1.0.0
     */
    public function cbmm_menu() {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
		add_options_page( CBMM_PLUGINNAME_HUMAN,
						  CBMM_PLUGINNAME_HUMAN,
					      'administrator',
					      CBMM_PLUGINNAME_SLUG,
					      [ $this, 'cbmm_admin_page' ]
						);
    }
    /**
     * Setup WordPress admin options page.
     *
	 * @since  1.0.0
     */
    public function cbmm_admin_page() {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
        // Get ourselves a proper URL
        $action = admin_url( 'admin.php' ) . '?page=' . CBMM_PLUGINNAME_SLUG;
        //
        $html = '<div class="wrap">';
            $html .= '<h1><span class="dashicons dashicons-cloud" style="vertical-align:middle;"></span>&nbsp;' . CBMM_PLUGINNAME_HUMAN . '</h1>';
            $html .= '<p>' . esc_html__( 'Provides integration between Mattermost and WordPress', $this->plugin_name ) . '</p>';
            $html .= '<nav class="nav-tab-wrapper">';
            $html .= '<a href="' . $action . '" class="nav-tab' . ( empty( $this->cbmm_settings_tab ) ? ' nav-tab-active':'' ) . '">'.
                     esc_html__( 'Basic configuration', $this->plugin_name ) .
                     '</a>';
            $html .= '<a href="' . $action . '&tab=notify" class="nav-tab' . ( $this->cbmm_settings_tab === 'notify' ? ' nav-tab-active':'' ) . '">'.
                     esc_html__( 'Notifications', $this->plugin_name ) .
                     '</a>';
            $html .= '<a href="' . $action . '&tab=emoji" class="nav-tab' . ( $this->cbmm_settings_tab === 'emoji' ? ' nav-tab-active':'' ) . '">'.
                     esc_html__( 'Emoji', $this->plugin_name ) .
                     '</a>';
            $html .= '<a href="' . $action . '&tab=advanced" class="nav-tab' . ( $this->cbmm_settings_tab === 'advanced' ? ' nav-tab-active':'' ) . '">'.
                     esc_html__( 'Advanced', $this->plugin_name ) .
                     '</a>';
            $html .= '<a href="' . $action . '&tab=about" class="nav-tab' . ( $this->cbmm_settings_tab === 'about' ? ' nav-tab-active':'' ) . '">'.
                     esc_html__( 'About', $this->plugin_name ) .
                     '</a>';
            $html .= '</nav>';
            ob_start();
            if ( $this->cbmm_settings_tab == 'about' ) {
                $this->cbmm_about_page();
                $html .= ob_get_contents();
                ob_end_clean();
            } else {
                // settings_errors();
                $html .= '<form method="post" action="options.php">';
                $html .= '<div class="tab-content">';
                $html .= '<div class="cbmm-config-header">';
                switch( $this->cbmm_settings_tab ) {
                    default:
                        settings_fields( 'cbmm-settings' );
                        do_settings_sections( 'cbmm-settings' );
                        break;
                    case 'notify':
                        settings_fields( 'cbmm_settings_notify' );
                        do_settings_sections( 'cbmm_settings_notify' );
                        break;
                    case 'emoji':
                        settings_fields( 'cbmm_settings_emoji' );
                        do_settings_sections( 'cbmm_settings_emoji' );
                        break;
                    case 'advanced':
                        settings_fields( 'cbmm_settings_advanced' );
                        do_settings_sections( 'cbmm_settings_advanced' );
                        break;
                } // switch
                submit_button();
                $html .= ob_get_contents();
                ob_end_clean();
                $html .= '</form>';
            }
            $html .= '</div>';
            $html .= '</div>'; // tab-content
        $html .= '</div>'; // wrap
        //
		echo $html;
    }
    /**
     * Display about/support.
     *
	 * @since  1.0.0
     */
    public function cbmm_about_page() {
        echo '<div class="tab-content">';
        echo '<div class="cbmm-config-header">'.
             '<p>'  . esc_html__( 'Thank you for installing', $this->plugin_name ) .' Cloudbridge Mattermost!' . '</p>'.
             '<p>'  . esc_html__( 'This plugin will provide some integration between WordPress and', $this->plugin_name ) . ' <a href="https://mattermost.com" class="cbmm-ext-link" target="_blank"> Mattermost</a></p>'.
             '</div>';
        echo '<div class="cbmm-config-section">'.
             '<p>'  . '<img class="cbmm-wps-logo" alt="" src="' . plugin_dir_url( __FILE__ ) . 'img/webbplatsen_logo.png" />' .
                      esc_html__( 'Commercial support and customizations for this plugin is available from', $this->plugin_name ) .
                      ' <a class="cbmm-ext-link" href="https://webbplatsen.se" target="_blank">WebbPlatsen i Sverige AB</a> '.
                      esc_html__('in Stockholm, Sweden. We speak Swedish and English', $this->plugin_name ) . ' :-)' .
                      '<br/><br/>' .
                      esc_html__( 'The plugin is written by Joaquim Homrighausen and sponsored by WebbPlatsen i Sverige AB.', $this->plugin_name ) . '</p>' .
             '<p>'  . esc_html__( 'If you find this plugin useful, the author is happy to receive a donation, good review, or just a kind word.', $this->plugin_name ) . '</p>' .
             '<p>'  . esc_html__( 'If there is something you feel to be missing from this plugin, or if you have found a problem with the code or a feature, please do not hesitate to reach out to', $this->plugin_name ) .
                                  ' <a class="cbmm-ext-link" href="mailto:support@webbplatsen.se">support@webbplatsen.se</a>' . '</p>';
             '</div>';
        echo '</div>';
    }
    /**
     * Display settings.
     *
	 * @since  1.0.0
     */
    public function cbmm_settings() {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
        add_settings_section( 'cbmm-settings', '', false, 'cbmm-settings' );
          add_settings_field( 'cbmm-site-label', esc_html__( 'Site label', $this->plugin_name ), [$this, 'cbmm_setting_site_label'], 'cbmm-settings', 'cbmm-settings', ['label_for' => 'cbmm-site-label'] );
          add_settings_field( 'cbmm-mm-webhook', esc_html__( 'Webhook URL', $this->plugin_name ), [$this, 'cbmm_setting_webhook_url'], 'cbmm-settings', 'cbmm-settings', ['label_for' => 'cbmm-mm-webhook'] );
        add_settings_section( 'cbmm_section_other', esc_html__( 'Other settings', $this->plugin_name ), false, 'cbmm-settings' );
          add_settings_field( 'cbmm-force-locale-enus', esc_html__( 'Force locale en_US', $this->plugin_name ), [$this, 'cbmm_setting_force_locale_enus'], 'cbmm-settings', 'cbmm_section_other', ['label_for' => 'cbmm-force-locale-enus'] );
          add_settings_field( 'cbmm-settings-remove', esc_html__( 'Remove settings', $this->plugin_name ), [$this, 'cbmm_setting_remove'], 'cbmm-settings', 'cbmm_section_other', ['label_for' => 'cbmm-settings-remove'] );

        add_settings_section( 'cbmm_settings_notify', '', false, 'cbmm_settings_notify' );
          add_settings_field( 'cbmm-roles-notify', esc_html__( 'Successful login', $this->plugin_name ), [$this, 'cbmm_setting_roles_notify'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-roles-notify'] );
          add_settings_field( 'cbmm-roles-warn', esc_html__( 'Unsuccessful login', $this->plugin_name ), [$this, 'cbmm_setting_roles_warn'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-roles-warn'] );
          add_settings_field( 'cbmm-unknown-warn', '', [$this, 'cbmm_setting_unknown_notify'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-unknown-warn'] );
          add_settings_field( 'cbmm-roles-password-reset', esc_html__( 'Password reset', $this->plugin_name ), [$this, 'cbmm_setting_roles_password_reset'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-roles-password-reset'] );
          add_settings_field( 'cbmm-roles-password-skip-email', esc_html__( 'Password reset, skip e-mail', $this->plugin_name ), [$this, 'cbmm_setting_password_reset_skip_email'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-roles-password-skip-email'] );
          add_settings_field( 'cbmm-notify-activate-plugin', esc_html__( 'Plugin activated', $this->plugin_name ), [$this, 'cbmm_setting_notify_plugin_activate'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-notify-activate-plugin'] );
          add_settings_field( 'cbmm-notify-deactivate-plugin', esc_html__( 'Plugin deactivated', $this->plugin_name ), [$this, 'cbmm_setting_notify_plugin_deactivate'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-notify-deactivate-plugin'] );
          add_settings_field( 'cbmm-notify-uninstall-plugin', esc_html__( 'Plugin uninstalled', $this->plugin_name ), [$this, 'cbmm_setting_notify_plugin_uninstall'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-notify-uninstall-plugin'] );
          add_settings_field( 'cbmm-roles-user-add', esc_html__( 'New user added', $this->plugin_name ), [$this, 'cbmm_setting_roles_adduser'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-roles-user-add'] );
          add_settings_field( 'cbmm-roles-user-delete', esc_html__( 'User deleted', $this->plugin_name ), [$this, 'cbmm_setting_roles_deleteuser'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-roles-user-delete'] );
          add_settings_field( 'cbmm-link-admin', esc_html__( 'Link to WP Admin', $this->plugin_name ), [$this, 'cbmm_setting_link_admin'], 'cbmm_settings_notify', 'cbmm_settings_notify', ['label_for' => 'cbmm-link-admin'] );

        add_settings_section( 'cbmm_settings_emoji', '', [$this, 'cbmm_settings_emoji_callback'], 'cbmm_settings_emoji' );
          add_settings_field( 'cbmm-notice-emoji', esc_html__( 'Successful login', $this->plugin_name ), [$this, 'cbmm_setting_notice_emoji'], 'cbmm_settings_emoji', 'cbmm_settings_emoji', ['label_for' => 'cbmm-notice-emoji'] );
          add_settings_field( 'cbmm-warning-emoji', esc_html__( 'Failed login', $this->plugin_name ), [$this, 'cbmm_setting_warning_emoji'], 'cbmm_settings_emoji', 'cbmm_settings_emoji', ['label_for' => 'cbmm-warning-emoji'] );
          add_settings_field( 'cbmm-link-emoji', esc_html__( 'Admin link', $this->plugin_name ), [$this, 'cbmm_setting_link_emoji'], 'cbmm_settings_emoji', 'cbmm_settings_emoji', ['label_for' => 'cbmm-link-emoji'] );
          add_settings_field( 'cbmm-bell-emoji', esc_html__( 'General notice', $this->plugin_name ), [$this, 'cbmm_setting_bell_emoji'], 'cbmm_settings_emoji', 'cbmm_settings_emoji', ['label_for' => 'cbmm-bell-emoji'] );

        add_settings_section( 'cbmm_settings_advanced', '', [$this, 'cbmm_settings_advanced_callback'], 'cbmm_settings_advanced' );
          add_settings_field( 'cbmm-mm-username', esc_html__( 'Webhook username', $this->plugin_name ), [$this, 'cbmm_setting_mm_username'], 'cbmm_settings_advanced', 'cbmm_settings_advanced', ['label_for' => 'cbmm-mm-username'] );
          add_settings_field( 'cbmm-mm-channel', esc_html__( 'Webhook channel', $this->plugin_name ), [$this, 'cbmm_setting_mm_channel'], 'cbmm_settings_advanced', 'cbmm_settings_advanced', ['label_for' => 'cbmm-mm-channel'] );
          add_settings_field( 'cbmm-mm-mention', esc_html__( 'Additional @mention', $this->plugin_name ), [$this, 'cbmm_setting_mm_mention'], 'cbmm_settings_advanced', 'cbmm_settings_advanced', ['label_for' => 'cbmm-mm-mention'] );

        register_setting( 'cbmm-settings', 'cbmm-site-label', ['type' => 'string', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_site_label']] );
        register_setting( 'cbmm-settings', 'cbmm-mm-webhook', ['type' => 'string', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_webhook']] );
        register_setting( 'cbmm-settings', 'cbmm-force-locale-enus' );
        register_setting( 'cbmm-settings', 'cbmm-settings-remove' );

        register_setting( 'cbmm_settings_notify', 'cbmm-roles-notify', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_roles']] );
        register_setting( 'cbmm_settings_notify', 'cbmm-roles-warn', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_roles']] );
        register_setting( 'cbmm_settings_notify', 'cbmm-roles-password-reset', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_roles']] );
        register_setting( 'cbmm_settings_notify', 'cbmm-roles-password-skip-email', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_roles']] );
        register_setting( 'cbmm_settings_notify', 'cbmm-unknown-warn' );
        register_setting( 'cbmm_settings_notify', 'cbmm-notify-activate-plugin' );
        register_setting( 'cbmm_settings_notify', 'cbmm-notify-deactivate-plugin' );
        register_setting( 'cbmm_settings_notify', 'cbmm-notify-uninstall-plugin' );
        register_setting( 'cbmm_settings_notify', 'cbmm-roles-user-add', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_roles']] );
        register_setting( 'cbmm_settings_notify', 'cbmm-roles-user-delete', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_roles']] );
        register_setting( 'cbmm_settings_notify', 'cbmm-link-admin' );

        register_setting( 'cbmm_settings_emoji', 'cbmm-notice-emoji', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_emoji']] );
        register_setting( 'cbmm_settings_emoji', 'cbmm-warning-emoji', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_emoji']] );
        register_setting( 'cbmm_settings_emoji', 'cbmm-link-emoji', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_emoji']] );
        register_setting( 'cbmm_settings_emoji', 'cbmm-bell-emoji', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_emoji']] );

        register_setting( 'cbmm_settings_advanced', 'cbmm-mm-username', ['type' => 'string', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_advanced']] );
        register_setting( 'cbmm_settings_advanced', 'cbmm-mm-channel', ['type' => 'string', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_advanced']] );
        register_setting( 'cbmm_settings_advanced', 'cbmm-mm-mention', ['type' => 'string', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_advanced']] );
    }

    /**
     * Sanitize input.
     *
     * Basic cleaning/checking of user input. Not much to do really.
     *
	 * @since  1.0.0
     */
    public function cbmm_setting_sanitize_site_label( $input ) {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
        if ( function_exists( 'mb_substr' ) ) {
            return( mb_substr( sanitize_text_field( $input ), 0, 200 ) );
        }
        return( substr( sanitize_text_field( $input ), 0, 200 ) );
    }
    public function cbmm_setting_sanitize_webhook( $input ) {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
        return( esc_url_raw( $input, ['https','http'] ) );
    }
    public function cbmm_setting_sanitize_roles( $input ) {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
        $available_roles = $this->cbmm_get_wp_roles( false );
        $return_val = array();
        if ( is_array( $input ) ) {
            $roles_array = array_keys( $available_roles );
            foreach( $input as $role ) {
                if ( in_array( $role, $roles_array ) ) {
                    // We know $role is clean since it matches
                    $return_val[] = $role;
                }
            }
        }
        return( json_encode( $return_val ) );
    }
    public function cbmm_setting_sanitize_emoji( $input ) {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
        if ( function_exists( 'mb_substr' ) ) {
            return( mb_substr( sanitize_text_field( $input ), 0, 30 ) );
        }
        return( substr( sanitize_text_field( $input ), 0, 30 ) );
    }
    public function cbmm_setting_sanitize_advanced( $input ) {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
        if ( function_exists( 'mb_substr' ) ) {
            return( mb_substr( sanitize_text_field( $input ), 0, 200 ) );
        }
        return( substr( sanitize_text_field( $input ), 0, 200 ) );
    }
    /**
     * Output input fields.
     *
	 * @since  1.0.0
     */
    public function cbmm_setting_site_label() {
        $option_val = $this->cbmm_get_option( 'cbmm-site-label', false );
        echo '<input type="text" size="60" maxlength="200" id="cbmm-site-label" name="cbmm-site-label" value="' . esc_attr( $option_val ). '" />';
        echo '<p class="description">' . esc_html__( 'The site name to use for the webhook, defaults to your site name if left empty.', $this->plugin_name ) . '</p>';
    }
    public function cbmm_setting_webhook_url() {
        $option_val = $this->cbmm_get_option( 'cbmm-mm-webhook', false );
        echo '<input type="text" size="60" maxlength="200" id="cbmm-mm-webhook" name="cbmm-mm-webhook" value="' . esc_attr( $option_val ). '" />';
        echo '<p class="description">' .
             esc_html__( 'The URL for the Mattermost incoming webhook', $this->plugin_name ) .
             '. ' .
             '<a href="https://docs.mattermost.com/developer/webhooks-incoming.html" target="_blank">' . esc_html__( 'Please check the documentation for details', $this->plugin_name ) . '</a>' .
             '</p>';
    }
    public function cbmm_setting_roles_notify($args) {
        $option_val = $this->cbmm_get_option( 'cbmm-roles-notify', false );
        $available_roles = $this->cbmm_get_wp_roles( false );
        if ( ! empty( $option_val ) ) {
            $checkboxes = @ json_decode( $option_val, true, 2 );
            if ( ! is_array( $checkboxes ) ) {
                $checkboxes = array();
            }
        } else {
            $checkboxes = array();
        }
        foreach( $available_roles as $k => $v ) {
            echo '<div class="cbmm-role-option">';
            echo '<input type="checkbox" name="cbmm-roles-notify[]" id="cbmm-roles-notify[]" value="' . esc_attr( $k ) . '" ' . ( in_array( $k, $checkboxes ) ? 'checked="checked" ':'' ) . '/>';
            echo '<label for="cbmm-roles-notify[]">'. esc_html__( $v ) . '</label> ';
            echo '</div>';
        }
    }
    public function cbmm_setting_roles_warn() {
        $option_val = $this->cbmm_get_option( 'cbmm-roles-warn', false );
        $available_roles = $this->cbmm_get_wp_roles( false );
        if ( ! empty( $option_val ) ) {
            $checkboxes = @ json_decode( $option_val, true, 2 );
            if ( ! is_array( $checkboxes ) ) {
                $checkboxes = array();
            }
        } else {
            $checkboxes = array();
        }
        foreach( $available_roles as $k => $v ) {
            echo '<div class="cbmm-role-option">';
            echo '<input type="checkbox" name="cbmm-roles-warn[]" id="cbmm-roles-warn[]" value="' . esc_attr( $k ) . '" ' . ( in_array( $k, $checkboxes ) ? 'checked="checked" ':'' ) . '/>';
            echo '<label for="cbmm-roles-warn[]">'. esc_html__( $v ) . '</label> ';
            echo '</div>';
        }
    }
    public function cbmm_setting_unknown_notify() {
        $option_val = $this->cbmm_get_option( 'cbmm-unknown-warn', false );
        echo '<div class="cbmm-role-option">';
        echo '<input type="checkbox" name="cbmm-unknown-warn" id="cbmm-unknown-warn" value="1" ' . ( checked( $option_val, 1, false ) ) . '/>';
        echo '<label for="cbmm-unknown-warn">'. esc_html__( 'Unknown users', $this->plugin_name ) . '</label> ';
        echo '</div>';
    }
    // @since 1.1.0
    public function cbmm_setting_roles_password_reset() {
        $option_val = $this->cbmm_get_option( 'cbmm-roles-password-reset', false );
        $available_roles = $this->cbmm_get_wp_roles( false );
        if ( ! empty( $option_val ) ) {
            $checkboxes = @ json_decode( $option_val, true, 2 );
            if ( ! is_array( $checkboxes ) ) {
                $checkboxes = array();
            }
        } else {
            $checkboxes = array();
        }
        foreach( $available_roles as $k => $v ) {
            echo '<div class="cbmm-role-option">';
            echo '<input type="checkbox" name="cbmm-roles-password-reset[]" id="cbmm-roles-password-reset[]" value="' . esc_attr( $k ) . '" ' . ( in_array( $k, $checkboxes ) ? 'checked="checked" ':'' ) . '/>';
            echo '<label for="cbmm-roles-password-reset[]">'. esc_html__( $v ) . '</label> ';
            echo '</div>';
        }
    }
    // @since 1.1.0
    public function cbmm_setting_password_reset_skip_email() {
        $option_val = $this->cbmm_get_option( 'cbmm-roles-password-skip-email', false );
        $available_roles = $this->cbmm_get_wp_roles( false );
        if ( ! empty( $option_val ) ) {
            $checkboxes = @ json_decode( $option_val, true, 2 );
            if ( ! is_array( $checkboxes ) ) {
                $checkboxes = array();
            }
        } else {
            $checkboxes = array();
        }
        foreach( $available_roles as $k => $v ) {
            echo '<div class="cbmm-role-option">';
            echo '<input type="checkbox" name="cbmm-roles-password-skip-email[]" id="cbmm-roles-password-skip-email[]" value="' . esc_attr( $k ) . '" ' . ( in_array( $k, $checkboxes ) ? 'checked="checked" ':'' ) . '/>';
            echo '<label for="cbmm-roles-password-skip-email[]">'. esc_html__( $v ) . '</label> ';
            echo '</div>';
        }
    }
    // @since 1.1.0
    public function cbmm_setting_notify_plugin_activate() {
        $option_val = $this->cbmm_get_option( 'cbmm-notify-activate-plugin', false );
        echo '<div class="cbmm-role-option">';
        echo '<input type="checkbox" name="cbmm-notify-activate-plugin" id="cbmm-notify-activate-plugin" value="1" ' . ( checked( $option_val, 1, false ) ) . '/>';
        echo '<label for="cbmm-notify-activate-plugin">'. esc_html__( 'Send notification when plugin is activated.', $this->plugin_name ) . '</label> ';
        echo '</div>';
    }
    // @since 1.1.0
    public function cbmm_setting_notify_plugin_deactivate() {
        $option_val = $this->cbmm_get_option( 'cbmm-notify-deactivate-plugin', false );
        echo '<div class="cbmm-role-option">';
        echo '<input type="checkbox" name="cbmm-notify-deactivate-plugin" id="cbmm-notify-deactivate-plugin" value="1" ' . ( checked( $option_val, 1, false ) ) . '/>';
        echo '<label for="cbmm-notify-deactivate-plugin">'. esc_html__( 'Send notification when plugin is deactivated.', $this->plugin_name ) . '</label> ';
        echo '</div>';
    }
    // @since 1.1.0
    public function cbmm_setting_notify_plugin_uninstall() {
        $option_val = $this->cbmm_get_option( 'cbmm-notify-uninstall-plugin', false );
        echo '<div class="cbmm-role-option">';
        echo '<input type="checkbox" name="cbmm-notify-uninstall-plugin" id="cbmm-notify-uninstall-plugin" value="1" ' . ( checked( $option_val, 1, false ) ) . '/>';
        echo '<label for="cbmm-notify-uninstall-plugin">'. esc_html__( 'Send notification when plugin is uninstalled.', $this->plugin_name ) . '</label> ';
        echo '</div>';
    }
    // @since 1.1.0
    public function cbmm_setting_roles_adduser() {
        $option_val = $this->cbmm_get_option( 'cbmm-roles-user-add', false );
        $available_roles = $this->cbmm_get_wp_roles( false );
        if ( ! empty( $option_val ) ) {
            $checkboxes = @ json_decode( $option_val, true, 2 );
            if ( ! is_array( $checkboxes ) ) {
                $checkboxes = array();
            }
        } else {
            $checkboxes = array();
        }
        foreach( $available_roles as $k => $v ) {
            echo '<div class="cbmm-role-option">';
            echo '<input type="checkbox" name="cbmm-roles-user-add[]" id="cbmm-roles-user-add[]" value="' . esc_attr( $k ) . '" ' . ( in_array( $k, $checkboxes ) ? 'checked="checked" ':'' ) . '/>';
            echo '<label for="cbmm-roles-user-add[]">'. esc_html__( $v ) . '</label> ';
            echo '</div>';
        }
    }
    // @since 1.1.0
    public function cbmm_setting_roles_deleteuser() {
        $option_val = $this->cbmm_get_option( 'cbmm-roles-user-delete', false );
        $available_roles = $this->cbmm_get_wp_roles( false );
        if ( ! empty( $option_val ) ) {
            $checkboxes = @ json_decode( $option_val, true, 2 );
            if ( ! is_array( $checkboxes ) ) {
                $checkboxes = array();
            }
        } else {
            $checkboxes = array();
        }
        foreach( $available_roles as $k => $v ) {
            echo '<div class="cbmm-role-option">';
            echo '<input type="checkbox" name="cbmm-roles-user-delete[]" id="cbmm-roles-user-delete[]" value="' . esc_attr( $k ) . '" ' . ( in_array( $k, $checkboxes ) ? 'checked="checked" ':'' ) . '/>';
            echo '<label for="cbmm-roles-user-delete[]">'. esc_html__( $v ) . '</label> ';
            echo '</div>';
        }
    }
    public function cbmm_setting_link_admin() {
        $option_val = $this->cbmm_get_option( 'cbmm-link-admin', false );
        echo '<div class="cbmm-role-option">';
        echo '<input type="checkbox" name="cbmm-link-admin" id="cbmm-link-admin" value="1" ' . ( checked( $option_val, 1, false ) ) . '/>';
        echo '<label for="cbmm-link-admin">'. esc_html__( 'Include link to WordPress Admin in notifications.', $this->plugin_name ) . '</label> ';
        echo '</div>';
    }
    // @since 1.1.0
    public function cbmm_setting_force_locale_enus() {
        $option_val = $this->cbmm_get_option( 'cbmm-force-locale-enus', false );
        echo '<div class="cbmm-role-option">';
        echo '<input type="checkbox" name="cbmm-force-locale-enus" id="cbmm-force-locale-enus" value="1" ' . ( checked( $option_val, 1, false ) ) . '/>';
        echo '<label for="cbmm-force-locale-enus">'. esc_html__( 'Force notifications to be sent in en_US locale (English).', $this->plugin_name ) . '</label> ';
        echo '</div>';
    }
    public function cbmm_setting_remove() {
        $option_val = $this->cbmm_get_option( 'cbmm-settings-remove', false );
        echo '<div class="cbmm-role-option">';
        echo '<input type="checkbox" name="cbmm-settings-remove" id="cbmm-settings-remove" value="1" ' . ( checked( $option_val, 1, false ) ) . '/>';
        echo '<label for="cbmm-settings-remove">'. esc_html__( 'Remove all CBMM plugin settings and data when plugin is uninstalled.', $this->plugin_name ) . '</label> ';
        echo '</div>';
    }
    public function cbmm_settings_emoji_callback() {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
        echo '<p>'.
             esc_html__( 'Emojis (markdown) can be configured for notices, warnings, and the admin link. ' .
                         'If left empty here, the defaults will be used.', $this->plugin_name ).
             '<br/>'.
             esc_html__( 'You can find an emoji cheat sheet here', $this->plugin_name ).
             ': ' .
             '<a href="' . 'https://www.webfx.com/tools/emoji-cheat-sheet/' .'" target="_blank">'.
             'https://www.webfx.com/tools/emoji-cheat-sheet/' .
             '</a></p>';
    }
    public function cbmm_setting_notice_emoji() {
        $option_val = $this->cbmm_get_option( 'cbmm-notice-emoji', false );
        echo '<input type="text" size="30" maxlength="30" id="cbmm-notice-emoji" name="cbmm-notice-emoji" value="' . esc_attr( $option_val ). '" />';
        echo '<p class="description">' . esc_html__( 'Default emoji markdown is', $this->plugin_name ) .' ' . CBMM_EMOJI_DEFAULT_NOTICE . '</p>';
    }
    public function cbmm_setting_warning_emoji() {
        $option_val = $this->cbmm_get_option( 'cbmm-warning-emoji', false );
        echo '<input type="text" size="30" maxlength="30" id="cbmm-warning-emoji" name="cbmm-warning-emoji" value="' . esc_attr( $option_val ). '" />';
        echo '<p class="description">' . esc_html__( 'Default emoji markdown is', $this->plugin_name ) .' ' . CBMM_EMOJI_DEFAULT_WARNING . '</p>';
    }
    public function cbmm_setting_link_emoji() {
        $option_val = $this->cbmm_get_option( 'cbmm-link-emoji', false );
        echo '<input type="text" size="30" maxlength="30" id="cbmm-link-emoji" name="cbmm-link-emoji" value="' . esc_attr( $option_val ). '" />';
        echo '<p class="description">' . esc_html__( 'Default emoji markdown is', $this->plugin_name ) .' ' . CBMM_EMOJI_DEFAULT_LINK . '</p>';
    }
    public function cbmm_setting_bell_emoji() {
        $option_val = $this->cbmm_get_option( 'cbmm-bell-emoji', false );
        echo '<input type="text" size="30" maxlength="30" id="cbmm-bell-emoji" name="cbmm-bell-emoji" value="' . esc_attr( $option_val ). '" />';
        echo '<p class="description">' . esc_html__( 'Default emoji markdown is', $this->plugin_name ) .' ' . CBMM_EMOJI_DEFAULT_BELL . '</p>';
    }
    public function cbmm_settings_advanced_callback() {
		if ( ! is_admin( ) || ! is_user_logged_in() || ! current_user_can( 'administrator' ) )  {
			return;
		}
        echo '<p>'.
             esc_html__( 'These settings allow you to modify the content sent to Mattermost. Use of these ' .
                         'settings depend on how the webhook has been created/configured in Mattermost. Some ' .
                         'of these settings may cause a notification to be rejected by Mattermost.', $this->plugin_name ).
             '</p>';
    }
    public function cbmm_setting_mm_username() {
        $option_val = $this->cbmm_get_option( 'cbmm-mm-username', false );
        echo '<input type="text" size="60" maxlength="200" id="cbmm-mm-username" name="cbmm-mm-username" value="' . esc_attr( $option_val ). '" />';
        echo '<p class="description">' . esc_html__( 'The username to use for the webhook, this should normally be left empty.', $this->plugin_name ) . '</p>';
    }
    public function cbmm_setting_mm_channel() {
        $option_val = $this->cbmm_get_option( 'cbmm-mm-channel', false );
        echo '<input type="text" size="60" maxlength="200" id="cbmm-mm-channel" name="cbmm-mm-channel" value="' . esc_attr( $option_val ). '" />';
        echo '<p class="description">' . esc_html__( 'The channel to use for the webhook, this should normally be left empty.', $this->plugin_name ) . '</p>';
    }
    public function cbmm_setting_mm_mention() {
        $option_val = $this->cbmm_get_option( 'cbmm-mm-mention', false );
        echo '<input type="text" size="60" maxlength="200" id="cbmm-mm-mention" name="cbmm-mm-mention" value="' . esc_attr( $option_val ). '" />';
        echo '<p class="description">' . esc_html__( 'Additional @mention to include with the notification, this should normally be left empty.', $this->plugin_name ) . '</p>';
    }

    /**
     * Send alert to Mattermost.
     *
     * Set-up request payload with some optional data based on configuration.
     * Uses wp_remote_post() to deliver the final payload to Mattermost.
     *
     * @since  1.0.0
     * @param  string $alert_message The error message.
     * @return boolean true=All good, false=Webhook URL has not been configured
     */
    protected function cbmm_alert_send( string $alert_message ) : bool {
        if ( empty( $this->cbmm_mm_webhook ) ) {
            error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): Webhook URL has not been configured, not sending alert' );
            return( false );
        }
        // Possibly add additional mention(s)
        if ( ! empty( $this->cbmm_mm_mention ) ) {
            $alert_message .= "\n" . $this->cbmm_mm_mention;
        }
        // Standard payload
        $mattermost_post = [
            'text'     => $alert_message,
        ];
        // Add username
        if ( ! empty( $this->cbmm_mm_username ) ) {
            $mattermost_post['username'] = $this->cbmm_mm_username;
        }
        // Add channel
        if ( ! empty( $this->cbmm_mm_channel ) ) {
            $mattermost_post['channel'] = $this->cbmm_mm_channel;
        }
        // https://developer.wordpress.org/reference/functions/wp_remote_post/
        $data = wp_remote_post(
                    $this->cbmm_mm_webhook,
                    array(
                        'headers'     => [ 'Content-Type' => 'application/json; charset=utf-8' ],
                        'method'      => 'POST',
                        'timeout'     => 45,
                        'body'        => json_encode( $mattermost_post ),
                        'data_format' => 'body',
                    )
                );
        return( true );
    }

    /**
     * Get string for message containing user information.
     *
     * Formats the contents of WP_User $user object into a suitable string.
     *
     * @since 1.1.0
     * @param object $user WP_User
     * @return string String with "user display" suitable for the notification
     */
    protected function cbmm_get_message_user_display( \WP_User $user ) : string {
        $name = trim( $user->display_name );
        if ( empty( $name ) ) {
            $name = trim( $user->user_firstname );
            $name .= ( empty( $name ) ? '': ' ') . trim( $user->user_lastname );
        }
        $user_login = trim( $user->user_login );
        if ( $name != $user_login ) {
            $name = ( empty( $name ) ? '`' . $user_login . '`' : $name . ' (`' . $user_login . '`)' );
        } else {
            $name = '`' . $name . '`';
        }
        return( $name );
    }

    /**
     * Build context based alert message.
     *
     * @since 1.0.0
     * @param string $username Username as entered when logging in.
     * @param mixed $context Either WP_User or WP_Error.
     * @param int $alert_type Type of notification.
     * @return mixed String with alert message or false on error.
     */
    protected function cbmm_make_alert_message( string $username, $context, int $alert_type ) {
        $alert_message = '';
        // Fetch remote IP if set
        $remote_ip = $_SERVER['REMOTE_ADDR'];
        if ( ! empty( $remote_ip ) ) {
            $remote_ip = ' ' . $this->cbmm_get_lang_string( 'from' ) . ' ' . $remote_ip;
        }
        // Figure out path to take
        switch( $alert_type ) {
            default: // Notification
                if ( ! is_a( $context, 'WP_User' ) ) {
                    error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): Unknown context "' . get_class( $context ) . '" for alert_type (' . $alert_type . ')' );
                    return( false );
                }
                $name = $this->cbmm_get_message_user_display( $context );
                $alert_message = $this->cbmm_notice_emoji . ' ' . $this->cbmm_get_lang_string( 'Login by' ) .
                                 ' ' . $name . $remote_ip . ' ' . $this->cbmm_get_lang_string( 'on' ) . ' ' . '`' . $this->cbmm_site_label . '`' . "\n";
                break;
            case CBMM_ALERT_FAILURE:
                if ( ! is_a( $context, 'WP_Error' ) ) {
                    error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): Unknown context "' . get_class( $context ) . '" for alert_type (' . $alert_type . ')' );
                    return( false );
                }
                $alert_code = key( $context->errors );
                $alert_message = $this->cbmm_warning_emoji . ' ' .
                                 $this->cbmm_get_lang_string( 'Failed login' ) . $remote_ip . ' ' .
                                 $this->cbmm_get_lang_string( 'on' ) . ' `' . $this->cbmm_site_label . '`: **';
                switch( $alert_code ) {
                    case 'invalid_username':
                        $alert_message .= $this->cbmm_get_lang_string( 'Invalid username' );
                        break;
                    case 'incorrect_password':
                        $alert_message .= $this->cbmm_get_lang_string( 'Incorrect password' );
                        break;
                    default:
                        $alert_message .= $this->cbmm_get_lang_string( 'Unknown error' ) . ' "' . $alert_code . '"';
                        break;
                } // switch
                $alert_message .= '** (' . $this->cbmm_get_lang_string( 'username' ) . ' `' . $username . '`).' . "\n";
                break;
            case CBMM_ALERT_RESET_PASSWORD:// @since 1.1.0
                if ( ! is_a( $context, 'WP_User' ) ) {
                    error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): Unknown context "' . get_class( $context ) . '" for alert_type (' . $alert_type . ')' );
                    return( false );
                }
                $name = $this->cbmm_get_message_user_display( $context );
                $alert_message = $this->cbmm_bell_emoji . ' ' . $this->cbmm_get_lang_string( 'Password reset by' ) .
                                 ' ' . $name . $remote_ip . ' ' . __( 'on', $this->plugin_name ) . ' ' . '`' . $this->cbmm_site_label . '`' . "\n";
                break;
            case CBMM_ALERT_PLUGIN_ACTIVATE:                    // @since 1.1.0
            case CBMM_ALERT_PLUGIN_DEACTIVATE:                  // @since 1.1.0
            case CBMM_ALERT_PLUGIN_UNINSTALL:                   // @since 1.1.0
                if ( ! is_a( $context, 'WP_User' ) ) {
                    error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): Unknown context "' . get_class( $context ) . '" for alert_type (' . $alert_type . ')' );
                    return( false );
                }
                switch( $alert_type ) {
                    case CBMM_ALERT_PLUGIN_ACTIVATE:
                        $action_msg = $this->cbmm_get_lang_string( 'Plugin activated by' );
                        break;
                    case CBMM_ALERT_PLUGIN_DEACTIVATE:
                        $action_msg = $this->cbmm_get_lang_string( 'Plugin deactivated by' );
                        break;
                    case CBMM_ALERT_PLUGIN_UNINSTALL:
                        $action_msg = $this->cbmm_get_lang_string( 'Plugin uninstalled by' );
                        break;
                }
                $name = $this->cbmm_get_message_user_display( $context );
                $alert_message = $this->cbmm_bell_emoji . ' **' . $this->cbmm_action_plugin . '** ' . $action_msg .
                                 ' ' . $name . $remote_ip . ' ' . $this->cbmm_get_lang_string( 'on' ) . ' ' . '`' . $this->cbmm_site_label . '`' . "\n";
                break;
            case CBMM_ALERT_USER_ADD:                           // @since 1.1.0
            case CBMM_ALERT_USER_DELETE:                        // @since 1.1.0
                if ( ! is_a( $context, 'WP_User' ) ) {
                    error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): Unknown context "' . get_class( $context ) . '" for alert_type (' . $alert_type . ')' );
                    return( false );
                }
                // From admin interface, create some additional information
                $admin_string = '';
                if ( is_admin() ) {
                    $admin_string = ' ' . $this->cbmm_get_lang_string( 'by' ) . ' ';
                    if ( is_user_logged_in() ) {
                        // Get current user
                        $admin_user = wp_get_current_user();
                        $admin_string .= $this->cbmm_get_message_user_display( $admin_user );
                    } else {
                        // This shouldn't be possible
                        $admin_string .= $this->cbmm_get_lang_string( 'unknown' );
                    }
                }
                $admin_string .= $remote_ip;
                // Choose message
                if ( $alert_type == CBMM_ALERT_USER_ADD ) {
                    $action_msg = $this->cbmm_get_lang_string( 'User added/edited' );
                } else {
                    $action_msg = $this->cbmm_get_lang_string( 'User deleted' );
                }
                // Show roles (we notify for) of the newly created user
                $new_user_roles = $this->cbmm_roles_merge( $context->get_role_caps(), $this->cbmm_mm_roles_user_add );
                // On with the show
                $name = $this->cbmm_get_message_user_display( $context );
                $alert_message = $this->cbmm_bell_emoji . ' **' . $action_msg .
                                 ':** ' . $name . $new_user_roles . $admin_string . ' ' . $this->cbmm_get_lang_string( 'on' ) . ' ' . '`' . $this->cbmm_site_label . '`' . "\n";
                break;
        } // switch
        //Add link to admin for site, optional
        if ( ! empty( $alert_message ) && $this->cbmm_link_admin == '1' ) {
            $alert_message .= '[' . $this->cbmm_link_emoji . ' WordPress admin](' . get_admin_url() . ')';
        }
        return( $alert_message );
    }

    /**
     * See if a user's roles/caps have been configured for notifications.
     *
     * Compares all configured notification roles/caps with that of the roles/
     * caps of a user. If found, returns true, otherwise false.
     *
     * @since 1.0.0
     * @param array $roles WordPress roles/caps of user in question.
     * @param string $notify_rules JSON string with configured roles.
     * @return boolean true=Notify, false=Don't notify
     */
    protected function cbmm_role_is_active( array $roles, string $notify_roles ) : bool {
        $notify_array = @ json_decode( $notify_roles, true, 2 );
        if ( ! is_array( $notify_array ) || empty( $notify_array ) ) {
            return( false );
        }
        // Lookup our selected notification roles. We could walk the other way
        // too, but we're likely to have less configured roles/caps than what
        // is available. So maybe this will save an iteration or two :-)
        foreach( $notify_array as $role ) {
            if ( in_array( $role, $roles ) && $roles[$role] ) {
                return( true );
            }
        }
        return( false );
    }

    /**
     * Create human readable role names from two lists.
     *
     * Extracts the human readable role names from a merged version of roles we
     * know about and roles present for a user.
     *
     * @since 1.1.0
     * @param array $roles WordPress roles/caps of user in question.
     * @param string $notify_rules JSON string with configured roles.
     * @return string List of translated role names like ' [Administratör,Prenumerant]'
     */
    protected function cbmm_roles_merge( array $roles, string $notify_roles ) : string {
        $notify_array = @ json_decode( $notify_roles, true, 2 );
        if ( ! is_array( $notify_array ) || empty( $notify_array ) ) {
            return( false );
        }
        $new_roles = array();
        // Lookup our selected notification roles. We could walk the other way
        // too, but we're likely to have less configured roles/caps than what
        // is available. So maybe this will save an iteration or two :-)
        foreach( $notify_array as $role ) {
            if ( in_array( $role, $roles ) && $roles[$role] ) {
                $new_roles[] = $role;
            }
        }
        // Do some i18n
        $wp_roles = $this->cbmm_get_wp_roles( $this->cbmm_force_locale_ENUS );
        for ( $c = 0; $c < count( $new_roles ); $c++ ) {
            if ( ! empty( $wp_roles[ $new_roles[$c] ] ) ) {
                $new_roles[$c] = $wp_roles[ $new_roles[$c] ];
            }
        }
        return( ' [' . implode( ',', $new_roles ) . ']' );
    }

    /**
     * Send alert when user with configured role(s) login.
     *
     * Send notification ("success") when a user with a role matching the
     * configured notification roles logs in.
     *
     * @since 1.0.0
     * @param string $username The username as entered when logging in.
     * @param object $user, WP_User
     */
    public function cbmm_alert_login( string $username, object $user ) {
        if ( ! is_a( $user, 'WP_User' ) ) {
            error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): No user information?' );
            error_log( get_class ($user));
            return;
        }
        // Fetch user's roles/caps from WordPress for currently logged in user
        $role_caps = $user->get_role_caps();
        // Possibly notify
        if ( $this->cbmm_role_is_active( $role_caps, $this->cbmm_mm_roles_notify ) ) {
            $alert_message = $this->cbmm_make_alert_message( $username, $user, CBMM_ALERT_SUCCESS );
            if ( ! empty( $alert_message ) ) {
                $this->cbmm_alert_send( $alert_message );
            }
        }
    }

    /**
     * Send alert of login failure.
     *
     * @since 1.0.0
     * @param string $username The username as entered when logging in.
     * @param object $error, WP_Error
     */
    public function cbmm_alert_failed_login( string $username, object $error ) {
        if ( ! is_a( $error, 'WP_Error' ) ) {
            error_log( basename( __FILE__ ) . ' (' . __FUNCTION__ . '): No error information?' );
            error_log( get_class ($error));
            return;
        }
        $error_code = key( $error->errors );
        if ( $error_code == 'invalid_username' && empty( $this->cbmm_mm_unknown_warn ) ) {
            // We're configured to not notify about unknown users
            return;
        } elseif ( $error_code == 'incorrect_password' ) {
            // We can get user info for this, so let's see if we should notify
            $failed_user = new \WP_User( 0, $username );
            $role_caps = $failed_user->get_role_caps();
            if ( is_array( $role_caps ) ) {
                if ( ! $this->cbmm_role_is_active( $role_caps, $this->cbmm_mm_roles_warn ) ) {
                    // We're not configured to notify for this user role/cap, bail
                    return;
                }
            }
        }
        $alert_message = $this->cbmm_make_alert_message( $username, $error, CBMM_ALERT_FAILURE );
        if ( ! empty( $alert_message ) ) {
            $this->cbmm_alert_send( $alert_message );
        }
    }

    /**
     * Hook for password reset.
     *
     * Handle password reset and optionally prevent e-mail from going out.
     *
     * @since 1.1.0
     */
    public function cbmm_password_reset_hook( \WP_User $user, string $new_password ) {
        // Fetch user's roles/caps from WordPress for currently logged in user
        $role_caps = $user->get_role_caps();
        // Possibly notify
        if ( $this->cbmm_role_is_active( $role_caps, $this->cbmm_mm_roles_password_reset ) ) {
            $notify_message = $this->cbmm_make_alert_message( $user->user_login, $user, CBMM_ALERT_RESET_PASSWORD );
            if ( ! empty( $notify_message ) ) {
                $this->cbmm_alert_send( $notify_message );
            }
        }
        // Possibly block e-mail
        if ( ! $this->cbmm_role_is_active( $role_caps, $this->cbmm_mm_roles_password_reset_skip_email ) ) {
            // E-mail should not be blocked for this role, let WordPress do its thing
            wp_password_change_notification();
        }
    }

    /**
     * Get plugin info for activation/deactivation.
     *
     * Retrieve plugin information about plugin being activated, deactivated,
     * and deleted/uninstalled. Called by the three hooks.
     *
     * @since 1.1.0
     * @param string $plugin The relative path and the filename of the main plugin file
     * @param boolean $network
     * $return string Plugin information
     */
    protected function cbmm_get_plugin_info( string $plugin, bool $network ) : string {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! function_exists( 'get_mu_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_base = basename( $plugin );
        $plugin_data = get_plugins( '/' . dirname( $plugin ) );
        if ( empty( $plugin_data[$plugin_base] ) ) {
            // I have no idea why get_mu_plugins() does not support the retrieval
            // of ONE MU plugin, but there you have it ...
            $plugin_data = get_mu_plugins();
        }
        if ( ! empty( $plugin_data[$plugin_base] ) ) {
            $plugin_data = $plugin_data[$plugin_base];
        } else {
            $plugin_data = array( 'Name' => $plugin );
        }
        if ( ! empty( $plugin_data['Name'] ) ) {
            $plugin_info = $plugin_data['Name'];
        } else {
            $plugin_info = $plugin;
        }
        if ( ! empty( $plugin_data['Version'] ) ) {
            $plugin_info .= ( empty( $plugin_info ) ? '' : ' ' ) . $plugin_data['Version'];
        }
        return( $plugin_info );
    }
    /**
     * Hook for plugin activation.
     *
     * Handle plugin activation notification.
     *
     * @since 1.1.0
     * @param string $plugin The relative path and the filename of the main plugin file
     * @param boolean $network
     */
    public function cbmm_handle_activate_plugin( string $plugin, bool $network ) {
        // Get plugin information and pass it on
        $this->cbmm_action_plugin = $this->cbmm_get_plugin_info( $plugin, $network );
        // Get current user
        $user = wp_get_current_user();
        // Create and send message
        $notify_message = $this->cbmm_make_alert_message( $user->user_login, $user, CBMM_ALERT_PLUGIN_ACTIVATE );
        if ( ! empty( $notify_message ) ) {
            $this->cbmm_alert_send( $notify_message );
        }
    }
    /**
     * Hook for plugin deactivation.
     *
     * Handle plugin deactivation notification.
     *
     * @since 1.1.0
     * @param string $plugin The relative path and the filename of the main plugin file
     * @param boolean $network
     */
    public function cbmm_handle_deactivate_plugin( string $plugin, bool $network ) {
        // Get plugin information and pass it on
        $this->cbmm_action_plugin = $this->cbmm_get_plugin_info( $plugin, $network );
        // Get current user
        $user = wp_get_current_user();
        // Create and send message
        $notify_message = $this->cbmm_make_alert_message( $user->user_login, $user, CBMM_ALERT_PLUGIN_DEACTIVATE );
        if ( ! empty( $notify_message ) ) {
            $this->cbmm_alert_send( $notify_message );
        }
    }
    /**
     * Hook for plugin deletion/uninstallation.
     *
     * Handle plugin deletion/uninstallation notifications. Retrieves information about
     * the plugin that is being uninstalled, while we still can :-)
     *
     * @since 1.1.0
     * @param string $plugin The relative path and the filename of the main plugin file
     */
    public function cbmm_handle_uninstall_plugin( string $plugin ) {
        // Get plugin information and pass it on
        $this->cbmm_action_plugin = $this->cbmm_get_plugin_info( $plugin, false );
        // We're done, the next function that should be called is the one below
    }
    /**
     * Hook for plugin deletion/uninstallation.
     *
     * Handle plugin deletion/uninstallation notifications. Called after the plugin
     * has actually been (hopefully) uninstalled.
     *
     * @since 1.1.0
     * @param string $plugin The relative path and the filename of the main plugin file
     * @param bool $uninstall_success If the uninstallation was successful
     */
    public function cbmm_handle_uninstalled_plugin( string $plugin, bool $uninstall_success ) {
        if ( ! empty( $this->cbmm_action_plugin ) ) {
            // Get current user
            $user = wp_get_current_user();
            // Create and send message
            $notify_message = $this->cbmm_make_alert_message( $user->user_login, $user, CBMM_ALERT_PLUGIN_UNINSTALL );
            if ( ! empty( $notify_message ) ) {
                $this->cbmm_alert_send( $notify_message );
            }
        }
    }

    /**
     * Hook for new/edited user.
     *
     * Handle notifications for new users/registered users/user edits.
     *
     * @since 1.1.0
     * @param int $user_id The WordPress user ID of the new/edited user record
     */
    public function cbmm_handle_register_user( int $user_id ) {
        $edit_user = new \WP_User( $user_id );
        $role_caps = $edit_user->get_role_caps();
        if ( is_array( $role_caps ) ) {
            if ( ! $this->cbmm_role_is_active( $role_caps, $this->cbmm_mm_roles_user_add ) ) {
                // We're not configured to notify for this user role/cap, bail
                return;
            }
        }
        $alert_message = $this->cbmm_make_alert_message( $edit_user->user_login, $edit_user, CBMM_ALERT_USER_ADD );
        if ( ! empty( $alert_message ) ) {
            $this->cbmm_alert_send( $alert_message );
        }
    }

    /**
     * Hook for deleted user
     *
     * Handle notifications for when a user is deleted.
     *
     * @since 1.1.0
     * @param string $plugin The relative path and the filename of the main plugin file
     * @param bool $uninstall_success If the uninstallation was successful
     */
    public function cbmm_handle_delete_user( int $user_id, $reassign, \WP_User $user ) {
        $role_caps = $user->get_role_caps();
        if ( is_array( $role_caps ) ) {
            if ( ! $this->cbmm_role_is_active( $role_caps, $this->cbmm_mm_roles_user_delete ) ) {
                // We're not configured to notify for this user role/cap, bail
                return;
            }
        }
        $alert_message = $this->cbmm_make_alert_message( $user->user_login, $user, CBMM_ALERT_USER_DELETE );
        if ( ! empty( $alert_message ) ) {
            $this->cbmm_alert_send( $alert_message );
        }
    }

    /**
     * Add hooks we're watching when WordPress is fully loaded.
     *
     * @since 1.0.0
     */
    public function cbmm_wp_loaded() {
        //@since 1.1.0 Hook these only if we actually have a webhook defined
        if ( ! empty( $this->cbmm_mm_webhook ) ) {
            // Login handlers
            add_action( 'wp_login',             [$this, 'cbmm_alert_login'],         10, 2 );
            add_action( 'wp_login_failed',      [$this, 'cbmm_alert_failed_login'],  10, 2 );
            // Plugin handlers
    		if ( is_admin( ) && is_user_logged_in() && current_user_can( 'administrator' ) )  {
                // Plugin notification handlers
                if ( $this->cbmm_notify_activate_plugin == '1' ) {
                    add_action( 'activated_plugin', [$this, 'cbmm_handle_activate_plugin'], 10, 2 );
                }
                if ( $this->cbmm_notify_deactivate_plugin == '1' ) {
                    add_action( 'deactivated_plugin', [$this, 'cbmm_handle_deactivate_plugin'], 10, 2 );
                }
                if ( $this->cbmm_notify_uninstall_plugin == '1' ) {
                    add_action( 'delete_plugin',  [$this, 'cbmm_handle_uninstall_plugin'],   10, 1 );
                    add_action( 'deleted_plugin', [$this, 'cbmm_handle_uninstalled_plugin'], 10, 2 );
                }
    		}
            // Password reset handlers, we should only do this if we have roles to notify for
            if ( ! empty( $this->cbmm_mm_roles_password_reset ) ) {
                add_action( 'after_password_reset', [$this, 'cbmm_password_reset_hook'], 10, 2 );
            }
            // Password reset e-mail handler, we should only do this if we have roles to block for
            if ( ! empty( $this->cbmm_mm_roles_password_reset_skip_email ) ) {
                // Remove standard handling
                if ( ! remove_action( 'after_password_reset', 'wp_password_change_notification' ) ) {
                    error_log( basename( __FILE__ ) . ' (' . __FUNCTION__ . '): Unable to remove wp_password_change_notification from after_password_reset' );
                }
            }
            // User added handler, we should only do this if we have roles to notify for
            if ( ! empty( $this->cbmm_mm_roles_user_add ) ) {
                add_action( 'user_register', [$this, 'cbmm_handle_register_user'], 10, 1 );
            }
            // User deleted handler, we should only do this if we have roles to notify for
            if ( ! empty( $this->cbmm_mm_roles_user_delete ) ) {
                add_action( 'deleted_user', [$this, 'cbmm_handle_delete_user'], 10, 3 );
            }
        }
    }

    /**
     * Activation of plugin.
     *
     * We don't really need to do anything at activation of CBMM.
     *
     * @since 1.0.0
     */
    /*
    public function cbmm_activate_plugin() {
        error_log( basename(__FILE__) . ' (' . __FUNCTION__ . ')' );
    }
    */

    /**
     * Deactivation of plugin.
     *
     * We don't really need to do anything at deactivation of CBMM.
     *
     * @since 1.0.0
     */
    /*
    public function cbmm_deactivate_plugin() {
        error_log( basename(__FILE__) . ' (' . __FUNCTION__ . ')' );
    }
    */

    /**
     * Setup language support.
     *
     * @since 1.0.0
     */
    public function setup_locale() {
		$rc = load_plugin_textdomain(
    			$this->plugin_name,
                false,
                dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
    }

    /**
     * Run plugin.
     *
     * Basically "enqueues" WordPress actions and lets WordPress do its thing.
     *
     * @since 1.0.0
     */
    public function run() {
        // Plugin activation and deactivation, not needed for this plugin atm :-)
        // register_activation_hook( __FILE__, [$this, 'cbmm_activate_plugin'] );
        // register_deactivation_hook( __FILE__, [$this, 'cbmm_deactivate_plugin'] );

        // Setup i18n. We use the 'init' action rather than 'plugins_loaded' as per
        // https://developer.wordpress.org/reference/functions/load_plugin_textdomain/#user-contributed-notes
		add_action( 'init',                  [$this, 'setup_locale']    );
        // Setup CSS
		add_action( 'admin_enqueue_scripts', [$this, 'cbmm_setup_css']  );
        // Setup
        add_action( 'admin_menu',            [$this, 'cbmm_menu']       );
		add_action( 'admin_init',            [$this, 'cbmm_settings']   );
        add_action( 'wp_loaded',             [$this, 'cbmm_wp_loaded']  );
    }

} // Cloudbridge_Mattermost



/**
 * Run plugin
 *
 * @since 1.0.0
 */
function run_cbmm() {
	$plugin = Cloudbridge_Mattermost::getInstance();
	$plugin->run();
}

run_cbmm();
