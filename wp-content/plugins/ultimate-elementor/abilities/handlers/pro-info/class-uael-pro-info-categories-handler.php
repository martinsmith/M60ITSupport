<?php
/**
 * Pro Info Categories Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Pro_Info_Categories_Handler
 *
 * @since 1.45.0
 */
class UAEL_Pro_Info_Categories_Handler implements UAEL_Ability_Handler {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'pro-info-categories';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'List Widget Categories', 'uael' ),
			'description'         => __( 'List all widget categories (content, creative, form, seo, woo, extension, feature) with total and active widget counts per category.', 'uael' ),
			'category'            => 'uael-info',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'categories' => array( 'type' => 'array' ),
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
	 * Execute: List widget categories with counts.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$widgets    = \UltimateElementor\Classes\UAEL_Helper::get_widget_options();
		$categories = array();

		if ( is_array( $widgets ) ) {
			foreach ( $widgets as $widget ) {
				$cat = isset( $widget['category'] ) ? $widget['category'] : 'uncategorized';

				if ( ! isset( $categories[ $cat ] ) ) {
					$categories[ $cat ] = array(
						'name'     => $cat,
						'total'    => 0,
						'active'   => 0,
						'inactive' => 0,
					);
				}

				$categories[ $cat ]['total']++;

				if ( ! empty( $widget['is_activate'] ) ) {
					$categories[ $cat ]['active']++;
				} else {
					$categories[ $cat ]['inactive']++;
				}
			}
		}

		return array(
			'success'    => true,
			'categories' => array_values( $categories ),
		);
	}
}
