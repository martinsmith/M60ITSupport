<?php
/**
 * Update Widget Handler.
 *
 * Updates settings for an existing widget element in any Elementor post.
 * Performs a partial merge -- only provided settings are changed.
 * Unified handler replacing template-builder/update.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Update_Widget_Handler
 *
 * Implements UAEL_Ability_Handler for the builder/update-widget ability.
 *
 * @since 1.45.0
 */
class UAEL_Update_Widget_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'builder-update-widget';
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
			'label'               => __( 'Update Widget Settings', 'uael' ),
			'description'         => __( 'Updates settings for an existing widget element in any Elementor post. Performs a partial merge -- only provided settings are changed.', 'uael' ),
			'category'            => 'uael-builder',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id', 'element_id', 'settings' ),
				'properties' => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'Any Elementor-enabled post ID (page, post, or template).', 'uael' ),
					),
					'element_id' => array(
						'type'        => 'string',
						'description' => __( 'Element ID to update (from get-structure).', 'uael' ),
					),
					'settings'   => array(
						'type'        => 'string',
						'description' => __( 'JSON string of the settings to update. You MUST call builder/get-schema first to discover the exact setting keys for the widget type. Then pass a JSON object string with only the keys you want to change. Example for uael-infobox: {"heading":"New Title","description":"New text"}. Example for heading: {"title":"New Heading"}. Example for text-editor: {"editor":"<p>New content</p>"}.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'For changing settings on an existing widget (e.g., text, colors, menu selection). Use get-structure first to find the element ID. If you need to change the entire layout, use builder/build instead.',
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
	 * @return array|WP_Error Result data or error.
	 */
	public function execute( $input ) {
		$allowed = $this->check_modifications_allowed();

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$element_id = sanitize_text_field( $input['element_id'] ?? '' );

		// Settings can arrive as JSON string (from Angie) or array (from MCP Adapter).
		$raw_settings = $input['settings'] ?? array();

		if ( is_string( $raw_settings ) ) {
			$settings = json_decode( $raw_settings, true );

			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $settings ) ) {
				$settings = array();
			}
		} else {
			$settings = is_array( $raw_settings ) ? $raw_settings : array();
		}

		if ( empty( $element_id ) ) {
			return new \WP_Error(
				'uael_missing_element_id',
				__( 'Element ID is required.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		$loaded = $this->load_post( $input['post_id'] ?? $input['template_id'] ?? 0 );

		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		$elements = $loaded['elements'];

		// When settings is empty, return the widget's current settings so the AI knows the correct keys.
		if ( empty( $settings ) ) {
			$element = UAEL_Element_Helpers::find_element( $elements, $element_id );

			if ( ! $element ) {
				return new \WP_Error(
					'uael_element_not_found',
					__( 'Element not found in post.', 'uael' ),
					array( 'status' => 404 )
				);
			}

			$widget_type      = $element['widgetType'] ?? $element['elType'] ?? 'unknown';
			$current_settings = $element['settings'] ?? array();
			$setting_keys     = array_keys( $current_settings );

			return new \WP_Error(
				'uael_empty_settings',
				sprintf(
					/* translators: 1: widget type, 2: comma-separated setting keys */
					__( 'No settings provided. This is a "%1$s" widget. Available setting keys: %2$s. Retry with the correct keys in the settings object.', 'uael' ),
					$widget_type,
					implode( ', ', array_slice( $setting_keys, 0, 15 ) )
				),
				array( 'status' => 400 )
			);
		}

		// Find and update the element in the tree.
		$updated = $this->update_element_settings( $elements, $element_id, $settings );

		if ( ! $updated['found'] ) {
			return new \WP_Error(
				'uael_element_not_found',
				__( 'Element not found in post.', 'uael' ),
				array( 'status' => 404 )
			);
		}

		$saved = UAEL_Element_Helpers::save_elementor_data( $loaded['post']->ID, $updated['elements'] );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: 1: Element ID, 2: Post title */
				__( 'Updated element %1$s in "%2$s".', 'uael' ),
				$element_id,
				$loaded['post']->post_title
			),
		);
	}

	/**
	 * Validate and load an Elementor-enabled post.
	 *
	 * @since 1.45.0
	 *
	 * @param int $post_id Post ID.
	 * @return array|WP_Error Array with 'post' and 'elements', or error.
	 */
	private function load_post( $post_id ) {
		$post = $this->validate_elementor_post( absint( $post_id ) );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$elements = UAEL_Element_Helpers::parse_elementor_data( $post->ID );

		if ( is_wp_error( $elements ) ) {
			return $elements;
		}

		return array(
			'post'     => $post,
			'elements' => $elements,
		);
	}

	/**
	 * Recursively find and update element settings (partial merge).
	 *
	 * @since 1.45.0
	 *
	 * @param array  $elements   Element tree.
	 * @param string $element_id Target element ID.
	 * @param array  $settings   Settings to merge.
	 * @return array Result with 'found' bool and 'elements' array.
	 */
	private function update_element_settings( $elements, $element_id, $settings ) {
		foreach ( $elements as $index => $element ) {
			if ( isset( $element['id'] ) && $element['id'] === $element_id ) {
				$existing = isset( $elements[ $index ]['settings'] ) && is_array( $elements[ $index ]['settings'] )
					? $elements[ $index ]['settings']
					: array();

				$elements[ $index ]['settings'] = array_merge( $existing, $settings );

				return array(
					'found'    => true,
					'elements' => $elements,
				);
			}

			if ( ! empty( $element['elements'] ) ) {
				$child_result = $this->update_element_settings( $element['elements'], $element_id, $settings );

				if ( $child_result['found'] ) {
					$elements[ $index ]['elements'] = $child_result['elements'];

					return array(
						'found'    => true,
						'elements' => $elements,
					);
				}
			}
		}

		return array(
			'found'    => false,
			'elements' => $elements,
		);
	}
}
