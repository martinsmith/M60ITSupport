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
 * Registers the admin sidebar menu and sub-menu pages for LeadConnector.
 *
 * @package LeadConnector
 */
class LeadConnector_Menu_Handler {
	/**
	 * Display name shown in the admin menu.
	 *
	 * @var string
	 */
	private $plugin_display_name = LEAD_CONNECTOR_DISPLAY_NAME;

	/**
	 * Default slug used for the top-level menu page.
	 *
	 * @var string
	 */
	private $plugin_default_slug = 'leadconnector-plugin';

	/**
	 * Callback function name that renders the settings page.
	 *
	 * @var string
	 */
	private $render_area_callback = 'leadconnector_render_plugin_settings_page';

	/**
	 * Location ID of the connected site, or null when unset.
	 *
	 * @var string|null
	 */
	private static $location_id = null;

	/**
	 * Whether sub-menu items should be rendered.
	 *
	 * @var bool
	 */
	private $render_sub_menu_items = false;

	/**
	 * WordPress capabilities required for menu access.
	 *
	 * @var array<string, string>
	 */
	private $capabilities = array(
		'manage_options' => 'manage_options',
	);

	/**
	 * Dashicon identifiers for menu items.
	 *
	 * @var array<string, string>
	 */
	private $icons = array(
		'dashicons-dashboard' => 'dashicons-admin-links',
	);

	/**
	 * Sub-menu item definitions populated by init_submenu_items().
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $sub_menu_items;

	/**
	 * Initialize the submenu item definitions.
	 *
	 * @return void
	 */
	private function init_submenu_items() {

		$this->sub_menu_items = array(
			'dashboard'      => array(
				'title'       => __( 'Dashboard', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 10,
				'is_external' => false,
			),
			'funnels'        => array(
				'title'       => __( 'Funnels', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 20,
				'is_external' => false,
			),
			'forms'          => array(
				'title'       => __( 'Forms', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 30,
				'is_external' => false,
			),
			'emails'         => array(
				'title'       => __( 'Email', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 30,
				'is_external' => false,
			),
			'phone'          => array(
				'title'       => __( 'Phone Numbers', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 30,
				'is_external' => false,
			),
			'chat_widget'    => array(
				'title'       => __( 'Chat Widget', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 30,
				'is_external' => false,
			),
			'custom_values'  => array(
				'title'       => __( 'Custom Values', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 35,
				'is_external' => false,
			),
			'calendar'       => array(
				'title'       => __( 'Calendar', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 40,
				'is_external' => false,
			),
			'reviews_widget' => array(
				'title'       => __( 'Reviews Widget', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 40,
				'is_external' => false,
			),
			'surveys'        => array(
				'title'       => __( 'Surveys', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 40,
				'is_external' => false,
			),
			'quizzes'        => array(
				'title'       => __( 'Quizzes', 'leadconnector' ),
				'capability'  => $this->capabilities['manage_options'],
				'icon'        => $this->icons['dashicons-dashboard'],
				'position'    => 50,
				'is_external' => false,
			),
			'contacts'       => array(
				'title'         => __( 'Contacts', 'leadconnector' ),
				'capability'    => $this->capabilities['manage_options'],
				'icon'          => $this->icons['dashicons-dashboard'],
				'position'      => 10,
				'is_external'   => true,
				'external_link' => lead_connector_constants\LEADCONNECTOR_BASE_URL . '/location/' . self::$location_id . '/contacts/smart_list/All',
			),
		);
	}

	/**
	 * Initialize the Menu Handler and Add the Menu Items
	 */
	public function __construct() {
		// Store Location Id.
		$options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		if ( ! isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ) {
			self::$location_id           = null;
			$this->render_sub_menu_items = false;
		} else {
			self::$location_id = $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ];
			if ( '' !== self::$location_id ) {
				$this->render_sub_menu_items = true;
			}
		}
		// Intialize the Sub Menu Items.
		$this->init_submenu_items();
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 ); // Adding 20 as priority to make sure it is added after the default menu items.
	}

	/**
	 * Makes External Links for the Plugin.
	 *
	 * @param string $href      Link URL.
	 * @param string $content   Link text.
	 * @param string $css_class CSS class name.
	 * @return string
	 */
	public function make_external_link( string $href, string $content, string $css_class ) {
		$link_allowed = array(
			'span' => array(
				'class'     => true,
				'data-href' => true,
			),
		);
		return wp_kses(
			'<span class="' . esc_attr( $css_class ) . '" data-href="' . esc_url( $href ) . '">' . esc_html( $content ) . '<span class="dashicons dashicons-external"></span></span>',
			$link_allowed
		);
	}

	/**
	 * Register the Menu Items, Adds the Main Menu Item and then loops through the sub menu items and adds them
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			esc_html( $this->plugin_display_name ),
			esc_html( $this->plugin_display_name ),
			$this->capabilities['manage_options'],
			'leadconnector-plugin',
			$this->render_area_callback,
			plugins_url( 'admin/images/icon.png', __DIR__ ),
			'58.5'
		);

		// Remove the Default Submenu Page for the Plugin.
		add_action(
			'admin_menu',
			function () {
				remove_submenu_page( 'leadconnector-plugin', 'leadconnector-plugin' );
			},
			99
		);

		if ( $this->render_sub_menu_items ) {
			// Add Sub Menu Items.
			foreach ( $this->sub_menu_items as $slug => $item ) {
				$this->add_submenu_items( $slug, $item );
			}
		}
	}

	/**
	 * Adds the Sub Menu Items to the Plugin
	 *
	 * @param mixed $parent_slug Primary slug for the plugin.
	 * @param mixed $item        Sub menu item defined in init_submenu_items.
	 * @return void
	 */
	private function add_submenu_items( $parent_slug, $item ) {
		if ( $item['is_external'] ) {
			$page_slug = $item['external_link'];
			$callback  = '';
		} else {
			$page_slug = 'leadconnector-plugin&tab=' . $parent_slug;
			$callback  = $this->render_area_callback;
		}
		$submenu_title = esc_html( $item['title'] );
		add_submenu_page(
			'leadconnector-plugin',
			$submenu_title,
			( 1 === (int) $item['is_external'] ? $this->make_external_link( $item['external_link'], $submenu_title, 'external_link' ) : $submenu_title ),
			$item['capability'],
			$page_slug,
			$callback,
		);
	}
}
