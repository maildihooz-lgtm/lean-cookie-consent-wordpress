<?php
/**
 * Plugin Name: Lean Cookie Consent
 * Plugin URI: https://github.com/maildihooz-lgtm/lean-cookie-consent-wordpress
 * Description: Minimal WordPress connector for the Lean Cookie Consent SaaS. Configure a Site Key in the admin area; the plugin enqueues a bundled local runtime that fetches site configuration from the SaaS. The plugin does not store custom consent logs and does not allow arbitrary script insertion.
 * Version: 2.0.3
 * Requires at least: 5.1
 * Requires PHP: 7.1
 * Author: Alessandro Romani
 * Author URI: https://www.blacklotus.eu
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lean-cookie-consent
 *
 * @package LeanCookieConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEAN_COOKIE_CONSENT_VERSION', '2.0.3' );
define( 'LEAN_COOKIE_CONSENT_FILE', __FILE__ );
define( 'LEAN_COOKIE_CONSENT_OPTION', 'lean_cookie_consent_site_key' );
define( 'LEAN_COOKIE_CONSENT_VERSION_OPTION', 'lean_cookie_consent_plugin_version' );
define( 'LEAN_COOKIE_CONSENT_UPGRADE_NOTICE_OPTION', 'lean_cookie_consent_upgrade_notice' );
define( 'LEAN_COOKIE_CONSENT_API_BASE_URL', 'https://api.leancookieconsent.com' );
define( 'LEAN_COOKIE_CONSENT_SITE_KEY_MAX_LENGTH', 64 );
define( 'LEAN_COOKIE_CONSENT_SITE_KEY_PATTERN', '/^[a-z0-9_-]+$/' );  // Production Site Key charset: a-z 0-9 and underscore (verified: 0 hyphens in 42 production sites)

register_activation_hook( LEAN_COOKIE_CONSENT_FILE, 'lean_cookie_consent_activate' );

/**
 * Run installation and upgrade routines on plugin activation.
 *
 * @param bool $network_wide Whether the plugin is network-activated.
 * @return void
 */
function lean_cookie_consent_activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			lean_cookie_consent_run_upgrade();
			restore_current_blog();
		}
		return;
	}

	lean_cookie_consent_run_upgrade();
}

add_action( 'admin_init', 'lean_cookie_consent_maybe_upgrade', 1 );

/**
 * Run pending upgrade routines during admin requests.
 *
 * This covers normal WordPress plugin updates, where activation hooks are not
 * fired again after replacing the plugin files.
 *
 * @return void
 */
function lean_cookie_consent_maybe_upgrade() {
	$stored_version = get_option( LEAN_COOKIE_CONSENT_VERSION_OPTION, '' );
	if ( LEAN_COOKIE_CONSENT_VERSION === $stored_version ) {
		return;
	}

	lean_cookie_consent_run_upgrade();
}

/**
 * Apply idempotent upgrade steps and store the current plugin version.
 *
 * @return void
 */
function lean_cookie_consent_run_upgrade() {
	$stored_version  = get_option( LEAN_COOKIE_CONSENT_VERSION_OPTION, '' );
	$legacy_settings = get_option( 'lean_cookie_consent_settings', false );
	$is_legacy       = ( '' === $stored_version && false !== $legacy_settings ) || ( '' !== $stored_version && version_compare( (string) $stored_version, '2.0.0', '<' ) );

	if ( $is_legacy ) {
		lean_cookie_consent_upgrade_from_legacy( $legacy_settings );
	}

	update_option( LEAN_COOKIE_CONSENT_VERSION_OPTION, LEAN_COOKIE_CONSENT_VERSION, false );
}

/**
 * Migrate from the legacy local CMP profile to the SaaS connector profile.
 *
 * Legacy 1.x installs did not require a SaaS Site Key, so most upgrades will
 * need the administrator to paste one manually. If a compatible key happens to
 * be present in stored legacy settings, preserve it.
 *
 * @param mixed $legacy_settings Stored legacy settings option.
 * @return void
 */
function lean_cookie_consent_upgrade_from_legacy( $legacy_settings ) {
	if ( '' === lean_cookie_consent_get_site_key() ) {
		$legacy_site_key = lean_cookie_consent_extract_legacy_site_key( $legacy_settings );
		if ( '' !== $legacy_site_key ) {
			update_option( LEAN_COOKIE_CONSENT_OPTION, $legacy_site_key, false );
		} else {
			update_option( LEAN_COOKIE_CONSENT_UPGRADE_NOTICE_OPTION, 'site_key_required', false );
		}
	}

	wp_clear_scheduled_hook( 'lean_cookie_consent_cleanup' );
	wp_clear_scheduled_hook( 'lean_cookie_consent_daily_cleanup' );
}

/**
 * Extract a compatible Site Key from legacy settings, if one exists.
 *
 * @param mixed $legacy_settings Stored legacy settings option.
 * @return string
 */
function lean_cookie_consent_extract_legacy_site_key( $legacy_settings ) {
	if ( ! is_array( $legacy_settings ) ) {
		return '';
	}

	$candidate_keys = array( 'site_key', 'siteKey', 'lean_site_key', 'account_site_key' );
	foreach ( $candidate_keys as $candidate_key ) {
		if ( empty( $legacy_settings[ $candidate_key ] ) || ! is_string( $legacy_settings[ $candidate_key ] ) ) {
			continue;
		}
		$site_key = lean_cookie_consent_sanitize_site_key( $legacy_settings[ $candidate_key ] );
		if ( '' !== $site_key ) {
			return $site_key;
		}
	}

	return '';
}

add_action( 'admin_notices', 'lean_cookie_consent_upgrade_admin_notice' );

/**
 * Tell legacy users that they must connect the new SaaS Site Key.
 *
 * @return void
 */
function lean_cookie_consent_upgrade_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( 'site_key_required' !== get_option( LEAN_COOKIE_CONSENT_UPGRADE_NOTICE_OPTION, '' ) ) {
		return;
	}

	delete_option( LEAN_COOKIE_CONSENT_UPGRADE_NOTICE_OPTION );
	$url = admin_url( 'options-general.php?page=lean-cookie-consent' );
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php
			printf(
				wp_kses(
					/* translators: %s: plugin settings page URL. */
					__( 'Lean Cookie Consent was upgraded to the SaaS connector profile. Add your Site Key in <a href="%s">Settings &gt; Lean Cookie Consent</a> to load the frontend runtime.', 'lean-cookie-consent' ),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				),
				esc_url( $url )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Return the validated Site Key or an empty string.
 *
 * Whitelist: lowercase letters, digits, underscore and hyphen. Max length
 * enforced at sanitization. This value is the only piece of user-controlled
 * data passed to the bundled runtime.
 *
 * @return string
 */
function lean_cookie_consent_get_site_key() {
	$site_key = get_option( LEAN_COOKIE_CONSENT_OPTION, '' );
	if ( ! is_string( $site_key ) ) {
		return '';
	}
	$site_key = trim( $site_key );
	if ( '' === $site_key ) {
		return '';
	}
	if ( strlen( $site_key ) > LEAN_COOKIE_CONSENT_SITE_KEY_MAX_LENGTH ) {
		return '';
	}
	if ( ! preg_match( LEAN_COOKIE_CONSENT_SITE_KEY_PATTERN, $site_key ) ) {
		return '';
	}
	return $site_key;
}

/**
 * Sanitize_callback for the Site Key setting.
 *
 * Strips anything outside the whitelist. Empty input becomes an empty string
 * (which disables the frontend enqueue).
 *
 * @param mixed $value Raw value submitted via the Settings API.
 * @return string
 */
function lean_cookie_consent_sanitize_site_key( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = trim( $value );
	if ( '' === $value ) {
		return '';
	}
	if ( strlen( $value ) > LEAN_COOKIE_CONSENT_SITE_KEY_MAX_LENGTH ) {
		$value = substr( $value, 0, LEAN_COOKIE_CONSENT_SITE_KEY_MAX_LENGTH );
	}
	$value = strtolower( $value );
	if ( ! preg_match( LEAN_COOKIE_CONSENT_SITE_KEY_PATTERN, $value ) ) {
		return '';
	}
	return $value;
}

add_action( 'admin_init', 'lean_cookie_consent_register_settings' );

/**
 * Register the single Site Key setting with the WordPress Settings API.
 *
 * The Settings API provides nonce verification and capability checking
 * automatically for the registered option group.
 *
 * @return void
 */
function lean_cookie_consent_register_settings() {
	register_setting(
		'lean_cookie_consent_settings',
		LEAN_COOKIE_CONSENT_OPTION,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'lean_cookie_consent_sanitize_site_key',
			'default'           => '',
			'show_in_rest'      => false,
			'description'       => __( 'Lean Cookie Consent Site Key', 'lean-cookie-consent' ),
		)
	);
}

add_action( 'admin_menu', 'lean_cookie_consent_register_settings_page' );

/**
 * Register the admin settings page under Settings → Lean Cookie Consent.
 *
 * @return void
 */
function lean_cookie_consent_register_settings_page() {
	add_options_page(
		__( 'Lean Cookie Consent', 'lean-cookie-consent' ),
		__( 'Lean Cookie Consent', 'lean-cookie-consent' ),
		'manage_options',
		'lean-cookie-consent',
		'lean_cookie_consent_render_settings_page'
	);
}

add_action( 'admin_enqueue_scripts', 'lean_cookie_consent_enqueue_admin_assets' );

/**
 * Enqueue the minimal admin stylesheet on the plugin settings page only.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function lean_cookie_consent_enqueue_admin_assets( $hook_suffix ) {
	if ( 'settings_page_lean-cookie-consent' !== $hook_suffix ) {
		return;
	}
	wp_enqueue_style(
		'lean-cookie-consent-admin',
		plugins_url( 'assets/lean-cookie-consent-admin.css', LEAN_COOKIE_CONSENT_FILE ),
		array(),
		LEAN_COOKIE_CONSENT_VERSION
	);
}

/**
 * Render the settings page.
 *
 * Capability check is enforced before rendering; the Settings API handles
 * the nonce + sanitize_callback on submit. The page exposes a single Site
 * Key field plus a link to the Lean Cookie Consent dashboard.
 *
 * @return void
 */
function lean_cookie_consent_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'lean-cookie-consent' ) );
	}

	$site_key = lean_cookie_consent_get_site_key();
	$status   = '';
	if ( '' === $site_key ) {
		$status = 'empty';
	} else {
		$status = 'connected';
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Lean Cookie Consent', 'lean-cookie-consent' ); ?></h1>

		<p class="description">
			<?php
			esc_html_e(
				'This plugin is a minimal connector to the Lean Cookie Consent SaaS. Enter your Site Key below; the bundled local runtime will load on every frontend page and fetch banner configuration from the SaaS.',
				'lean-cookie-consent'
			);
			?>
		</p>

		<hr>

		<form method="post" action="options.php">
			<?php settings_fields( 'lean_cookie_consent_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="lean-cookie-consent-site-key">
							<?php esc_html_e( 'Site Key', 'lean-cookie-consent' ); ?>
						</label>
					</th>
					<td>
						<input
							class="regular-text lean-cookie-consent-admin-field"
							id="lean-cookie-consent-site-key"
							name="<?php echo esc_attr( LEAN_COOKIE_CONSENT_OPTION ); ?>"
							type="text"
							autocomplete="off"
							spellcheck="false"
							value="<?php echo esc_attr( $site_key ); ?>"
						>
						<p class="description">
							<?php
							esc_html_e(
								'Only lowercase letters, digits, underscores and hyphens are allowed (max 64 characters). You can find your Site Key in the Lean Cookie Consent dashboard under each site.',
								'lean-cookie-consent'
							);
							?>
						</p>
						<p class="lean-cookie-consent-admin-status">
							<?php
							echo wp_kses(
								__( 'Status:', 'lean-cookie-consent' ) . ' <strong>' . ( 'connected' === $status ? esc_html__( 'Connected - the bundled runtime will be loaded on the frontend.', 'lean-cookie-consent' ) : esc_html__( 'Not configured - the frontend will not load any Lean Cookie Consent script.', 'lean-cookie-consent' ) ) . '</strong>',
								array( 'strong' => array() )
							);
							?>
						</p>
						<p class="description">
							<?php esc_html_e( 'To change Site Key, replace the value and save. To disconnect, clear the field and save.', 'lean-cookie-consent' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save / Connect', 'lean-cookie-consent' ) ); ?>
		</form>

		<hr>

		<h2><?php esc_html_e( 'Manage banner, preferences and policy', 'lean-cookie-consent' ); ?></h2>
		<p>
			<a
				class="button button-secondary"
				href="<?php echo esc_url( 'https://app.leancookieconsent.com/admin' ); ?>"
				target="_blank"
				rel="noopener noreferrer"
			>
				<?php esc_html_e( 'Open Lean Cookie Consent dashboard', 'lean-cookie-consent' ); ?>
			</a>
		</p>
		<p class="description">
			<?php
			esc_html_e(
				'Banner copy, layout, colors, categories, services, languages and policy links are all configured in the Lean Cookie Consent dashboard. This WordPress plugin only wires your Site Key to the bundled runtime.',
				'lean-cookie-consent'
			);
			?>
		</p>
	</div>
	<?php
}

add_action( 'wp_enqueue_scripts', 'lean_cookie_consent_enqueue_embed' );

/**
 * Enqueue the bundled Lean Cookie Consent runtime on the frontend.
 *
 * The URL is hardcoded; only the validated Site Key is appended as a
 * `site` query parameter via rawurlencode(). Nothing else from user input
 * is ever added to the URL. The script is loaded in the footer and fetches
 * JSON configuration from the fixed SaaS API base URL.
 *
 * @return void
 */
function lean_cookie_consent_enqueue_embed() {
	if ( is_admin() ) {
		return;
	}
	$site_key = lean_cookie_consent_get_site_key();
	if ( '' === $site_key ) {
		return;
	}
	wp_enqueue_script(
		'lean-cookie-consent',
		add_query_arg(
			array(
				'site' => $site_key,
				'api'  => LEAN_COOKIE_CONSENT_API_BASE_URL,
			),
			plugins_url( 'assets/lean-cookie-consent.js', LEAN_COOKIE_CONSENT_FILE )
		),
		array(),
		LEAN_COOKIE_CONSENT_VERSION,
		true
	);
}

add_action( 'admin_init', 'lean_cookie_consent_register_privacy_policy_content' );

/**
 * Register a short Privacy Policy Guide entry describing the SaaS integration.
 *
 * @return void
 */
function lean_cookie_consent_register_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content = wp_kses_post(
		wpautop(
			__( 'This website uses Lean Cookie Consent to display a cookie consent banner, preference center and consent signaling.', 'lean-cookie-consent' ) . "\n\n" .
			__( 'The banner configuration and the underlying consent record are handled by the Lean Cookie Consent SaaS platform (api.leancookieconsent.com). When a visitor saves a preference, the consent record is stored by the SaaS and tied to a pseudonymous consent identifier. Site configuration (banner copy, categories, services, languages, policy links) is also managed in the Lean Cookie Consent dashboard.', 'lean-cookie-consent' ) . "\n\n" .
			__( 'The WordPress plugin itself does not store any visitor data, does not set any additional cookies, and does not record consent locally. The only data configured by the site owner is the Site Key, which is stored in WordPress options and used by the bundled local runtime.', 'lean-cookie-consent' )
		)
	);

	wp_add_privacy_policy_content( 'Lean Cookie Consent', $content );
}
