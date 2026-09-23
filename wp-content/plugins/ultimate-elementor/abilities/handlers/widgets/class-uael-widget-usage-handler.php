<?php
/**
 * Widget Usage Handler.
 *
 * Returns site-wide usage counts for UAEL widgets across all Elementor content.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Widget_Usage_Handler
 *
 * Implements UAEL_Ability_Handler for the widgets/get-usage ability.
 *
 * @since 1.45.0
 */
class UAEL_Widget_Usage_Handler implements UAEL_Ability_Handler {

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'widgets-get-usage';
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
			'label'               => __( 'Get Widget Usage Map', 'uael' ),
			'description'         => __( 'Returns site-wide usage counts for UAEL widgets across all Elementor content (pages, posts, and templates).', 'uael' ),
			'category'            => 'uael-widgets',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'widget_slug' => array(
						'type'        => 'string',
						'description' => __( 'Optional. Filter to a single widget slug.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'usage'   => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'integer' ),
					),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => true,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Use this to check which widgets are actively used on the site before deactivating any widget.',
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * Uses cached usage data when available, otherwise scans all Elementor
	 * content site-wide via UAEL_Helper::uaepro_get_used_widget().
	 *
	 * @since 1.45.0
	 *
	 * @param array $input Optional input with widget_slug filter.
	 * @return array Result with usage map.
	 */
	public function execute( $input ) {
		$cached = get_option( 'uaepro_widgets_usage_data_option', array() );

		if ( ! is_array( $cached ) || empty( $cached ) ) {
			$cached = \UltimateElementor\Classes\UAEL_Helper::uaepro_get_used_widget();
		}

		$usage = is_array( $cached ) ? $cached : array();

		// If filtering by a single slug, return only that entry.
		if ( ! empty( $input['widget_slug'] ) ) {
			$filter_slug = sanitize_text_field( $input['widget_slug'] );
			if ( isset( $usage[ $filter_slug ] ) ) {
				$usage = array( $filter_slug => $usage[ $filter_slug ] );
			} else {
				$usage = array( $filter_slug => 0 );
			}
		}

		return array(
			'success' => true,
			'usage'   => $usage,
		);
	}
}
