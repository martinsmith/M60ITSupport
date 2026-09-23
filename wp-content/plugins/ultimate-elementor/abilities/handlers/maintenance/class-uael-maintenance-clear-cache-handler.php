<?php
/**
 * Maintenance Clear Cache Handler.
 *
 * Clears the Elementor CSS cache globally.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Maintenance_Clear_Cache_Handler
 *
 * Implements UAEL_Ability_Handler for the maintenance/clear-cache ability.
 *
 * @since 1.45.0
 */
class UAEL_Maintenance_Clear_Cache_Handler implements UAEL_Ability_Handler {

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'maintenance-clear-cache';
	}

	/**
	 * Get the wp_register_ability() args array.
	 *
	 * Does NOT include execute_callback -- the registry sets that automatically.
	 *
	 * @since 1.45.0
	 *
	 * @return array Ability registration args.
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Clear Elementor Cache', 'uael' ),
			'description'         => __( 'Clears the Elementor CSS cache globally to regenerate all stylesheets.', 'uael' ),
			'category'            => 'uael-maintenance',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Use this after making design or template changes to regenerate Elementor CSS files. Safe to call multiple times.',
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * Clears the Elementor CSS cache via the files manager.
	 *
	 * @since 1.45.0
	 *
	 * @param array $input Unused input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public function execute( $input ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error(
				'uael_elementor_not_active',
				__( 'Elementor is not active.', 'uael' ),
				array( 'status' => 500 )
			);
		}

		\Elementor\Plugin::$instance->files_manager->clear_cache();

		return array(
			'success' => true,
			'message' => __( 'Elementor CSS cache cleared successfully.', 'uael' ),
		);
	}
}
