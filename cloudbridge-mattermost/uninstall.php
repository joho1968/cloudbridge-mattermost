<?php
/**
 * Cloudbridge Mattermost is uninstalled.
 *
 * @link     https://github.com/joho1968/cloudbridge-mattermost
 * @since    1.0.0
 * @package  Cloudbridge Mattermost
 * @author   Joaquim Homrighausen <joho@webbplatsen.se>
 *
 * uninstall.php
 * Copyright (C) 2020, 2021, 2022 Joaquim Homrighausen; all rights reserved.
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

// Don't load directly
defined( 'ABSPATH' ) || die( '-1' );
// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
// If action is not to uninstall, then exit
if ( empty( $_REQUEST['action'] ) || $_REQUEST['action'] !== 'delete-plugin' ) {
	exit;
}
// If it's not us, then exit
if ( empty( $_REQUEST['slug'] ) || $_REQUEST['slug'] !== 'cloudbridge-mattermost' ) {
	exit;
}
// If we shouldn't do this, then exit
if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'delete_plugins' ) ) {
	exit;
}

// Figure out if an uninstall should remove plugin settings
$remove_settings = get_option( 'cbmm-remove-settings', '0' );

if ( $remove_settings == '1' ) {
	// Remove Cloudbridge Mattermost settings. Transients are removed upon
    // plugin deactivation and do not need to be cleaned up here.
    delete_option( 'cbmm-site-label' );
    delete_option( 'cbmm-mm-webhook' );
    delete_option( 'cbmm-roles-notify' );
    delete_option( 'cbmm-roles-warn' );
    delete_option( 'cbmm-roles-password-reset' );
    delete_option( 'cbmm-roles-password-skip-email' );
    delete_option( 'cbmm-unknown-warn' );
    delete_option( 'cbmm-notify-activate-plugin' );
    delete_option( 'cbmm-notify-deactivate-plugin' );
    delete_option( 'cbmm-notify-uninstall-plugin' );
    delete_option( 'cbmm-roles-user-add' );
    delete_option( 'cbmm-roles-user-delete' );
    delete_option( 'cbmm-link-admin' );
    delete_option( 'cbmm-notice-emoji' );
    delete_option( 'cbmm-warning-emoji' );
    delete_option( 'cbmm-link-emoji' );
    delete_option( 'cbmm-bell-emoji' );
    delete_option( 'cbmm-mm-username' );
    delete_option( 'cbmm-mm-channel' );
    delete_option( 'cbmm-mm-mention' );
    delete_option( 'cbmm-settings-remove' );
    delete_option( 'cbmm-force-locale-enus' );
    delete_option( 'cbmm-oauth2-base-url' );
    delete_option( 'cbmm-oauth2-client-id' );
    delete_option( 'cbmm-oauth2-client-secret' );
    delete_option( 'cbmm-oauth2-login-roles' );
    delete_option( 'cbmm-oauth2-allow-usernames' );
    delete_option( 'cbmm-oauth2-allow-register' );
    delete_option( 'cbmm-oauth2-force-register' );
    delete_option( 'cbmm-oauth2-use-mmidforuuname' );
    delete_option( 'cbmm-cloudflare-ipv4' );
    delete_option( 'cbmm-cloudflare-ipv6' );
}
