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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.leadconnectorhq.com
 * @since      1.0.0
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    LeadConnector
 * @subpackage LeadConnector/includes
 */
class LeadConnector {


	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      LeadConnector_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'LEAD_CONNECTOR_VERSION' ) ) {
			$this->version = LEAD_CONNECTOR_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = LEAD_CONNECTOR_PLUGIN_NAME;

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - LeadConnector_Loader. Orchestrates the hooks of the plugin.
	 * - LeadConnector_Admin. Defines all hooks for the admin area.
	 * - LeadConnector_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-leadconnector-loader.php';

		require_once plugin_dir_path( __DIR__ ) . 'admin/class-leadconnector-data-encryption.php';

		/** Inbound input validation + input DTO definitions (load before admin/public handlers that use them). */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-leadconnector-abstract-input-dto.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-leadconnector-input-validator.php';
		// `class-leadconnector-input-dto-custom-values-save.php` was deleted in
		// 3.0.32: its DTO only bound to the
		// `/leadconnector_internal_api/v1/save_custom_values` REST route,
		// which was removed alongside the auth-cookie-forwarding loopback
		// it served (replaced by `wp_schedule_single_event()` in
		// `LeadConnector_Admin::leadconnector_save_custom_values()`).
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-leadconnector-rest-input-guard.php';
		LeadConnector_REST_Input_Guard::init();

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-leadconnector-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-leadconnector-public.php';

		$this->loader = new LeadConnector_Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new LeadConnector_Admin( $this->get_plugin_name(), $this->get_version() );

		// C1: register the admin-notice / admin-head / dashicons hooks exactly
		// once from the canonical bootstrap instance. The constructor used to
		// do this implicitly, which caused N-times duplicate hook registration
		// every time a secondary call site (cron callback, CustomValues OAuth
		// helper, native template handler) instantiated the admin class on the
		// same request. `register_hooks()` is idempotent via a static guard.
		$plugin_admin->register_hooks();

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_admin, 'enqueue_cdn_script_frontend' );

		$this->loader->add_action( 'rest_api_init', $plugin_admin, 'admin_rest_api_init' );
		$this->loader->add_action( 'init', $plugin_admin, 'register_custom_post' );

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'wporg_options_page' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'template_redirect', $plugin_admin, 'process_page_request', 1, 2 );

		$this->loader->add_action( 'save_post', $plugin_admin, 'maybe_clear_all_caches', 10, 2 );
		$this->loader->add_action( 'save_post', $plugin_admin, 'sync_native_funnel_index_on_save', 20, 2 );
		$this->loader->add_action( 'before_delete_post', $plugin_admin, 'sync_native_funnel_index_on_delete', 10, 1 );
		$this->loader->add_action( 'before_delete_post', $plugin_admin, 'schedule_sync_ai_page_deleted_from_wp', 10, 1 );
		$this->loader->add_action( 'wp_trash_post', $plugin_admin, 'schedule_sync_ai_page_deleted_from_wp', 10, 1 );
		$this->loader->add_action( 'transition_post_status', $plugin_admin, 'schedule_sync_ai_page_status_from_wp', 10, 3 );
		$this->loader->add_action( 'added_post_meta', $plugin_admin, 'sync_native_funnel_index_on_meta_change', 10, 4 );
		$this->loader->add_action( 'updated_post_meta', $plugin_admin, 'sync_native_funnel_index_on_meta_change', 10, 4 );
		$this->loader->add_action( 'deleted_post_meta', $plugin_admin, 'sync_native_funnel_index_on_meta_change', 10, 4 );
		$this->loader->add_action( 'transition_post_status', $plugin_admin, 'sync_native_funnel_index_on_status_change', 10, 3 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new LeadConnector_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return 'LeadConnector';
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Plugin_Name_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
