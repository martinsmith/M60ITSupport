<?php
/**
 * Page Create Handler.
 *
 * Creates a new Elementor-ready page or post with optional template settings.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Page_Create_Handler
 *
 * Implements UAEL_Ability_Handler for the pages/create ability.
 *
 * @since 1.45.0
 */
class UAEL_Page_Create_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Valid page templates.
	 *
	 * @var array
	 */
	const VALID_TEMPLATES = array( 'elementor_header_footer', 'elementor_canvas', 'default' );

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'pages-create';
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
			'label'               => __( 'Create Page', 'uael' ),
			'description'         => __( 'Creates a new Elementor-ready page or post with optional page template.', 'uael' ),
			'category'            => 'uael-pages',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'title' ),
				'properties' => array(
					'title'         => array(
						'type'        => 'string',
						'description' => __( 'The page or post title.', 'uael' ),
					),
					'post_type'     => array(
						'type'        => 'string',
						'enum'        => array( 'page', 'post' ),
						'default'     => 'page',
						'description' => __( 'Post type to create.', 'uael' ),
					),
					'status'        => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft' ),
						'default'     => 'draft',
						'description' => __( 'Initial post status.', 'uael' ),
					),
					'page_template' => array(
						'type'        => 'string',
						'enum'        => self::VALID_TEMPLATES,
						'default'     => 'default',
						'description' => __( 'Elementor page template: elementor_header_footer, elementor_canvas, or default.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'                 => array( 'type' => 'integer' ),
					'title'              => array( 'type' => 'string' ),
					'post_type'          => array( 'type' => 'string' ),
					'page_template'      => array( 'type' => 'string' ),
					'edit_url'           => array( 'type' => 'string' ),
					'elementor_edit_url' => array( 'type' => 'string' ),
					'view_url'           => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => false,
					'idempotent'   => false,
					'instructions' => 'Creates a new page/post ready for Elementor editing. Confirm title and settings with user before creating.',
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
		$modifications_check = $this->check_modifications_allowed();

		if ( is_wp_error( $modifications_check ) ) {
			return $modifications_check;
		}

		$post_type = ! empty( $input['post_type'] ) ? sanitize_text_field( $input['post_type'] ) : 'page';
		$status    = ! empty( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'draft';
		$title     = sanitize_text_field( $input['title'] );
		$template  = ! empty( $input['page_template'] ) ? sanitize_text_field( $input['page_template'] ) : 'default';

		// Validate post type against Elementor's supported CPTs.
		$supported_cpts = get_option( 'elementor_cpt_support', array( 'page', 'post' ) );

		if ( ! is_array( $supported_cpts ) || ! in_array( $post_type, $supported_cpts, true ) ) {
			return new \WP_Error(
				'uael_unsupported_post_type',
				sprintf(
					/* translators: %s: post type slug */
					__( 'Post type "%s" is not supported by Elementor.', 'uael' ),
					$post_type
				),
				array( 'status' => 400 )
			);
		}

		// Validate template value.
		if ( ! in_array( $template, self::VALID_TEMPLATES, true ) ) {
			$template = 'default';
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => $post_type,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set Elementor edit mode meta so the page is recognized by Elementor.
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_data', '[]' );
		update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );

		// Set page template if not default.
		if ( 'default' !== $template ) {
			update_post_meta( $post_id, '_wp_page_template', $template );
		}

		return array(
			'id'                 => $post_id,
			'title'              => $title,
			'post_type'          => $post_type,
			'page_template'      => $template,
			'edit_url'           => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			'elementor_edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
			'view_url'           => get_permalink( $post_id ),
		);
	}
}
