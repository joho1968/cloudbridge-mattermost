[![License GPLv2 or later](https://img.shields.io/badge/License-GPLv2%2B-green.svg?style=flat-square)](cloudbridge-mattermost/LICENSE.txt) [![PHP 8.1 or later](https://img.shields.io/badge/PHP-8.1%2B-blue.svg?style=flat-square)](https://www.php.net/)

# Cloudbridge Mattermost

Use Mattermost to sign in to WordPress or register a WordPress account. An incoming webhook can notify a Mattermost channel about sign-ins, password resets, user changes, and plugin activation, deactivation, or removal.

## Install

1. Install Cloudbridge Mattermost from the WordPress plugin directory or upload the installable ZIP from the GitHub release under **Plugins → Add New Plugin → Upload Plugin**. Requires PHP 8.1 or later and WordPress 5.4 or later.
2. Activate the plugin. Configure a Mattermost incoming webhook to enable notifications.
3. For sign-in or registration, register a confidential OAuth 2.0 application in Mattermost, enter its credentials in the plugin's **OAuth2** settings, and save a **Local login verification** policy.

For a standard WordPress installation, register this callback in Mattermost, using your own domain. Keep the existing callback when upgrading. If WordPress uses a custom plugin directory, use the actual URL of the callback file.

```text
https://example.com/wp-content/plugins/cloudbridge-mattermost/includes/cbmm-oauth2.php
```

[WordPress plugin directory](https://wordpress.org/plugins/cloudbridge-mattermost/) · [GitHub releases](https://github.com/joho1968/cloudbridge-mattermost/releases) · [Mattermost OAuth 2.0 documentation](https://developers.mattermost.com/integrate/apps/authentication/oauth2/)

## Upgrade to 2.3.0

1. If you require local verification, update Cloudbridge 2FA to 2.1.0 or later with external login API 2 before updating Cloudbridge Mattermost.
2. After updating Cloudbridge Mattermost, choose and save **Local login verification** in the **OAuth2** settings. If you require local verification, save **Require Cloudbridge 2FA integration** again. A saved API 1 required policy blocks Mattermost sign-in until you save the API 2 policy.
3. Test both Mattermost and WordPress sign-in. Restart any sign-ins that were in progress. If you remove a custom API 1 adapter, keep any account restrictions it enforces.

## What's new in 2.3.0

- Add local login verification with Cloudbridge 2FA support and keep the registered callback URL.
- Allow Mattermost registration when public WordPress registration is disabled.
- Warn about unencrypted OAuth and webhook URLs. Validate OAuth responses and bind sign-in attempts to the browser that started them.
- Improve webhook error reporting and limit repeated failed-sign-in notifications.
- Require PHP 8.1 or later and ship updated OAuth, HTTP, and CIDR dependencies.

This release was verified with Mattermost 8 through 12 and WordPress 7.1.x. PHP 8.1 through 8.5 were tested. See the plugin readme for the full changelog, configuration options, and details about the data processed by OAuth and notifications. [Plugin readme](cloudbridge-mattermost/README.txt)

## Build from source

The GitHub source checkout uses Composer for its PHP dependencies. From the `cloudbridge-mattermost` directory, run `composer install --no-dev --prefer-dist` before copying the plugin directory to WordPress. The installable ZIP includes the dependencies.

## License and support

Cloudbridge Mattermost was written by Joaquim Homrighausen while converting :coffee: into code and is sponsored by WebbPlatsen i Sverige AB in Stockholm, Sweden. It is available under GPLv2 or later. [License](cloudbridge-mattermost/LICENSE.txt)

For support or commercial customizations, contact support@webbplatsen.se. [Project site](https://code.webbplatsen.net/wordpress/cloudbridge-mattermost/)
