=== Lean Cookie Consent ===
Contributors: blacklotusconsulting
Tags: cookie consent, privacy, gdpr, cookie banner, consent
Requires at least: 5.1
Tested up to: 6.8
Requires PHP: 7.1
Stable tag: 2.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Minimal SaaS connector with manual Site Key setup and a bundled local cookie consent runtime.

== Description ==

Lean Cookie Consent is a minimal WordPress connector for the Lean Cookie Consent SaaS platform. The plugin stores a single Site Key in the WordPress options table and enqueues a bundled local runtime on every frontend page. The runtime fetches public site configuration from `https://api.leancookieconsent.com/v1/config?site=YOUR_SITE_KEY`.

Banner copy, layout, colors, cookie categories, services, languages, policy links and consent records are all managed inside the Lean Cookie Consent dashboard. The WordPress plugin bundles only the static runtime, does not keep a custom consent log table, does not set any additional first-party cookies and does not expose any way to insert or execute arbitrary JavaScript.

The plugin is a SaaS connector only. A Lean Cookie Consent account and a valid Site Key are required.

== Installation ==

1. Sign up for a Lean Cookie Consent account at https://leancookieconsent.com/ and create a site. Copy the Site Key shown in your site configuration.
2. Upload the plugin files to the `/wp-content/plugins/lean-cookie-consent` directory, or install the plugin through the WordPress plugins screen.
3. Activate the plugin through the Plugins screen in WordPress.
4. Go to Settings -> Lean Cookie Consent and paste your Site Key. Save / Connect.
5. Open your site in a private browsing window to verify the banner appears.

== Frequently Asked Questions ==

= Does it require an external account? =

Yes. A Lean Cookie Consent account and a Site Key are required. The plugin will not load any script until a valid Site Key is configured.

= Does it load external JavaScript? =

No remote executable JavaScript is loaded by version 2.0.0 and later. When a Site Key is configured, the plugin enqueues its bundled local runtime and the runtime fetches JSON configuration from the Lean Cookie Consent SaaS. The Site Key is passed as a `site` URL query parameter. See the "External services" section below for full details.

= Does the plugin allow inserting or executing arbitrary JavaScript? =

No. The plugin does not expose any script field, custom HTML, custom CSS, textarea, custom URL or other input that could be used to insert or execute arbitrary JavaScript. The only frontend script loaded by this plugin is the bundled local runtime included in the plugin package.

= Does the plugin keep a local consent log? =

No. Consent records are stored and managed entirely by the Lean Cookie Consent SaaS. The WordPress plugin does not create or use any custom database table for consent logging.

= Where do I find my Site Key? =

Sign in to https://app.leancookieconsent.com/admin, open your site configuration and copy the Site Key shown there.

= Is this trialware? =

No. Lean Cookie Consent is a paid SaaS service. A Lean Cookie Consent account is required to use this connector.

== External services ==

This plugin connects to the Lean Cookie Consent SaaS platform to fetch public banner configuration and to record visitor consent choices.

Service: Lean Cookie Consent configuration API
URL: https://api.leancookieconsent.com/v1/config
When: On every frontend page load when a Site Key is configured in Settings → Lean Cookie Consent.
What is sent: The configured Site Key (passed as a `site` URL query parameter, character-whitelist `[a-z0-9_-]`, max 64 chars), and the visitor's browser context (User-Agent, Accept-Language, current URL) needed by the SaaS to return the correct public configuration.
What is received: A JSON configuration payload used by the bundled local runtime to render the cookie banner, preference center and consent-mode signaling on the visitor's browser. The payload also includes the consent record API endpoint used by the banner.
Why: To display the cookie banner and record consent choices that match the configuration you set up in your Lean Cookie Consent dashboard.
Account: A Lean Cookie Consent account and Site Key are required. The Site Key is entered manually in Settings → Lean Cookie Consent.
Service provider: Alessandro Romani e Alessandro Vona (https://leancookieconsent.com/)
Privacy Policy: https://leancookieconsent.com/privacy-policy
Terms of Service: https://leancookieconsent.com/terms

Service: Lean Cookie Consent consent API
URL: https://api.leancookieconsent.com/api/consent
When: Only when a visitor interacts with the bundled local banner and saves, denies or accepts consent choices.
What is sent: The configured Site Key, the visitor's selected consent categories/action, banner/policy metadata from the SaaS configuration and technical browser request metadata needed to store the consent evidence.
What is received: A JSON response confirming whether the consent event was stored.
Why: To keep an auditable consent record for the site configuration managed in Lean Cookie Consent.

The WordPress plugin itself does not send any other data to any other service. The only data stored by this plugin is the Site Key, kept in the WordPress `wp_options` table under the option name `lean_cookie_consent_site_key`. The plugin does not set any first-party cookies, does not keep any custom database tables and does not record consent locally.

== Privacy ==

This plugin does not collect, store or transmit visitor data on its own. The only data it stores in WordPress is the Site Key configured by the site owner.

The cookie consent banner, preference collection and consent records are handled by the Lean Cookie Consent SaaS. Please review the Lean Cookie Consent Privacy Policy (https://leancookieconsent.com/privacy-policy) for details on what the SaaS collects and how it is processed.

This plugin adds a short suggested text to the WordPress Privacy Policy Guide describing the SaaS integration and the data handled by the SaaS.

== Changelog ==

= 2.0.3 =
* Correct the WordPress readme "Tested up to" value for repository validation.

= 2.0.2 =
* Add idempotent upgrade handling for legacy installs, including plugin version storage, legacy Site Key preservation when available and an admin notice when a SaaS Site Key must be configured.

= 2.0.1 =
* Point the WordPress settings page dashboard button directly to the Lean Cookie Consent admin area.

= 2.0.0 =
* Plugin rebuilt as a minimal SaaS connector. Removed the entire local consent management platform: banner HTML, preference panel, consent logging, CSV export, search, delete, retention cleanup, pseudonymization, category descriptions, color picker, font picker, layout picker, position picker, consent expiration, privacy policy guide for local CMP, custom consent table.
* The plugin now stores a single Site Key and enqueues a bundled local runtime on the frontend. Banner configuration is fully managed in the Lean Cookie Consent dashboard and fetched as JSON.
* Removed arbitrary script insertion completely. The plugin does not provide custom script, custom HTML, custom CSS, account registration, automatic onboarding or account-linking features in version 2.0.0.
* Added the External Services disclosure required by WordPress.org guidelines.
* Added uninstall cleanup that removes the legacy local CMP options and drops the legacy `wp_lean_cookie_consent` table.

= 1.3.8 =
* Added pseudonymous consent IDs, action/event metadata, site URL, banner language and CSV export fields to the Free consent log.
* Replaced raw IP display/storage for new consent records with a one-way hash and a 12-month cleanup baseline.

= 1.3.7 =
* Fixed Plugin Check findings for the standalone package.
* Replaced wp_date() with a WordPress 5.1-compatible date helper.
* Tightened uninstall cleanup naming and database-safety annotations.

= 1.3.6 =
* Updated the frontend cookie banner to match the hosted Lean Cookie Consent layout.
* Made preferences visible in the banner by default, with SaaS-style panel, overlay, two-column desktop layout and compact mobile layout.

= 1.3.5 =
* Reset legacy banner settings on upgrade to the standalone profile.
* Updated the default first-run banner layout to a compact bottom-right box.

= 1.3.4 =
* Fixed frontend banner rendering by avoiding an admin-only WordPress helper.

= 1.3.3 =
* Removed third-party consent helper output and related settings from the standalone plugin.
* Simplified plugin copy and documentation around the standalone local workflow.
* Added suggested Privacy Policy Guide text for the plugin local cookies and consent records.
* Added uninstall cleanup for plugin options and custom consent log tables.
* Kept the plugin focused on local banner display, preference collection and consent records.

= 1.3.2 =
* Strengthened WCAG 2.1 A/AA support for the frontend banner and preference panel.
* Added dialog description/status semantics, visible focus styles, focus restoration and improved keyboard handling.
* Improved default contrast and mobile/text-spacing resilience.

= 1.2.0 =
* Added policy page selector and policy version tracking.
* Stored policy/plugin version metadata with consent records.
* Added editable category descriptions.
* Improved admin consent record visibility for policy versions.

= 1.1.0 =
* Renamed product and slug to Lean Cookie Consent.
* Added preference panel with technical, analytics and marketing categories.
* Added configurable consent expiration.
* Added layout and position presets.
* Improved frontend footprint for visitors with saved consent.

= 1.0.1 =
* Security and WordPress.org review remediation pass.
* Added nonces and capability checks for admin actions.
* Added AJAX nonce verification for consent logging.
* Replaced deprecated multisite activation code.
* Removed timezone override.
* Improved sanitization, validation and escaping.
* Aligned text domain with the plugin slug.

= 1.0 =
* First release.
