<?php
/**
 * Plugin Name: WP Portfolio
 * Plugin URI: https://wpportfolio.net/
 * Description: Display the portfolio of Starter Templates & other portfolio items easily on your website.
 * Version: 1.13.6
 * Requires at least: 6.6
 * Author: Brainstorm Force
 * Author URI: http://www.brainstormforce.com
 * Text Domain: astra-portfolio
 *
 * @package Astra Portfolio
 */

/**
 * Set constants.
 */
define( 'ASTRA_PORTFOLIO_VER', '1.13.6' );
define( 'ASTRA_PORTFOLIO_FILE', __FILE__ );
define( 'ASTRA_PORTFOLIO_BASE', plugin_basename( ASTRA_PORTFOLIO_FILE ) );
define( 'ASTRA_PORTFOLIO_DIR', plugin_dir_path( ASTRA_PORTFOLIO_FILE ) );
define( 'ASTRA_PORTFOLIO_URI', plugins_url( '/', ASTRA_PORTFOLIO_FILE ) );

require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio.php';

// Brainstorm Updater.
require_once ASTRA_PORTFOLIO_DIR . 'class-brainstorm-updater-astra-portfolio.php';

// Astra Notices.
require_once ASTRA_PORTFOLIO_DIR . 'admin/astra-notices/class-astra-notices.php';

// BSF Analytics Tracker.
if ( ! class_exists( 'BSF_Analytics_Loader' ) ) {
	require_once ASTRA_PORTFOLIO_DIR . 'admin/bsf-analytics/class-bsf-analytics-loader.php';
}

$bsf_analytics = BSF_Analytics_Loader::get_instance();

$bsf_analytics->set_entity(
	array(
		'astra-portfolio' => array(
			'product_name'    => 'WP Portfolio',
			'path'            => ASTRA_PORTFOLIO_DIR . 'admin/bsf-analytics',
			'author'          => 'Brainstorm Force',
			'time_to_display' => '+24 hours',
			'deactivation_survey' => array(
				array(
					'id'                => 'deactivation-survey-astra-portfolio', // 'deactivation-survey-<your-plugin-slug>'
					'popup_logo'        => ASTRA_PORTFOLIO_URI . 'assets/images/wp-portfolio-logo.svg',
					'plugin_slug'       => 'astra-portfolio', // <your-plugin-slug>
					'plugin_version'    => ASTRA_PORTFOLIO_VER,
					'popup_title'       => 'Quick Feedback',
					'support_url'       => 'https://wpportfolio.net/contact/',
					'popup_description' => 'If you have a moment, please share why you are deactivating WP Portfolio:',
					'show_on_screens'   => array( 'plugins' ),
				),
			),
			'hide_optin_checkbox' => true,
		),
	)
);

// Remove Astra Portfolio taxonomies from WordPress sitemaps
add_filter('wp_sitemaps_taxonomies', function($taxonomies) {
    unset($taxonomies['astra-portfolio-categories']);
    unset($taxonomies['astra-portfolio-other-categories']); 
    return $taxonomies;
});
