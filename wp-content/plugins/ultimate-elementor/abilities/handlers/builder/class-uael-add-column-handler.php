<?php
/**
 * Add Column Handler.
 *
 * Adds a column to an existing section or container in any Elementor post.
 * Automatically redistributes column sizes to fit.
 * Unified handler replacing template-builder/add-column.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Add_Column_Handler
 *
 * Implements UAEL_Ability_Handler for the builder/add-column ability.
 *
 * @since 1.45.0
 */
class UAEL_Add_Column_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'builder-add-column';
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
			'label'               => __( 'Add Column to Section', 'uael' ),
			'description'         => __( 'Adds a column to an existing section or container in any Elementor post. Automatically redistributes column sizes to fit.', 'uael' ),
			'category'            => 'uael-builder',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id', 'section_id' ),
				'properties' => array(
					'post_id'     => array(
						'type'        => 'integer',
						'description' => __( 'Any Elementor-enabled post ID (page, post, or template).', 'uael' ),
					),
					'section_id'  => array(
						'type'        => 'string',
						'description' => __( 'Section or container element ID to add column to.', 'uael' ),
					),
					'auto_resize' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => __( 'If true, automatically redistributes all column sizes evenly.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'column_id' => array( 'type' => 'string' ),
					'message'   => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => false,
					'idempotent'   => false,
					'instructions' => 'For adding a column to an existing section. Do NOT use this to build multi-column layouts from scratch -- use builder/build instead, which creates sections with the correct column structure in one call. Only use add-column for incremental changes.',
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

		$loaded = $this->load_post( $input['post_id'] ?? $input['template_id'] ?? 0 );

		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		$section_id  = sanitize_text_field( $input['section_id'] ?? '' );
		$auto_resize = isset( $input['auto_resize'] ) ? (bool) $input['auto_resize'] : true;

		if ( empty( $section_id ) ) {
			return new \WP_Error(
				'uael_missing_section_id',
				__( 'section_id is required.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		$result = UAEL_Element_Helpers::add_column_to_section(
			$loaded['elements'],
			$section_id,
			0,
			$auto_resize
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$saved = UAEL_Element_Helpers::save_elementor_data( $loaded['post']->ID, $result['elements'] );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'success'   => true,
			'column_id' => $result['column_id'],
			'message'   => sprintf(
				/* translators: 1: Section ID, 2: Post title, 3: Column ID */
				__( 'Added column to section %1$s in "%2$s". Use builder/insert-widget with position {"inside": "%3$s"} to add widgets.', 'uael' ),
				$section_id,
				$loaded['post']->post_title,
				$result['column_id']
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
