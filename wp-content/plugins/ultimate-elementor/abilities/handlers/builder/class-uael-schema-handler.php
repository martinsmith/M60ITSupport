<?php
/**
 * Schema Handler.
 *
 * Returns settings schemas for widgets, sections, containers, and columns.
 * Unified handler replacing template-builder/get-widget-schema and
 * template-builder/get-section-schema.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Schema_Handler
 *
 * Implements UAEL_Ability_Handler for the builder/get-schema ability.
 *
 * @since 1.45.0
 */
class UAEL_Schema_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'builder-get-schema';
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
			'label'               => __( 'Get Element Schema', 'uael' ),
			'description'         => __( 'Returns a settings schema for widgets, sections, containers, or columns. Use before building layouts to discover available settings.', 'uael' ),
			'category'            => 'uael-builder',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'type' ),
				'properties' => array(
					'type'        => array(
						'type'        => 'string',
						'enum'        => array( 'widget', 'section', 'container', 'column' ),
						'description' => __( 'Type of element to get schema for.', 'uael' ),
					),
					'widget_type' => array(
						'type'        => 'string',
						'description' => __( 'Widget type slug (required when type=widget). E.g., "heading", "navigation-menu".', 'uael' ),
					),
					'tab'         => array(
						'type'        => 'string',
						'enum'        => array( 'content', 'style', 'all' ),
						'default'     => 'all',
						'description' => __( 'Which settings tab to include. Defaults to "all" which returns content + style settings.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'type'        => array( 'type' => 'string' ),
					'widget_type' => array( 'type' => 'string' ),
					'title'       => array( 'type' => 'string' ),
					'tab'         => array( 'type' => 'string' ),
					'layout_mode' => array( 'type' => 'string' ),
					'schema'      => array( 'type' => 'object' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => true,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Call with type=widget and widget_type to get widget schema. Call with type=section/container/column to get layout element schema. ALWAYS call this for each widget BEFORE calling builder/build -- it returns the full settings schema so you know the correct keys. Without this, you will guess at setting names and produce broken styling. The schema includes "depends_on" fields showing toggle dependencies -- these toggles are auto-set by builder/build, so just provide value keys.',
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
	 * @return array|WP_Error Schema data or error.
	 */
	public function execute( $input ) {
		$type = sanitize_text_field( $input['type'] ?? '' );

		if ( 'widget' === $type ) {
			return $this->get_widget_schema( $input );
		}

		if ( in_array( $type, array( 'section', 'container', 'column' ), true ) ) {
			return $this->get_layout_schema( $type );
		}

		return new \WP_Error(
			'uael_invalid_type',
			__( 'Type must be one of: widget, section, container, column.', 'uael' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Get widget settings schema.
	 *
	 * @since 1.45.0
	 *
	 * @param array $input Input parameters with 'widget_type' and optional 'tab'.
	 * @return array|WP_Error Widget schema or error.
	 */
	private function get_widget_schema( $input ) {
		$widget_type = sanitize_text_field( $input['widget_type'] ?? '' );

		if ( empty( $widget_type ) ) {
			return new \WP_Error(
				'uael_missing_widget_type',
				__( 'widget_type is required when type=widget.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		if ( ! UAEL_Element_Helpers::is_widget_allowed( $widget_type ) ) {
			return new \WP_Error(
				'uael_widget_not_allowed',
				/* translators: %s: widget type slug */
				sprintf( __( 'Widget type "%s" is not in the allowed list.', 'uael' ), $widget_type ),
				array( 'status' => 400 )
			);
		}

		$tab = sanitize_text_field( $input['tab'] ?? 'all' );

		return UAEL_Element_Helpers::get_widget_schema( $widget_type, $tab );
	}

	/**
	 * Get layout element settings schema.
	 *
	 * Introspects Elementor's registered controls for sections, containers, or columns.
	 *
	 * @since 1.45.0
	 *
	 * @param string $element_type Element type: 'section', 'container', or 'column'.
	 * @return array|WP_Error Schema data or error.
	 */
	private function get_layout_schema( $element_type ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new \WP_Error(
				'uael_elementor_not_active',
				__( 'Elementor is not active.', 'uael' ),
				array( 'status' => 500 )
			);
		}

		$layout_mode = UAEL_Element_Helpers::is_container_active() ? 'container' : 'section';

		// Get the element instance from Elementor's elements manager.
		$element = \Elementor\Plugin::$instance->elements_manager->get_element_types( $element_type );

		if ( ! $element ) {
			return new \WP_Error(
				'uael_element_type_not_found',
				/* translators: %s: element type */
				sprintf( __( 'Element type "%s" not found in Elementor.', 'uael' ), $element_type ),
				array( 'status' => 404 )
			);
		}

		// Get all registered controls for this element type.
		$controls = $element->get_controls();
		$schema   = array();

		foreach ( $controls as $control_id => $control ) {
			// Skip internal/hidden controls.
			if ( ! empty( $control['is_internal'] ) ) {
				continue;
			}

			$entry = array(
				'type'    => $control['type'] ?? 'unknown',
				'label'   => $control['label'] ?? '',
				'section' => $control['section'] ?? '',
				'tab'     => $control['tab'] ?? '',
			);

			if ( isset( $control['default'] ) ) {
				$entry['default'] = $control['default'];
			}

			if ( isset( $control['options'] ) ) {
				$entry['options'] = $control['options'];
			}

			if ( isset( $control['condition'] ) ) {
				$entry['depends_on'] = $control['condition'];
			}

			$schema[ $control_id ] = $entry;
		}

		return array(
			'element_type' => $element_type,
			'layout_mode'  => $layout_mode,
			'schema'       => $schema,
		);
	}
}
