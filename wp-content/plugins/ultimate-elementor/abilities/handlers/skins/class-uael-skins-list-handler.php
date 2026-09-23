<?php
/**
 * Skins List Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Skins_List_Handler
 *
 * @since 1.45.0
 */
class UAEL_Skins_List_Handler implements UAEL_Ability_Handler {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'skins-list';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'List Post Skins', 'uael' ),
			'description'         => __( 'List all available post skins with their activation status.', 'uael' ),
			'category'            => 'uael-skins',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'skins'   => array( 'type' => 'array' ),
					'total'   => array( 'type' => 'integer' ),
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
	 * Execute: List post skins.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$skins = \UltimateElementor\Classes\UAEL_Helper::get_post_skin_options();

		if ( ! is_array( $skins ) ) {
			return array(
				'success' => false,
				'skins'   => array(),
				'total'   => 0,
			);
		}

		$result = array();

		foreach ( $skins as $key => $skin ) {
			$result[] = array(
				'key'       => $key,
				'slug'      => isset( $skin['slug'] ) ? $skin['slug'] : '',
				'title'     => isset( $skin['title'] ) ? $skin['title'] : $key,
				'is_active' => ! empty( $skin['is_activate'] ),
			);
		}

		return array(
			'success' => true,
			'skins'   => $result,
			'total'   => count( $result ),
		);
	}
}
