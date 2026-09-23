<?php
/**
 * Widget Bulk Toggle Handler.
 *
 * Activates or deactivates all widgets in a single operation.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Widget_Bulk_Toggle_Handler
 *
 * Implements UAEL_Ability_Handler for the widgets/bulk-toggle ability.
 *
 * @since 1.45.0
 */
class UAEL_Widget_Bulk_Toggle_Handler implements UAEL_Ability_Handler {

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'widgets-bulk-toggle';
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
			'label'               => __( 'Bulk Toggle All Widgets', 'uael' ),
			'description'         => __( 'Activate or deactivate all widgets at once. When deactivating, all widgets are disabled which may break the frontend.', 'uael' ),
			'category'            => 'uael-widgets',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'action' ),
				'properties' => array(
					'action' => array(
						'type'        => 'string',
						'enum'        => array( 'activate', 'deactivate' ),
						'description' => __( 'Whether to activate or deactivate all widgets.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'action'  => array( 'type' => 'string' ),
					'count'   => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => true,
					'idempotent'   => true,
					'instructions' => 'Warn user before deactivating all.',
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
	 * @param array $input Input with action ('activate' or 'deactivate').
	 * @return array Result with action performed and count of affected widgets.
	 */
	public function execute( $input ) {
		$action      = sanitize_text_field( $input['action'] );
		$widget_list = \UltimateElementor\Classes\UAEL_Helper::get_widget_list();
		$new_widgets = array();

		if ( 'activate' === $action ) {
			foreach ( $widget_list as $slug => $value ) {
				$new_widgets[ $slug ] = $slug;
			}
		} else {
			foreach ( $widget_list as $slug => $value ) {
				$new_widgets[ $slug ] = 'disabled';
			}
		}

		$count       = count( $new_widgets );
		$new_widgets = array_map( 'esc_attr', $new_widgets );

		\UltimateElementor\Classes\UAEL_Helper::update_admin_settings_option( '_uael_widgets', $new_widgets );
		\UltimateElementor\Classes\UAEL_Helper::create_specific_stylesheet();

		return array(
			'success' => true,
			'action'  => $action,
			'count'   => $count,
			'message' => 'activate' === $action
				/* translators: %d: number of widgets */
				? sprintf( __( '%d widgets activated successfully.', 'uael' ), $count )
				/* translators: %d: number of widgets */
				: sprintf( __( '%d widgets deactivated successfully.', 'uael' ), $count ),
		);
	}
}
