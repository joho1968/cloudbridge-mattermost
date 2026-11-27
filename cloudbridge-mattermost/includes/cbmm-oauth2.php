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

// Legacy registered callback: bootstrap only to locate the trusted core route.
$cbmm_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_file( $cbmm_load ) && ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
    $cbmm_load = rtrim( $_SERVER['DOCUMENT_ROOT'], '/\\' ) . '/wp-load.php';
}
if ( ! is_file( $cbmm_load ) ) { http_response_code( 503 ); exit; }
require_once $cbmm_load;
if ( ! defined( 'ABSPATH' ) || ! defined( 'CBMM_VERSION' ) || ! class_exists( __NAMESPACE__ . '\\CBMM_OAuth', false ) ) {
    http_response_code( 503 ); exit;
}
CBMM_OAuth::relay();
