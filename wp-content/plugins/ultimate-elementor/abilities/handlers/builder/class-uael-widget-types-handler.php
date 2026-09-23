<?php
/**
 * Widget Types Handler.
 *
 * Lists all widget types available for insertion into Elementor posts.
 * Unified handler replacing template-builder/list-widget-types.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Widget_Types_Handler
 *
 * Implements UAEL_Ability_Handler for the builder/list-widget-types ability.
 *
 * @since 1.45.0
 */
class UAEL_Widget_Types_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'builder-list-widget-types';
	}

	/**
	 * Get the wp_register_ability() args array.
	 *
	 * @since 1.45.0
	 *
	 * @return array Ability registration args.
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'List Available Widget Types', 'uael' ),
			'description'         => __( 'Lists all widget types that can be inserted. RECOMMENDED — Core: heading, text-editor, image, button, spacer, divider, icon-list. UAE Pro: uael-infobox (Info Box), uael-price-table (Price Box), uael-price-list, uael-advanced-heading, uael-posts, uael-business-reviews, uael-table.', 'uael' ),
			'category'            => 'uael-builder',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'source' => array(
						'type'        => 'string',
						'enum'        => array( 'all', 'header-footer-elementor', 'elementor', 'ultimate-addons-for-elementor' ),
						'default'     => 'all',
						'description' => __( 'Filter by source plugin.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'   => array( 'type' => 'string' ),
						'title'  => array( 'type' => 'string' ),
						'source' => array( 'type' => 'string' ),
					),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => true,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Use this to discover available widget types before building layouts. The slug is what you pass as widget_type. RECOMMENDED WIDGETS: Core -- heading, text-editor, image, button, spacer, divider, icon-list, icon-box. UAE Pro -- uael-infobox (Info Box), uael-price-table (Price Box), uael-price-list, uael-advanced-heading, uael-fancy-heading, uael-posts, uael-business-reviews, uael-table, uael-buttons (Multi Buttons). Call builder/get-schema with type=widget on any widget to discover its content + style settings before building.',
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
	 * @param array $input Validated input parameters.
	 * @return array List of widget types.
	 */
	public function execute( $input ) {
		$widgets = UAEL_Element_Helpers::get_allowed_widget_types();
		$source  = sanitize_text_field( $input['source'] ?? 'all' );

		if ( 'all' !== $source ) {
			$widgets = array_values(
				array_filter(
					$widgets,
					function ( $w ) use ( $source ) {
						return $w['source'] === $source;
					}
				)
			);
		}

		return $widgets;
	}
}
