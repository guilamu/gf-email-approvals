<?php
/**
 * Plugin Name: Email Approvals for Gravity Forms
 * Plugin URI: https://github.com/guilamu/gf-email-approvals
 * Description: Adds email-based approval notifications to Gravity Forms entries.
 * Version: 0.2.0
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * License: AGPL-3.0
 * Text Domain: gf-email-approvals
 * Domain Path: /languages
 * Update URI: https://github.com/guilamu/gf-email-approvals/
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'GF_EMAIL_APPROVALS_VERSION', '0.2.0' );
define( 'GF_EMAIL_APPROVALS_FILE', __FILE__ );
define( 'GF_EMAIL_APPROVALS_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_EMAIL_APPROVALS_URL', plugin_dir_url( __FILE__ ) );
define( 'GF_EMAIL_APPROVALS_SLUG', 'gf-email-approvals' );
define( 'GF_EMAIL_APPROVALS_NAME', 'Email Approvals for Gravity Forms' );
define( 'GF_EMAIL_APPROVALS_GITHUB_REPO', 'guilamu/gf-email-approvals' );

require_once GF_EMAIL_APPROVALS_PATH . 'includes/class-github-updater.php';

register_activation_hook( __FILE__, 'gf_email_approvals_activate' );

add_filter( 'plugin_row_meta', 'gf_email_approvals_plugin_row_meta', 10, 2 );
add_action( 'init', 'gf_email_approvals_load_textdomain' );
add_action( 'plugins_loaded', 'gf_email_approvals_register_bug_reporter', 20 );
add_action( 'admin_notices', 'gf_email_approvals_missing_gravity_forms_notice' );

add_action( 'gform_loaded', array( 'GF_Email_Approvals_Bootstrap', 'load' ), 5 );

/**
 * Loads the add-on once Gravity Forms is ready.
 */
class GF_Email_Approvals_Bootstrap {
	/**
	 * Registers the add-on class with Gravity Forms.
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		require_once GF_EMAIL_APPROVALS_PATH . 'includes/class-gf-email-approvals-token-store.php';
		require_once GF_EMAIL_APPROVALS_PATH . 'includes/class-gf-email-approvals-addon.php';

		GFAddOn::register( 'GFEmailApprovalsAddon' );
	}
}

/**
 * Blocks activation when Gravity Forms is not available.
 *
 * @return void
 */
function gf_email_approvals_activate() {
	if ( gf_email_approvals_has_gravity_forms() ) {
		return;
	}

	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	deactivate_plugins( plugin_basename( GF_EMAIL_APPROVALS_FILE ) );

	wp_die(
		esc_html__( 'Email Approvals for Gravity Forms requires Gravity Forms to be installed and active.', 'gf-email-approvals' ),
		esc_html__( 'Plugin dependency missing', 'gf-email-approvals' ),
		array( 'back_link' => true )
	);
}

/**
 * Returns whether Gravity Forms is available for this plugin.
 *
 * @return bool
 */
function gf_email_approvals_has_gravity_forms() {
	return class_exists( 'GFForms' ) && method_exists( 'GFForms', 'include_addon_framework' );
}

/**
 * Loads plugin translations from the languages directory.
 *
 * @return void
 */
function gf_email_approvals_load_textdomain() {
	load_plugin_textdomain(
		'gf-email-approvals',
		false,
		dirname( plugin_basename( GF_EMAIL_APPROVALS_FILE ) ) . '/languages/'
	);
}

/**
 * Displays an admin notice when Gravity Forms is missing.
 *
 * @return void
 */
function gf_email_approvals_missing_gravity_forms_notice() {
	if ( gf_email_approvals_has_gravity_forms() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'Email Approvals for Gravity Forms requires Gravity Forms to be installed and active.', 'gf-email-approvals' )
		. '</p></div>';
}

/**
 * Registers the plugin with Guilamu Bug Reporter when available.
 *
 * @return void
 */
function gf_email_approvals_register_bug_reporter() {
	if ( ! class_exists( 'Guilamu_Bug_Reporter' ) ) {
		return;
	}

	Guilamu_Bug_Reporter::register(
		array(
			'slug'        => GF_EMAIL_APPROVALS_SLUG,
			'name'        => GF_EMAIL_APPROVALS_NAME,
			'version'     => GF_EMAIL_APPROVALS_VERSION,
			'github_repo' => GF_EMAIL_APPROVALS_GITHUB_REPO,
		)
	);
}

/**
 * Adds the WordPress-style plugin details link for the custom updater.
 *
 * @param array  $links Existing plugin meta links.
 * @param string $file  Current plugin file.
 *
 * @return array
 */
function gf_email_approvals_plugin_row_meta( $links, $file ) {
	if ( plugin_basename( GF_EMAIL_APPROVALS_FILE ) !== $file ) {
		return $links;
	}

	$links[] = sprintf(
		'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
		esc_url( self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . GF_EMAIL_APPROVALS_SLUG . '&TB_iframe=true&width=772&height=926' ) ),
		esc_attr__( 'More information about Email Approvals for Gravity Forms', 'gf-email-approvals' ),
		esc_attr( GF_EMAIL_APPROVALS_NAME ),
		esc_html__( 'View details', 'gf-email-approvals' )
	);

	if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
		$links[] = sprintf(
			'<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="%s" data-plugin-name="%s">%s</a>',
			esc_attr( GF_EMAIL_APPROVALS_SLUG ),
			esc_attr( GF_EMAIL_APPROVALS_NAME ),
			esc_html__( 'Report a Bug', 'gf-email-approvals' )
		);
	} else {
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/guilamu/guilamu-bug-reporter/releases' ),
			esc_html__( 'Report a Bug (install Bug Reporter)', 'gf-email-approvals' )
		);
	}

	return $links;
}

/**
 * Returns the singleton instance.
 *
 * @return GFEmailApprovalsAddon|null
 */
function gf_email_approvals() {
	if ( ! class_exists( 'GFEmailApprovalsAddon' ) ) {
		return null;
	}

	return GFEmailApprovalsAddon::get_instance();
}