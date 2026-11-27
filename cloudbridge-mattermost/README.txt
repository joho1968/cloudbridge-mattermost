=== Cloudbridge Mattermost ===
Contributors: joho68, webbplatsen
Donate link: https://code.webbplatsen.net/wordpress/cloudbridge-mattermost/
Tags: mattermost, cloud, integration, notifications, security
Requires at least: 5.4.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Sign in to WordPress with Mattermost and send WordPress event notifications to a Mattermost channel.

== Description ==

Use Mattermost to sign in to WordPress or register a WordPress account. Set up a Mattermost incoming webhook to get notifications for these events.

* Successful and failed sign-ins, including attempts by unknown users
* Password resets
* User creation, updates, and deletion
* Changes to the site administrator's email address
* Plugin activation, deactivation, and removal

Choose the channel, set the bot name, and add mentions in the notification settings.

* Requires PHP 8.1 or later and was tested on PHP 8.1 through 8.5.
* This plugin optionally makes use of the `mb_` PHP functions
* This plugin may create entries in your PHP error log (if active)
* This plugin contains no JavaScript
* This plugin contains no tracking code. See below for the data used by notifications and Mattermost sign-in.

== Installation ==

1. Install Cloudbridge Mattermost from the WordPress Plugins screen. For a manual installation, copy the `cloudbridge-mattermost` folder into `wp-content/plugins/`.
2. Activate the plugin from the Plugins screen.
3. To enable notifications, enter a Mattermost incoming webhook URL in the plugin settings.
4. To enable OAuth2, register a confidential application in Mattermost and enter its credentials in the plugin's OAuth2 settings. [Mattermost OAuth 2.0 documentation](https://developers.mattermost.com/integrate/apps/authentication/oauth2/)

== Frequently Asked Questions ==

= Is the plugin locale aware =

Cloudbridge Mattermost uses standard WordPress functionality to handle localization/locale. The native language localization of the plugin is English. It has been translated to Swedish by the author.

For notifications sent to Mattermost, there is a setting since 1.1.0 that will allow you to override all such messages to be in en_US locale (English).

= Are there any incompatibilities =

Test Mattermost sign-in with any plugin or custom code that restricts WordPress accounts. If you require local two-factor verification for Mattermost sign-in, use Cloudbridge 2FA 2.1.0 or later with external login API 2.

= Is there a way to customize the Mattermost login button =

Add the `cbmm_login_filter` filter in your theme's `functions.php` or in a plugin.

> add_filter('cbmm_login_filter', 'name_of_your_function', 10, 1);

The filter receives an array with `url`, `text`, and `full` keys. Return the array with a new `text` value to change the label. Use plain text and let the plugin escape it for display. The `url` value is escaped for HTML, and `full` contains the complete markup for the Mattermost sign-in section. To replace that markup, return it in an `override` key and escape any dynamic values in your own code.

= Can accounts be matched only by email =

Yes. Leave "Attempt to match Mattermost and WordPress usernames if e-mail match fails" unchecked in the OAuth2 settings. Login then requires the authenticated Mattermost account's verified email to match the WordPress account's email. Only enable username fallback when usernames are centrally controlled and identify the same people in both systems. Email reassignment should also be considered when managing account access.

= Can Mattermost registration remain enabled while public WordPress registration is disabled =

Yes. Enable registration in the plugin's OAuth2 settings. Users must sign in to Mattermost with a verified email before the plugin creates their WordPress account. The Mattermost registration button appears on the login form when public registration is disabled. New accounts get the WordPress default role. The allowed OAuth2 roles determine whether they can sign in with Mattermost.

= Are HTTPS URLs required =

HTTPS is recommended for Mattermost OAuth2 and incoming webhook URLs. HTTP URLs remain supported, with a warning in the corresponding settings field that traffic is not encrypted.

= Does Mattermost login require cookies =

Yes. Allow cookies and finish signing in or registering within 15 minutes in the same browser. The plugin sets an HttpOnly, SameSite=Lax cookie for the WordPress login path and marks it Secure on HTTPS. Restart any sign-in or registration that began before the 2.3.0 upgrade.

= How does OAuth2 interact with local MFA and account restrictions =

After updating, choose and save "Local login verification" in the OAuth2 settings. If you require local verification, update Cloudbridge 2FA to 2.1.0 or later first. Update Cloudbridge Mattermost, save "Require Cloudbridge 2FA integration" again, then test both Mattermost and WordPress sign-in. A saved API 1 required policy blocks Mattermost sign-in until you save the API 2 policy. Before removing a custom API 1 adapter, keep any account restrictions it enforces.

"Require Cloudbridge 2FA integration" requires external login API 2. Cloudbridge Mattermost verifies the Mattermost identity and allowed roles. WordPress and Cloudbridge 2FA then apply local account restrictions and the configured two-factor requirements. WordPress creates the session after verification completes. If the required integration is unavailable or the account or policy changes during the attempt, sign-in stops.

"Existing WordPress login policy" applies WordPress authentication filters to the matched account. If a local two-factor plugin denies Mattermost sign-in, use the normal WordPress login for that account. Test site-specific restrictions with both sign-in methods. Password-only checks apply to password sign-in. Use a general account policy hook for restrictions that also need to apply to OAuth.

= What information is sent or stored =

Webhook notifications can include usernames, display names, roles, source IP addresses, site and plugin names, administrator email changes, and the type of event. Your Mattermost server controls how long it keeps the messages. Notifications omit passwords and password-reset keys. The plugin escapes account details that could trigger mentions and keeps mentions you configured in the settings.

For OAuth2, the plugin uses verified Mattermost profile details to match or create a WordPress account. WordPress stores the account and the completed login session. While sign-in is pending, the plugin stores the expiry, the destination if supplied, and the information it needs to check that the browser, settings, and login policy still match. The browser secret stays in the HttpOnly cookie.

When Cloudbridge 2FA is required, its temporary challenge stores account identifiers, checks for changes to the settings and selected policy, and holds the destination until verification finishes. It omits OAuth tokens and the client secret. The callback debug log records events and omits profiles, codes, state values, cookies, and redirect URLs. Your server and other plugins may log requests separately.

By default, failed sign-ins generate at most one notification per source every 30 seconds when a persistent object cache is active. Sites using database transients can send duplicates if requests arrive together. The rate limit uses a site-specific hash of the source IP. Use the `cbmm_failed_login_notice_interval` filter to set the interval in seconds. The maximum is 300. Set it to 0 to turn off this limit.

Use the final webhook URL because the plugin doesn't follow redirects. Delivery waits up to five seconds and requires an HTTP 2xx response. A failed notification leaves the login or password reset in place.

= What callback URL do I have to setup on Mattermost =

For a standard WordPress installation, register `https://example.com/wp-content/plugins/cloudbridge-mattermost/includes/cbmm-oauth2.php` with your own domain.

If WordPress uses a custom plugin directory, register the actual URL of `includes/cbmm-oauth2.php`. Keep the existing Mattermost callback when upgrading. Cloudbridge Mattermost forwards the callback to the site's WordPress login endpoint.

== Changelog ==

= 2.3.0 =

* Verified with Mattermost 8 through 12 and WordPress 7.1.x.
* Add a local login verification setting and support required two-factor verification with Cloudbridge 2FA 2.1.0 and external login API 2
* Keep the registered Mattermost callback URL and complete OAuth through the WordPress login endpoint
* Require administrators to save the OAuth verification policy after upgrading, and restart any sign-ins already in progress
* Check account matching, allowed roles, provider settings and verification expiry again before starting a WordPress session
* Require PHP 8.1 or later and update the bundled HTTP, OAuth, and CIDR libraries
* Show Mattermost registration on the login form when it is enabled and public WordPress registration is disabled
* Warn about unencrypted HTTP OAuth2 and webhook URLs without preventing their use
* Require sign-ins to finish in the same browser and verify the Mattermost response before logging in
* Keep account details and OAuth secrets out of callback logs
* Limit failed-sign-in notifications and prevent account details from triggering mentions
* Set a five-second webhook timeout and report failed deliveries
* Fix duplicate password-change notifications, private Mattermost URL validation, and transient cleanup on uninstall
* Require the WordPress `manage_options` capability to view or save plugin settings

= 2.2.3 =

* Verified with Mattermost version 8-11 and WordPress 6.9.x
* Notifications are no longer sent for non‑standard login errors when all “Unsuccessful login” roles are unchecked (#2, itsumonotakumi)
* Fix override handling for Mattermost login/register button filter output
* Hardened OAuth2 post-login redirects to same-host only; external redirect_to values now fall back to admin/home
* Updated Composer dependencies (guzzlehttp/*, league/oauth2-client, symfony/deprecation-contracts) and removed paragonie/random_compat

= 2.2.2 =
* Verified with Mattermost 8.x, 9.x, 10.x and WordPress 6.8.x
* Thanks to Nelson (castillo-n) for various fixes (PR#1)
* Added missing property `$cbmm_site_label` to class
* Removed use of `FILTER_SANITIZE_STRING` (deprecated in PHP 8.1)
* Removed use of uninitialized variables (exception handling)

= 2.2.1 =
* Refactor code to prevent warnings in PHP log while checking roles
* Verified with Mattermost 8.x, 9.x and WordPress 6.5.x
* Updated various dependencies

= 2.2.0 =
* Improved support for Mattermost accounts with 2FA/MFA enabled
* Added possibility to register new users via Mattermost
* Added possibility to register new users only via Mattermost
* Verified with Mattermost 7.x and WordPress 6.x
* Updated various dependencies

= 2.1.0 =
* Verified with WordPress 5.8
* Support for Cloudflare
* Minor fixes

= 2.0.0 =
* Added **OAuth2 support**, you can now login to WordPress via Mattermost!
* Changed emoji for admin link to a more suitable one (:link:)

= 1.1.0 =
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

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.3.0 =

Requires PHP 8.1. For required local verification, upgrade Cloudbridge 2FA to 2.1.0 first, then upgrade Cloudbridge Mattermost, save the required policy again, and test sign-in.

= 2.2.1 =
* Simply install/upgrade to 2.2.1 and walk through the settings

= 2.2.0 =
* Simply install/upgrade to 2.2.0 and walk through the settings

= 2.0.0 =
* Simply install/upgrade 1.x to 2.0.0 and walk through the settings

= 1.1.0 =
* Simply install/upgrade 1.0.0 to 1.1.0 and walk through the settings

= 1.0.0 =
* Initial release

== Credits ==

The Cloudbridge Mattermost WordPress Plugin was written by Joaquim Homrighausen while converting caffeine into code.

Cloudbridge Mattermost is sponsored by [WebbPlatsen i Sverige AB](https://webbplatsen.se), Stockholm, Sweden.

Commercial support and customizations for this plugin is available from WebbPlatsen i Sverige AB in Stockholm, Sweden.

If you find this plugin useful, the author is happy to receive a donation, good review, or just a kind word.

If there is something you feel to be missing from this plugin, or if you have found a problem with the code or a feature, please do not hesitate to reach out to support@webbplatsen.se.

This plugin can also be downloaded from [code.webbplatsen.net](https://code.webbplatsen.net/wordpress/cloudbridge-mattermost/) and [GitHub](https://github.com/joho1968/cloudbridge-mattermost)
