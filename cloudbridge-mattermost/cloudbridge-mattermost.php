<?php
/**
 * Cloudbridge Mattermost
 *
 * @link              https://github.com/joho1968/cloudbridge-mattermost
 * @since             1.0.0
 * @package           Cloudbridge Mattermost
 * @author            Joaquim Homrighausen <joho@webbplatsen.se>
 *
 * @wordpress-plugin
 * Plugin Name:       Cloudbridge Mattermost
 * Plugin URI:        https://github.com/joho1968/cloudbridge-mattermost
 * Description:       Provides integration between Mattermost and WordPress
 * Version:           1.0.0
 * Author:            WebbPlatsen, Joaquim Homrighausen <joho@webbplatsen.se>
 * Author URI:        https://github.com/joho1968/
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


define( 'CBMM_VERSION',                '1.0.0'                  );
define( 'CBMM_REV',                    1                        );
define( 'CBMM_PLUGINNAME_HUMAN',       'Cloudbridge Mattermost' );
define( 'CBMM_PLUGINNAME_SLUG',        'cloudbridge-mattermost' );
define( 'CBMM_ALERT_SUCCESS',          1                        );
define( 'CBMM_ALERT_FAILURE',          2                        );
define( 'CBMM_EMOJI_DEFAULT_NOTICE',   ':unlock:'               );
define( 'CBMM_EMOJI_DEFAULT_WARNING', ':stop_sign:'             );
define( 'CBMM_EMOJI_DEFAULT_LINK',    ':fast_forward:'          );


class Cloudbridge_Mattermost {
	public static $instance = null;
	protected $plugin_name;
	protected $version;
    protected $cbmm_wp_roles = null;
    protected $cbmm_settings_tab = '';

	protected $cbmm_notice_emoji;
	protected $cbmm_warning_emoji;
	protected $cbmm_link_emoji;
	protected $cbmm_link_admin;
	protected $cbmm_mm_webhook;
	protected $cbmm_mm_username;
	protected $cbmm_mm_channel;
	protected $cbmm_mm_mention;
    protected $cbmm_mm_roles_notify;
    protected $cbmm_mm_roles_warn;
    protected $cbmm_mm_unknown_warn;
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
			$this->version = '1.0.0';
		}
		$this->plugin_name = CBMM_PLUGINNAME_SLUG;
        // Fetch options and setup defaults
        $this->cbmm_site_label = $this->cbmm_get_option( 'cbmm-site-label', true );
        $this->cbmm_mm_webhook = $this->cbmm_get_option( 'cbmm-mm-webhook', false );
        $this->cbmm_mm_roles_notify = $this->cbmm_get_option( 'cbmm-roles-notify', true );
        $this->cbmm_mm_roles_warn = $this->cbmm_get_option( 'cbmm-roles-warn', true );
        $this->cbmm_mm_unknown_warn = $this->cbmm_get_option( 'cbmm-unknown-warn', true );
        $this->cbmm_link_admin = $this->cbmm_get_option( 'cbmm-link-admin', true );
        $this->cbmm_notice_emoji = $this->cbmm_get_option( 'cbmm-notice-emoji', true );
        $this->cbmm_warning_emoji = $this->cbmm_get_option( 'cbmm-warning-emoji', true );
        $this->cbmm_link_emoji = $this->cbmm_get_option( 'cbmm-link-emoji', true );
        $this->cbmm_mm_username = $this->cbmm_get_option( 'cbmm-mm-username', false );
        $this->cbmm_mm_channel = $this->cbmm_get_option( 'cbmm-mm-channel', false );
        $this->cbmm_mm_mention = $this->cbmm_get_option( 'cbmm-mm-mention', false );
        $this->cbmm_settings_remove = $this->cbmm_get_option( 'cbmm-settings-remove', false );

        $this->cbmm_settings_tab = ( ! empty( $_GET['tab'] ) ? $_GET['tab'] : '' );
        if ( ! in_array( $this->cbmm_settings_tab, ['emoji','advanced', 'about'] ) ) {
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
	 * @since  1.0.0
     */
    public function cbmm_setup_css() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cloudbridge-mattermost.css', array(), $this->resource_mtime( dirname(__FILE__).'/css/cloudbridge-mattermost.css' ), 'all' );
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
                    $default_val = '(' . __('Unknown', $this->plugin_name) . ')';
                } else {
                    $default_val = '';
                }
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
     * @since  1.0.0
     * @return array List of roles and their human names
     */
    protected function cbmm_get_wp_roles() {
        if ( $this->cbmm_wp_roles !== null ) {
            return( $this->cbmm_wp_roles );
        }
        $wp_roles = wp_roles();
        if ( is_object( $wp_roles ) ) {
            // not sure why WP_Roles::get_roles_data() returns false
            // $roles = $wp_roles->get_roles_data();
            $roles = array_keys( $wp_roles->roles );
            $role_names = $wp_roles->get_names();
        } else {
            $roles = false;
            $role_names = array();
        }
        $return_roles = array();
        if ( is_array( $roles ) ) {
            foreach( $roles as $role_k => $role_v ) {
                if ( ! empty( $role_names[$role_v] ) ) {
                    $return_roles[$role_v] = translate_user_role( $role_names[$role_v] );
                } else {
                    $return_roles[$role_v] = __( 'Unknown role', $this->plugin_name ) . ' (' . $role_v . ')';
                }
            }
        } else {
            error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): wp_roles() returned empty' );
        }
        $this->cbmm_wp_roles =  $return_roles;
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

        add_settings_section( 'cbmm_section_notify', esc_html__( 'Notifications', $this->plugin_name ), false, 'cbmm-settings' );
          add_settings_field( 'cbmm-roles-notify', esc_html__( 'Successful login', $this->plugin_name ), [$this, 'cbmm_setting_roles_notify'], 'cbmm-settings', 'cbmm_section_notify', ['label_for' => 'cbmm-roles-notify'] );
          add_settings_field( 'cbmm-roles-warn', esc_html__( 'Unsuccessful login', $this->plugin_name ), [$this, 'cbmm_setting_roles_warn'], 'cbmm-settings', 'cbmm_section_notify', ['label_for' => 'cbmm-roles-warn'] );
          add_settings_field( 'cbmm-unknown-warn', '', [$this, 'cbmm_setting_unknown_notify'], 'cbmm-settings', 'cbmm_section_notify', ['label_for' => 'cbmm-unknown-warn'] );
          add_settings_field( 'cbmm-link-admin', esc_html__( 'Link to WP Admin', $this->plugin_name ), [$this, 'cbmm_setting_link_admin'], 'cbmm-settings', 'cbmm_section_notify', ['label_for' => 'cbmm-link-admin'] );

        add_settings_section( 'cbmm_section_other', esc_html__( 'Other settings', $this->plugin_name ), false, 'cbmm-settings' );
          add_settings_field( 'cbmm-settings-remove', esc_html__( 'Remove settings', $this->plugin_name ), [$this, 'cbmm_setting_remove'], 'cbmm-settings', 'cbmm_section_other', ['label_for' => 'cbmm-settings-remove'] );

        add_settings_section( 'cbmm_settings_emoji', 'Emoji', [$this, 'cbmm_settings_emoji_callback'], 'cbmm_settings_emoji' );
          add_settings_field( 'cbmm-notice-emoji', esc_html__( 'Successful login', $this->plugin_name ), [$this, 'cbmm_setting_notice_emoji'], 'cbmm_settings_emoji', 'cbmm_settings_emoji', ['label_for' => 'cbmm-notice-emoji'] );
          add_settings_field( 'cbmm-warning-emoji', esc_html__( 'Failed login', $this->plugin_name ), [$this, 'cbmm_setting_warning_emoji'], 'cbmm_settings_emoji', 'cbmm_settings_emoji', ['label_for' => 'cbmm-warning-emoji'] );
          add_settings_field( 'cbmm-link-emoji', esc_html__( 'Admin link', $this->plugin_name ), [$this, 'cbmm_setting_link_emoji'], 'cbmm_settings_emoji', 'cbmm_settings_emoji', ['label_for' => 'cbmm-link-emoji'] );

        add_settings_section( 'cbmm_settings_advanced', esc_html__( 'Advanced', $this->plugin_name ), [$this, 'cbmm_settings_advanced_callback'], 'cbmm_settings_advanced' );
          add_settings_field( 'cbmm-mm-username', esc_html__( 'Webhook username', $this->plugin_name ), [$this, 'cbmm_setting_mm_username'], 'cbmm_settings_advanced', 'cbmm_settings_advanced', ['label_for' => 'cbmm-mm-username'] );
          add_settings_field( 'cbmm-mm-channel', esc_html__( 'Webhook channel', $this->plugin_name ), [$this, 'cbmm_setting_mm_channel'], 'cbmm_settings_advanced', 'cbmm_settings_advanced', ['label_for' => 'cbmm-mm-channel'] );
          add_settings_field( 'cbmm-mm-mention', esc_html__( 'Additional @mention', $this->plugin_name ), [$this, 'cbmm_setting_mm_mention'], 'cbmm_settings_advanced', 'cbmm_settings_advanced', ['label_for' => 'cbmm-mm-mention'] );

        register_setting( 'cbmm-settings', 'cbmm-site-label', ['type' => 'string', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_site_label']] );
        register_setting( 'cbmm-settings', 'cbmm-mm-webhook', ['type' => 'string', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_webhook']] );
        register_setting( 'cbmm-settings', 'cbmm-roles-notify', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_roles']] );
        register_setting( 'cbmm-settings', 'cbmm-roles-warn', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_roles']] );
        register_setting( 'cbmm-settings', 'cbmm-unknown-warn' );
        register_setting( 'cbmm-settings', 'cbmm-link-admin' );
        register_setting( 'cbmm-settings', 'cbmm-settings-remove' );

        register_setting( 'cbmm_settings_emoji', 'cbmm-notice-emoji', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_emoji']] );
        register_setting( 'cbmm_settings_emoji', 'cbmm-warning-emoji', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_emoji']] );
        register_setting( 'cbmm_settings_emoji', 'cbmm-link-emoji', ['type' => 'array', 'sanitize_callback' => [$this, 'cbmm_setting_sanitize_emoji']] );

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
        $available_roles = $this->cbmm_get_wp_roles();
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
        $available_roles = $this->cbmm_get_wp_roles();
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
        $available_roles = $this->cbmm_get_wp_roles();
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
    public function cbmm_setting_link_admin() {
        $option_val = $this->cbmm_get_option( 'cbmm-link-admin', false );
        echo '<div class="cbmm-role-option">';
        echo '<input type="checkbox" name="cbmm-link-admin" id="cbmm-link-admin" value="1" ' . ( checked( $option_val, 1, false ) ) . '/>';
        echo '<label for="cbmm-link-admin">'. esc_html__( 'Include link to WordPress Admin in notifications.', $this->plugin_name ) . '</label> ';
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
             esc_html__( 'Three emojis (markdown) can be configured for notices, warnings, and the admin link. ' .
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
     * @param  string $username WordPress username.
     * @param  string $alert_message The error message.
     * @return boolean true=All good, false=Webhook URL has not been configured
     */
    protected function cbmm_alert_send( string $username, string $alert_message ) : bool {
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
     * Build context based alert message.
     *
     * @since 1.0.0
     * @param string $username Username as entered when logging in.
     * @param mixed $context Either WP_User or WP_Error.
     * @param int $alert_type Type of notification.
     * @return mixed String with alert message or false on error.
     */
    protected function cbmm_make_alert_message( string $username, $context, int $alert_type ) {
        if ( ! in_array( $alert_type, [CBMM_ALERT_SUCCESS, CBMM_ALERT_FAILURE] ) ) {
            error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): Unknown alert_type (' . $alert_type . ')' );
            return( false );
        }
        $alert_message = '';
        // Fetch remote IP if set
        $remote_ip = $_SERVER['REMOTE_ADDR'];
        if ( ! empty( $remote_ip ) ) {
            $remote_ip = ' ' . __( 'from', $this->plugin_name ) . ' ' . $remote_ip;
        }
        // Figure out path to take
        switch( $alert_type ) {
            default: // Notification
                if ( ! is_a( $context, 'WP_User' ) ) {
                    error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): Unknown context "' . get_class( $context ) . '" for alert_type (' . $alert_type . ')' );
                    return( false );
                }
                $name = trim( $context->display_name );
                if ( empty( $context ) ) {
                    $name = trim( $context->user_firstname );
                    $name .= ( empty( $name ) ? '': ' ') . trim( $context->user_lastname );
                }
                $user_login = trim( $context->user_login );
                if ( $name != $user_login ) {
                    $name = ( empty( $name ) ? '`' . $user_login . '`' : $name . ' (`' . $user_login . '`)' );
                }
                $alert_message = $this->cbmm_notice_emoji . ' ' . __( 'Login by', $this->plugin_name ) .
                                 ' ' .
                                 $name .
                                 ' '.
                                 $remote_ip .
                                 ' ' .
                                 __( 'on', $this->plugin_name ) .
                                 ' ' .
                                 '`' . $this->cbmm_site_label . '`' .
                                 "\n";
                break;
            case CBMM_ALERT_FAILURE:
                if ( ! is_a( $context, 'WP_Error' ) ) {
                    error_log( basename(__FILE__) . ' (' . __FUNCTION__ . '): Unknown context "' . get_class( $context ) . '" for alert_type (' . $alert_type . ')' );
                    return( false );
                }
                $alert_code = key( $context->errors );
                $alert_message = $this->cbmm_warning_emoji . ' ' .
                                 __( 'Failed login' ) . $remote_ip . ' ' .
                                 __( 'on', $this->plugin_name ) . ' `' . $this->cbmm_site_label . '`: **';
                switch( $alert_code ) {
                    case 'invalid_username':
                        $alert_message .= __( 'Invalid username', $this->plugin_name );
                        break;
                    case 'incorrect_password':
                        $alert_message .= __( 'Incorrect password', $this->plugin_name );
                        break;
                    default:
                        $alert_message .= __( 'Unknown error', $this->plugin_name ) . ' "' . $alert_code . '"';
                        break;
                } // switch
                $alert_message .= '** (' . __( 'username', $this->plugin_name) . ' `' . $username . '`).' . "\n";
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
    public function cbmm_role_is_active( array $roles, string $notify_roles ) : bool {
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
                $this->cbmm_alert_send( $username, $alert_message );
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
            $this->cbmm_alert_send( $username, $alert_message );
        }
    }

    /**
     * Add hooks we're watching when WordPress is fully loaded.
     *
     * @since 1.0.0
     */
    public function cbmm_wp_loaded() {
        add_action( 'wp_login',        [$this, 'cbmm_alert_login'],        10, 2 );
        add_action( 'wp_login_failed', [$this, 'cbmm_alert_failed_login'], 10, 2 );
    }

    /**
     * Admin init.
     *
     * We don't really need to do anything here.
     *
     * @since 1.0.0
     */
    /*
    public function cbmm_admininit() {
        error_log( basename(__FILE__) . ' (' . __FUNCTION__ . ')' );
    }
    */

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
        // add_action( 'admin_init',            [$this, 'cbmm_admininit']  );
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
