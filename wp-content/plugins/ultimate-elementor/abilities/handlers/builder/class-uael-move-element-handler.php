<?php
/**
 * Move Element Handler.
 *
 * Repositions an element within any Elementor post.
 * Removes from current location and inserts at new position.
 * Unified handler replacing template-builder/move.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Move_Element_Handler
 *
 * Implements UAEL_Ability_Handler for the builder/move-element ability.
 *
 * @since 1.45.0
 */
class UAEL_Move_Element_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'builder-move-element';
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
			'label'               => __( 'Move Element', 'uael' ),
			'description'         => __( 'Repositions an element within any Elementor post. Removes from current location and inserts at new position.', 'uael' ),
			'category'            => 'uael-builder',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id', 'element_id', 'position' ),
				'properties' => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'Any Elementor-enabled post ID (page, post, or template).', 'uael' ),
					),
					'element_id' => array(
						'type'        => 'string',
						'description' => __( 'Element ID to move.', 'uael' ),
					),
					'position'   => array(
						'description' => __( 'New position: { "after": "element_id" } or { "before": "element_id" }.', 'uael' ),
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
					'instructions' => 'For reordering elements within an existing post. Use get-structure first to find element IDs. If the layout needs a complete reorganization, use builder/build instead.',
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
		$position   = $input['position'] ?? null;

		if ( empty( $element_id ) ) {
			return new \WP_Error(
				'uael_missing_element_id',
				__( 'Element ID is required.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $position ) ) {
			return new \WP_Error(
				'uael_missing_position',
				__( 'Position is required.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		$loaded = $this->load_post( $input['post_id'] ?? $input['template_id'] ?? 0 );

		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		$elements = UAEL_Element_Helpers::move_element( $loaded['elements'], $element_id, $position );

		if ( is_wp_error( $elements ) ) {
			return $elements;
		}

		$saved = UAEL_Element_Helpers::save_elementor_data( $loaded['post']->ID, $elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: 1: Element ID, 2: Post title */
				__( 'Moved element %1$s in "%2$s".', 'uael' ),
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
}
