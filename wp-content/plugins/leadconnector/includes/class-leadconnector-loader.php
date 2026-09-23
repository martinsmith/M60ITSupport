<?php
/**
 *
 * LeadConnector Plugin
 * Copyright (C) 2020-2026 LeadConnector
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package LeadConnector
 */

/**
 * Register all actions and filters for the plugin
 *
 * @link       https://www.leadconnectorhq.com
 * @since      1.0.0
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/leadconnector-functions.php';

/**
 * Collects and registers all hook callbacks for the plugin.
 *
 * @package LeadConnector
 */
class LeadConnector_Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
	 */
	protected $actions;

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
	 */
	protected $filters;

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 *
	 * The previous implementation also stored copies of `$plugin_name` and
	 * `$version` on the loader; neither was read anywhere in the codebase
	 * (#L6) so they have been removed to clarify the loader's
	 * single-responsibility role of collecting and dispatching hooks.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $hook          The name of the WordPress action that is being registered.
	 * @param    object $component     A reference to the instance of the object on which the action is defined.
	 * @param    string $callback      The name of the function definition on the $component.
	 * @param    int    $priority      Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $hook          The name of the WordPress filter that is being registered.
	 * @param    object $component     A reference to the instance of the object on which the filter is defined.
	 * @param    string $callback      The name of the function definition on the $component.
	 * @param    int    $priority      Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function that is used to register the actions and hooks into a single
	 * collection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $hooks         The collection of hooks that is being registered (that is, actions or filters).
	 * @param    string $hook          The name of the WordPress filter that is being registered.
	 * @param    object $component     A reference to the instance of the object on which the filter is defined.
	 * @param    string $callback      The name of the function definition on the $component.
	 * @param    int    $priority      The priority at which the function should be fired.
	 * @param    int    $accepted_args The number of arguments that should be passed to the $callback.
	 * @return   array   The collection of actions and filters registered with WordPress.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$options = get_option( LEAD_CONNECTOR_OPTION_NAME );

		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED ] ) && 'true' === $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED ] ) {
			add_filter( 'phpmailer_init', 'leadconnector_phpmailer_init_smtp', 10, 1 );
		}

		// New shortcodes (prefixed with 'leadconnector_').
		add_shortcode( 'leadconnector_phone_number_pool', 'leadconnector_shortcode_phone_pool' );
		add_shortcode( 'leadconnector_form', 'leadconnector_shortcode_form' );
		add_shortcode( 'leadconnector_calendar', 'leadconnector_shortcode_calendar' );
		add_shortcode( 'leadconnector_survey', 'leadconnector_shortcode_survey' );
		add_shortcode( 'leadconnector_quiz', 'leadconnector_shortcode_quiz' );
		add_shortcode( 'leadconnector_reviews_widget', 'leadconnector_shortcode_reviews_widget' );

		// Backward-compatibility aliases for existing user content.
		add_shortcode( 'lc_phone_number_pool', 'leadconnector_shortcode_phone_pool' ); // Deprecated : Will be removed in a future version. Check above for the new shortcode.
		add_shortcode( 'lc_form', 'leadconnector_shortcode_form' ); // Deprecated : Will be removed in a future version. Check above for the new shortcode.
		add_shortcode( 'lc_calendar', 'leadconnector_shortcode_calendar' ); // Deprecated : Will be removed in a future version. Check above for the new shortcode.
		add_shortcode( 'lc_survey', 'leadconnector_shortcode_survey' ); // Deprecated : Will be removed in a future version. Check above for the new shortcode.
		add_shortcode( 'lc_quiz', 'leadconnector_shortcode_quiz' ); // Deprecated : Will be removed in a future version. Check above for the new shortcode.
		add_shortcode( 'lc_reviews_widget', 'leadconnector_shortcode_reviews_widget' ); // Deprecated : Will be removed in a future version. Check above for the new shortcode.

		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] ) && '' !== $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] ) {
			add_action( 'init', 'leadconnector_schedule_oauth_refresh_cron' );
		}

		add_action(
			'leadconnector_twicedaily_refresh_req_v2',
			function () {
				$leadconnector_admin = new LeadConnector_Admin( LEAD_CONNECTOR_PLUGIN_NAME, LEAD_CONNECTOR_VERSION );
				$leadconnector_admin->refresh_oauth_token();
			}
		);

		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
