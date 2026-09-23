<?php
/**
 * Widget Deactivate Unused Handler.
 *
 * Scans site-wide Elementor usage and deactivates widgets not found
 * in any published content. Skips extensions that are site-wide features.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Widget_Deactivate_Unused_Handler
 *
 * Implements UAEL_Ability_Handler for the widgets/deactivate-unused ability.
 *
 * @since 1.45.0
 */
class UAEL_Widget_Deactivate_Unused_Handler implements UAEL_Ability_Handler {

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'widgets-deactivate-unused';
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
			'label'               => __( 'Deactivate Unused Widgets', 'uael' ),
			'description'         => __( 'Disable widgets not used in any published Elementor content (pages, posts, or templates).', 'uael' ),
			'category'            => 'uael-widgets',
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
					'success'           => array( 'type' => 'boolean' ),
					'deactivated'       => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'deactivated_count' => array( 'type' => 'integer' ),
					'message'           => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => true,
					'idempotent'   => true,
					'instructions' => 'Before running, use widgets/get-usage to show the user which widgets will be deactivated. Ask for confirmation before proceeding.',
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * Cross-references widget usage in Elementor data across all published
	 * content, then deactivates any active widget not found in use.
	 * Skips extensions (DisplayConditions, Particles, PartyPropzExtension,
	 * SectionDivider, Cross_Domain, Presets, StickyHeader) as they are
	 * site-wide features not tied to individual content.
	 *
	 * @since 1.45.0
	 *
	 * @param array $input Unused input parameters.
	 * @return array Result with list of deactivated widget slugs.
	 */
	public function execute( $input ) {
		$widget_list = \UltimateElementor\Classes\UAEL_Helper::get_widget_list();
		$used        = \UltimateElementor\Classes\UAEL_Helper::uaepro_get_used_widget();

		// Extensions to always preserve -- site-wide features not tied to content.
		$preserved   = array( 'DisplayConditions', 'Particles', 'PartyPropzExtension', 'SectionDivider', 'Cross_Domain', 'Presets', 'StickyHeader' );
		$deactivated = array();
		$widgets     = \UltimateElementor\Classes\UAEL_Helper::get_admin_settings_option( '_uael_widgets', array() );

		foreach ( $widget_list as $slug => $value ) {
			if ( in_array( $slug, $preserved, true ) ) {
				continue;
			}
			if ( ! isset( $used[ $value['slug'] ] ) ) {
				$widgets[ $slug ] = 'disabled';
				$deactivated[]    = $slug;
			}
		}

		$widgets = array_map( 'esc_attr', $widgets );
		\UltimateElementor\Classes\UAEL_Helper::update_admin_settings_option( '_uael_widgets', $widgets );
		\UltimateElementor\Classes\UAEL_Helper::create_specific_stylesheet();

		return array(
			'success'           => true,
			'deactivated'       => $deactivated,
			'deactivated_count' => count( $deactivated ),
			'message'           => sprintf(
				/* translators: %d: number of widgets */
				__( '%d unused widgets deactivated.', 'uael' ),
				count( $deactivated )
			),
		);
	}
}
