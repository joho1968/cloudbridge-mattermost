[![Software License](https://img.shields.io/badge/License-GPL%20v2-green.svg?style=flat-square)](LICENSE) [![PHP 7.2\+](https://img.shields.io/badge/PHP-7.2-blue?style=flat-square)](https://php.net) [![WordPress 5](https://img.shields.io/badge/WordPress-5.0-orange?style=flat-square)](https://wordpress.org) [![Mattermost 5](https://img.shields.io/badge/Mattermost-5-blue?style=flat-square)](https://mattermost.com)

# Cloudbridge Mattermost (CBMM)

Mattermost integration for WordPress. Tested with Mattermost 5.30.1+ and WordPress 5.5+.

## Description

This WordPress plugin provides integration with Mattermost.

The WordPress slug is `cloudbridge-mattermost`.

The plugin is also available on [wordpress.org](https://wordpress.org/plugins/cloudbridge-mattermost/)

The plugin provides `OAuth2 authentication` functionality for logging into WordPress via Mattermost.

The plugin additionally provides the following `notification` functionality, using an `incoming webhook` in Mattermost:

* Notifications for successful login
* Notifications for failed login
* Notifications for unknown user login attempt
* Notifications for password reset
* Notifications for plugin activation
* Notifications for plugin deactivation
* Notifications for plugin uninstallation
* Notifications for new/edited user
* Notifications for deleted user

The plugin can also make use of additional functionality such as posting to a specific channel/user, overriding bot names, and additionally mention specific users.

### Other notes

* This plugin may work with earlier versions of WordPress
* This plugin has been tested with `WordPress 5.5.3 and 5.6` at the time of this writing
* This plugin optionally makes use of the `mb_substr()` PHP function
* This plugin may create entries in your PHP error log (if active)
* This plugin contains no Javascript and is not sensitive to the coming jQuery updates in WordPress
* This plugin contains no tracking code and does not process or store any information about users

## Installation

This section describes how to install the plugin and get it working.

1. Upload the `cloudbridge-mattermost` folder to the `/wp-content/plugins/` directory (or install it from the 'Plugins' menu in WordPress)
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the basic settings, such as the webhook URL

## Frequently Asked Questions

### Is the plugin locale aware

Cloudbridge Mattermost uses standard WordPress functionality to handle localization/locale. The native language localization of the plugin is English. It has been translated to Swedish by the author.

For notifications sent to Mattermost, there is a setting since 1.1.0 that will allow you to override all such messages to be in en_US locale (English).

### Are there any incompatibilities

This is a hard question to answer. There are no known incompatibilities.

## Changelog

### 2.0.0
* Added `OAuth2 support`, you can now login to WordPress via Mattermost!
* Changed emoji for admin link to a more suitable one (:link:)

### 1.1.0
* Moved notifications to a separate tab
* Added notifications for password reset
* Added notification for plugin activation
* Added notification for plugin deactivation
* Added notification for plugin uninstallation
* Added notification for new/edited user
* Added notification for deleted user
* Added general notice emoji (:bell:)
* Added setting to force notifications to be en_US locale (English)
* Login/Login failure hooks are only hooked if webhook URL has been configured
* Corrected locale display/handling of some strings
* Updated donate link

### 1.0.0
* Initial release

## Upgrade Notice

### 2.0.0
* Simply install/upgrade 1.x to 2.0.0 and walk through the settings

### 1.1.0
* Simply install/upgrade 1.0.0 to 1.1.0 and walk through the settings

### 1.0.0
* Initial release

## License

Please see [LICENSE](LICENSE) for a full copy of GPLv2

Copyright (C) 2020 [Joaquim Homrighausen](https://github.com/joho1968); all rights reserved.

This file is part of Cloudbridge Mattermost (CBMM). Cloudbridge Mattermost is free software.

You may redistribute it and/or modify it under the terms of the GNU General Public License version 2, as published by the Free Software Foundation.

Cloudbridge Mattermost is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with the CBMM package. If not, write to:

```
The Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor
Boston, MA  02110-1301, USA.
```

## Credits

The Cloudbridge Mattermost WordPress Plugin was written by Joaquim Homrighausen while converting :coffee: into code.

Cloudbridge Mattermost is sponsored by [WebbPlatsen i Sverige AB](https://webbplatsen.se), Stockholm, :sweden:

Commercial support and customizations for this plugin is available from WebbPlatsen i Sverige AB in Stockholm, :sweden:

If you find this plugin useful, the author is happy to receive a donation, good review, or just a kind word.

If there is something you feel to be missing from this plugin, or if you have found a problem with the code or a feature, please do not hesitate to reach out to support@webbplatsen.se.

This plugin can also be downloaded from [code.webbplatsen.net](https://code.webbplatsen.net/wordpress/cloudbridge-mattermost/) and [WordPress.org](https://wordpress.org/plugins/cloudbridge-mattermost/)

Kudos to [The League of Extraordinary Packages](https://thephpleague.com/).

### External references

These links are not here for any sort of endorsement or marketing, they're purely for informational purposes.

* Mattermost; https://mattermost.com
* me; :monkey: https://joho.se and https://github.com/joho1968
* WebbPlatsen; https://webbplatsen.se and https://code.webbplatsen.net
