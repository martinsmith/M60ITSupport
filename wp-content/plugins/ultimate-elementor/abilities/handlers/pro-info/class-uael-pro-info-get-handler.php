<?php
/**
 * Pro Info Get Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Pro_Info_Get_Handler
 *
 * @since 1.45.0
 */
class UAEL_Pro_Info_Get_Handler implements UAEL_Ability_Handler {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'pro-info-get';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Plugin Info', 'uael' ),
			'description'         => __( 'Get UAE Pro plugin information including version, total widget count, active widget count, Elementor compatibility, and available categories.', 'uael' ),
			'category'            => 'uael-info',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'info'    => array( 'type' => 'object' ),
				),
			),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'priority'    => 1.0,
					'destructive' => false,
					'idempotent'  => true,
				),
				'mcp'         => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		);
	}

	/**
	 * Execute: Get plugin info.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$widgets      = \UltimateElementor\Classes\UAEL_Helper::get_widget_options();
		$active_count = 0;
		$categories   = array();

		if ( is_array( $widgets ) ) {
			foreach ( $widgets as $widget ) {
				if ( ! empty( $widget['is_activate'] ) ) {
					$active_count++;
				}
				if ( isset( $widget['category'] ) && ! in_array( $widget['category'], $categories, true ) ) {
					$categories[] = $widget['category'];
				}
			}
		}

		$branding    = \UltimateElementor\Classes\UAEL_Helper::get_white_labels();
		$plugin_name = ! empty( $branding['plugin']['name'] ) ? $branding['plugin']['name'] : __( 'Ultimate Addons for Elementor', 'uael' );

		return array(
			'success' => true,
			'info'    => array(
				'name'               => $plugin_name,
				'version'            => defined( 'UAEL_VER' ) ? UAEL_VER : 'unknown',
				'total_widgets'      => is_array( $widgets ) ? count( $widgets ) : 0,
				'active_widgets'     => $active_count,
				'inactive_widgets'   => ( is_array( $widgets ) ? count( $widgets ) : 0 ) - $active_count,
				'widget_categories'  => $categories,
				'elementor_required' => true,
				'elementor_version'  => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : __( 'Not installed', 'uael' ),
				'wp_version'         => get_bloginfo( 'version' ),
				'hfe_active'         => defined( 'HFE_VER' ),
			),
		);
	}
}
