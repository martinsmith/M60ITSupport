<?php
/**
 * Widget Deactivate Handler.
 *
 * Disables a specific widget by slug.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Widget_Deactivate_Handler
 *
 * Implements UAEL_Ability_Handler for the widgets/deactivate ability.
 *
 * @since 1.45.0
 */
class UAEL_Widget_Deactivate_Handler implements UAEL_Ability_Handler {

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'widgets-deactivate';
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
			'label'               => __( 'Deactivate Widget', 'uael' ),
			'description'         => __( 'Disable a specific widget by slug.', 'uael' ),
			'category'            => 'uael-widgets',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'widget_slug' ),
				'properties' => array(
					'widget_slug' => array(
						'type'        => 'string',
						'description' => __( 'Widget slug to deactivate.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'widget_slug' => array( 'type' => 'string' ),
					'message'     => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Confirm the widget name with the user before deactivating. Check widgets/get-usage first to verify it is not in use on the frontend.',
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @since 1.45.0
	 *
	 * @param array $input Input with widget_slug.
	 * @return array|WP_Error Result or error.
	 */
	public function execute( $input ) {
		$widget_key = sanitize_text_field( $input['widget_slug'] );

		if ( empty( $widget_key ) ) {
			return new WP_Error(
				'uael_invalid_widget',
				__( 'Widget key is required.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		// Verify widget exists.
		$widget_list = \UltimateElementor\Classes\UAEL_Helper::get_widget_list();

		if ( ! isset( $widget_list[ $widget_key ] ) ) {
			return new WP_Error(
				'uael_invalid_widget',
				/* translators: %s: widget slug */
				sprintf( __( 'Widget "%s" not found.', 'uael' ), $widget_key ),
				array( 'status' => 404 )
			);
		}

		$widgets                = \UltimateElementor\Classes\UAEL_Helper::get_admin_settings_option( '_uael_widgets', array() );
		$widgets[ $widget_key ] = 'disabled';
		$widgets                = array_map( 'esc_attr', $widgets );

		\UltimateElementor\Classes\UAEL_Helper::update_admin_settings_option( '_uael_widgets', $widgets );
		\UltimateElementor\Classes\UAEL_Helper::create_specific_stylesheet();

		return array(
			'success'     => true,
			'widget_slug' => $widget_key,
			'message'     => sprintf(
				/* translators: %s: widget title */
				__( 'Widget "%s" deactivated successfully.', 'uael' ),
				isset( $widget_list[ $widget_key ]['title'] ) ? $widget_list[ $widget_key ]['title'] : $widget_key
			),
		);
	}
}
