<?php
/**
 * Skins Bulk Toggle Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Skins_Bulk_Toggle_Handler
 *
 * @since 1.45.0
 */
class UAEL_Skins_Bulk_Toggle_Handler implements UAEL_Ability_Handler {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'skins-bulk-toggle';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Bulk Toggle Post Skins', 'uael' ),
			'description'         => __( 'Activate or deactivate all post skins at once.', 'uael' ),
			'category'            => 'uael-skins',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'action' => array(
						'type'        => 'string',
						'description' => __( 'Action to perform: "activate" or "deactivate".', 'uael' ),
						'enum'        => array( 'activate', 'deactivate' ),
					),
				),
				'required'   => array( 'action' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'priority'    => 2.0,
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
	 * Execute: Bulk toggle all post skins.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$action = isset( $input['action'] ) ? sanitize_text_field( $input['action'] ) : '';

		if ( ! in_array( $action, array( 'activate', 'deactivate' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Action must be "activate" or "deactivate".', 'uael' ),
			);
		}

		$post_skins = \UltimateElementor\Classes\UAEL_Helper::get_post_skin_list();
		$widgets    = \UltimateElementor\Classes\UAEL_Helper::get_admin_settings_option( '_uael_widgets', array() );

		if ( ! is_array( $widgets ) ) {
			$widgets = array();
		}

		$value = 'activate' === $action ? null : 'disabled';

		foreach ( $post_skins as $slug => $skin ) {
			$widgets[ $slug ] = null === $value ? $slug : $value;
		}

		$widgets = array_map( 'esc_attr', $widgets );

		\UltimateElementor\Classes\UAEL_Helper::update_admin_settings_option( '_uael_widgets', $widgets );
		\UltimateElementor\Classes\UAEL_Helper::create_specific_stylesheet();

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %1$d: skin count, %2$s: action */
				__( '%1$d post skins %2$sd.', 'uael' ),
				count( $post_skins ),
				$action
			),
		);
	}
}
