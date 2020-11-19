[![Software License](https://img.shields.io/badge/License-GPL%20v2-green.svg?style=flat-square)](LICENSE) [![PHP 7.2\+](https://img.shields.io/badge/PHP-7.2-blue?style=flat-square)](https://php.net) [![WordPress 5](https://img.shields.io/badge/WordPress-5.0-orange?style=flat-square)](https://wordpress.org) [![Mattermost 5](https://img.shields.io/badge/Mattermost-5-blue?style=flat-square)](https://mattermost.com)

# Cloudbridge Mattermost (CBMM)

Provides Mattermost integration for WordPress. This plugin has been tested with Mattermost 5.28 and above.

## Description

CBMM provides integration with Mattermost. This is the initial release of the plugin. We intend to roll out a number of other features with the coming releases.

The WordPress slug is `cloudbridge-mattermost`.

The plugin is also available on [wordpress.org](https://wordpress.org/plugins/cloudbridge-mattermost/)

The plugin initially provides the following functionality:

* Notifications, using an incoming webhook, upon successful logins
* Notifications, using an incoming webhook, upon failed logins
* Notifications, using an incoming webhook, upon unknown login attempts

The plugin can also make use of additional functionality such as posting to a specific channel/user, overriding bot names, and additionally mention specific users.

### Other notes

* This plugin may work with earlier versions of WordPress
* This plugin has currently only been tested with WordPress 5.5.3 at the time of this writing
* This plugin makes use of the mb_*() PHP functions
* This plugin may create entries in your PHP error log (if active)
* This plugin contains no tracking code and does not process or store any information about users

## Installation

This section describes how to install the plugin and get it working.

1. Upload the `cloudbridge-mattermost` folder to the `/wp-content/plugins/` directory (or install it from the 'Plugins' menu in WordPress)
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the basic settings, such as the webhook URL

## Frequently Asked Questions

### Is the plugin locale aware

Cloudbridge Mattermost uses standard WordPress functionality to handle localization/locale. The native language localization of the plugin is English. It has been translated to Swedish by the author.

### Are there any incompatibilities

This is a hard question to answer. There are no known incompatibilities.

## Changelog

### 1.0.0
* Initial release

## Upgrade Notice

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

### External references

These links are not here for any sort of endorsement or marketing, they're purely for informational purposes.

* Mattermost; https://mattermost.com
* me; :monkey: https://joho.se and https://github.com/joho1968
* WebbPlatsen; https://webbplatsen.se
