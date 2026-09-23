<?php
/**
 *
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.leadconnectorhq.com
 * @since             1.0.0
 * @package           LeadConnector
 *
 * @wordpress-plugin
 * Plugin Name:       LeadConnector
 * Plugin URI:        https://www.leadconnectorhq.com/
 * Description:       This plugin helps you to add the lead connector widgets to your website.
 * Version:           4.0.6
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            LeadConnector
 * Author URI:        https://www.leadconnectorhq.com
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       leadconnector
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */

if ( file_exists( __DIR__ . '/config.php' ) ) {
	require_once __DIR__ . '/config.php';
} else {
	wp_die(
		esc_html__( 'LeadConnector: config.php is missing. Please re-install the plugin.', 'leadconnector' )
	);
}

// Constants That are required by plugin that are not part of the default config.php.
define( 'LEAD_CONNECTOR_OAUTH_CALLBACK_URL', get_option( 'siteurl', '__missing_siteurl__' ) );


/**
 * Add custom query variables to WordPress.
 *
 * @param array $vars Existing query variables.
 * @return array
 */
function leadconnector_register_query_vars( $vars ) {
	$vars[] = 'leadconnector_token';
	$vars[] = 'elementor_highlight';
	return $vars;
}
add_filter( 'query_vars', 'leadconnector_register_query_vars' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-leadconnector-activator.php
 */
function leadconnector_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-leadconnector-constants.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-leadconnector-activator.php';
	LeadConnector_Activator::activate();

	leadconnector_register_query_vars( array() );
	flush_rewrite_rules();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-leadconnector-deactivator.php
 */
function leadconnector_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-leadconnector-deactivator.php';
	LeadConnector_Deactivator::deactivate();

	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'leadconnector_activate' );
register_deactivation_hook( __FILE__, 'leadconnector_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-leadconnector.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-leadconnector-update-functions.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-leadconnector-ai-page-preview.php';

// Add Elementor CLI.
require plugin_dir_path( __FILE__ ) . 'includes/class-leadconnector-elementor-cli.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-leadconnector-preview-cli.php';
require plugin_dir_path( __FILE__ ) . 'includes/SeoOverrides/class-leadconnector-seo-overrides.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function leadconnector_run() {
	$plugin = new LeadConnector();
	$plugin->run();
}
leadconnector_run();


// M8: uninstall.php is the authoritative uninstall handler; register_uninstall_hook()
// is superseded when uninstall.php exists and has been removed to avoid duplication.

/**
 * Enqueue Elementor CSS overrides for pages built with Elementor.
 *
 * @return void
 */
function leadconnector_enqueue_elementor_overrides() {
	if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}

	$post_id = get_the_ID();
	if ( ! $post_id || ! get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
		return;
	}

	wp_register_style(
		'leadconnector-elementor-overrides',
		plugin_dir_url( __FILE__ ) . 'public/css/custom-elementor.css',
		array(),
		'1.0.0'
	);

	wp_enqueue_style( 'leadconnector-elementor-overrides' );
}
add_action( 'wp_enqueue_scripts', 'leadconnector_enqueue_elementor_overrides' );

/**
 * Load theme fixes CSS on all frontend pages
 * Fixes responsive header padding for block themes
 */
function leadconnector_enqueue_theme_fixes() {
	// L4: Only enqueue on pages that actually display LeadConnector funnel/page content.
	// Loading this CSS unconditionally adds a network request to every frontend page,
	// including posts and archives that have nothing to do with LC. We check for an
	// LC funnel step URL stored in the current post's meta as the gate.
	if ( ! is_singular() ) {
		return;
	}
	$post_id = get_the_ID();
	if ( ! $post_id || ! get_post_meta( $post_id, 'leadconnector_step_url', true ) ) {
		return;
	}

	$css_file = plugin_dir_path( __FILE__ ) . 'public/css/theme-fixes.css';
	$version  = file_exists( $css_file ) ? filemtime( $css_file ) : LEAD_CONNECTOR_VERSION;

	wp_enqueue_style(
		'leadconnector-theme-fixes',
		plugin_dir_url( __FILE__ ) . 'public/css/theme-fixes.css',
		array(),
		$version
	);
}
add_action( 'wp_enqueue_scripts', 'leadconnector_enqueue_theme_fixes' );
