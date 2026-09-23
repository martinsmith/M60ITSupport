<?php
/**
 * Design Tokens Handler.
 *
 * Reads the active Elementor kit for global colors, typography,
 * and layout tokens used to ensure consistent design.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Design_Tokens_Handler
 *
 * Implements UAEL_Ability_Handler for the design-system/get-tokens ability.
 *
 * @since 1.45.0
 */
class UAEL_Design_Tokens_Handler implements UAEL_Ability_Handler {

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'design-system-get-tokens';
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
			'label'               => __( 'Get Design Tokens', 'uael' ),
			'description'         => __( 'Returns the site\'s global colors, fonts, and spacing from the active Elementor kit. ALWAYS call this before build-template or build-page to match the site\'s existing design.', 'uael' ),
			'category'            => 'uael-design',
			'permission_callback' => function () {
				return current_user_can( 'read' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'colors'     => array( 'type' => 'object' ),
					'typography' => array( 'type' => 'object' ),
					'layout'     => array( 'type' => 'object' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => true,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'ALWAYS call this BEFORE build-template or build-page. '
						. 'Use the returned colors instead of hardcoded hex values. '
						. 'Apply primary color to CTAs, buttons, and key backgrounds. '
						. 'Use text color for body/paragraph text. '
						. 'Use accent color for highlights and interactive elements. '
						. 'Use the site\'s fonts from typography tokens. '
						. 'Respect container_width for boxed layouts and space_between_widgets for consistent spacing. '
						. 'This ensures AI-generated templates and pages match the site\'s existing design.',
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * Reads global colors, fonts, and spacing from the active Elementor kit.
	 *
	 * @since 1.45.0
	 *
	 * @param array $input Unused input parameters.
	 * @return array|WP_Error Design tokens or error.
	 */
	public function execute( $input ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error(
				'uael_elementor_not_active',
				__( 'Elementor is not active.', 'uael' ),
				array( 'status' => 500 )
			);
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit_for_frontend();

		if ( ! $kit ) {
			return new WP_Error(
				'uael_no_active_kit',
				__( 'No active Elementor kit found.', 'uael' ),
				array( 'status' => 500 )
			);
		}

		// Extract global colors.
		$system_colors = $kit->get_settings_for_display( 'system_colors' );
		$custom_colors = $kit->get_settings_for_display( 'custom_colors' );

		$colors = array(
			'system' => $this->normalize_colors( $system_colors ),
			'custom' => $this->normalize_colors( $custom_colors ),
		);

		// Extract global typography.
		$system_typography = $kit->get_settings( 'system_typography' );
		$custom_typography = $kit->get_settings( 'custom_typography' );

		$typography = array(
			'system' => $this->normalize_typography( $system_typography ),
			'custom' => $this->normalize_typography( $custom_typography ),
		);

		// Extract layout defaults.
		$container_width = $kit->get_settings_for_display( 'container_width' );
		$space_between   = $kit->get_settings_for_display( 'space_between_widgets' );

		$layout = array(
			'container_width'       => ! empty( $container_width['size'] ) ? absint( $container_width['size'] ) : 1140,
			'space_between_widgets' => $this->normalize_spacing( $space_between ),
		);

		return array(
			'colors'     => $colors,
			'typography' => $typography,
			'layout'     => $layout,
		);
	}

	/**
	 * Normalize color entries from Elementor kit settings.
	 *
	 * @since 1.45.0
	 *
	 * @param mixed $colors Raw color settings.
	 * @return array Normalized color entries.
	 */
	private function normalize_colors( $colors ) {
		if ( empty( $colors ) || ! is_array( $colors ) ) {
			return array();
		}

		$result = array();
		foreach ( $colors as $color ) {
			if ( ! is_array( $color ) ) {
				continue;
			}

			$result[] = array(
				'id'    => sanitize_text_field( $color['_id'] ?? '' ),
				'title' => sanitize_text_field( $color['title'] ?? '' ),
				'color' => sanitize_hex_color( $color['color'] ?? '' ),
			);
		}

		return $result;
	}

	/**
	 * Normalize typography entries from Elementor kit settings.
	 *
	 * @since 1.45.0
	 *
	 * @param mixed $typography Raw typography settings.
	 * @return array Normalized typography entries.
	 */
	private function normalize_typography( $typography ) {
		if ( empty( $typography ) || ! is_array( $typography ) ) {
			return array();
		}

		$result = array();
		foreach ( $typography as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$item = array(
				'id'    => sanitize_text_field( $entry['_id'] ?? '' ),
				'title' => sanitize_text_field( $entry['title'] ?? '' ),
			);

			// Extract typography properties (prefixed with typography_ or styles_).
			$props = array( 'font_family', 'font_size', 'font_weight', 'line_height', 'letter_spacing', 'text_transform', 'font_style' );

			foreach ( $props as $prop ) {
				foreach ( array( 'typography_', 'styles_' ) as $prefix ) {
					$key = $prefix . $prop;

					if ( isset( $entry[ $key ] ) && '' !== $entry[ $key ] ) {
						$item[ $prop ] = $entry[ $key ];
						break;
					}
				}
			}

			$result[] = $item;
		}

		return $result;
	}

	/**
	 * Normalize spacing value from Elementor kit settings.
	 *
	 * @since 1.45.0
	 *
	 * @param mixed $spacing Raw spacing settings.
	 * @return array Normalized spacing with size and unit.
	 */
	private function normalize_spacing( $spacing ) {
		if ( empty( $spacing ) || ! is_array( $spacing ) ) {
			return array(
				'size' => 20,
				'unit' => 'px',
			);
		}

		return array(
			'size' => absint( $spacing['size'] ?? 20 ),
			'unit' => sanitize_text_field( $spacing['unit'] ?? 'px' ),
		);
	}
}
