<?php
/**
 * Skins Activate Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Skins_Activate_Handler
 *
 * @since 1.45.0
 */
class UAEL_Skins_Activate_Handler implements UAEL_Ability_Handler {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'skins-activate';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Activate Post Skin', 'uael' ),
			'description'         => __( 'Activate a specific post skin by its key.', 'uael' ),
			'category'            => 'uael-skins',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'skin_key' => array(
						'type'        => 'string',
						'description' => __( 'The post skin key to activate, e.g., Skin_Card, Skin_Feed, Skin_News, Skin_Business.', 'uael' ),
					),
				),
				'required'   => array( 'skin_key' ),
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
	 * Execute: Activate a post skin.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$skin_key = isset( $input['skin_key'] ) ? sanitize_text_field( $input['skin_key'] ) : '';

		if ( empty( $skin_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Skin key is required.', 'uael' ),
			);
		}

		$post_skins = \UltimateElementor\Classes\UAEL_Helper::get_post_skin_list();

		if ( ! isset( $post_skins[ $skin_key ] ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: skin key */
					__( 'Post skin "%s" not found.', 'uael' ),
					$skin_key
				),
			);
		}

		$widgets              = \UltimateElementor\Classes\UAEL_Helper::get_admin_settings_option( '_uael_widgets', array() );
		$widgets[ $skin_key ] = $skin_key;
		$widgets              = array_map( 'esc_attr', $widgets );

		\UltimateElementor\Classes\UAEL_Helper::update_admin_settings_option( '_uael_widgets', $widgets );
		\UltimateElementor\Classes\UAEL_Helper::create_specific_stylesheet();

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: skin title */
				__( 'Post skin "%s" activated successfully.', 'uael' ),
				isset( $post_skins[ $skin_key ]['title'] ) ? $post_skins[ $skin_key ]['title'] : $skin_key
			),
		);
	}
}
