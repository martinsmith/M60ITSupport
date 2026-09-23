<?php
/**
 * Pro Info Widget Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Pro_Info_Widget_Handler
 *
 * @since 1.45.0
 */
class UAEL_Pro_Info_Widget_Handler implements UAEL_Ability_Handler {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'pro-info-widget';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Widget Details', 'uael' ),
			'description'         => __( 'Get detailed information about a specific UAE widget by its key.', 'uael' ),
			'category'            => 'uael-info',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'widget_key' => array(
						'type'        => 'string',
						'description' => __( 'The widget class key, e.g., "FAQ", "Modal_Popup", "Woo_Products".', 'uael' ),
					),
				),
				'required'   => array( 'widget_key' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'widget'  => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
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
	 * Execute: Get single widget details.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$widget_key = isset( $input['widget_key'] ) ? sanitize_text_field( $input['widget_key'] ) : '';

		if ( empty( $widget_key ) ) {
			return array(
				'success' => false,
				'widget'  => null,
				'message' => __( 'Widget key is required.', 'uael' ),
			);
		}

		$widgets = \UltimateElementor\Classes\UAEL_Helper::get_widget_options();

		if ( ! isset( $widgets[ $widget_key ] ) ) {
			return array(
				'success' => false,
				'widget'  => null,
				'message' => sprintf(
					/* translators: %s: widget key */
					__( 'Widget "%s" not found.', 'uael' ),
					$widget_key
				),
			);
		}

		$widget = $widgets[ $widget_key ];

		return array(
			'success' => true,
			'widget'  => array(
				'key'         => $widget_key,
				'slug'        => isset( $widget['slug'] ) ? $widget['slug'] : '',
				'title'       => isset( $widget['title'] ) ? $widget['title'] : $widget_key,
				'description' => isset( $widget['description'] ) ? $widget['description'] : '',
				'category'    => isset( $widget['category'] ) ? $widget['category'] : '',
				'is_active'   => ! empty( $widget['is_activate'] ),
				'is_pro'      => isset( $widget['is_pro'] ) ? (bool) $widget['is_pro'] : false,
				'doc_url'     => isset( $widget['doc_url'] ) ? $widget['doc_url'] : '',
				'demo_url'    => isset( $widget['demo_url'] ) ? $widget['demo_url'] : '',
			),
			'message' => __( 'Widget found.', 'uael' ),
		);
	}
}
