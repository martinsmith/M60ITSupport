<?php
/**
 * Page Update Meta Handler.
 *
 * Updates title, page template, or featured image for an Elementor page or post.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Page_Update_Meta_Handler
 *
 * Implements UAEL_Ability_Handler for the pages/update-meta ability.
 *
 * @since 1.45.0
 */
class UAEL_Page_Update_Meta_Handler implements UAEL_Ability_Handler {

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
		return 'pages-update-meta';
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
			'label'               => __( 'Update Page Meta', 'uael' ),
			'description'         => __( 'Updates title, page template, or featured image for an Elementor page or post.', 'uael' ),
			'category'            => 'uael-pages',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id'           => array(
						'type'        => 'integer',
						'description' => __( 'The page or post ID to update.', 'uael' ),
					),
					'title'             => array(
						'type'        => 'string',
						'description' => __( 'New page/post title.', 'uael' ),
					),
					'page_template'     => array(
						'type'        => 'string',
						'enum'        => self::VALID_TEMPLATES,
						'description' => __( 'Elementor page template: elementor_header_footer, elementor_canvas, or default.', 'uael' ),
					),
					'featured_image_id' => array(
						'type'        => 'integer',
						'description' => __( 'Attachment ID for featured image. Pass 0 to remove.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'        => array( 'type' => 'boolean' ),
					'post_id'        => array( 'type' => 'integer' ),
					'updated_fields' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Updates page metadata like title, page template, or featured image. Only provided fields are updated.',
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
		$post_id    = absint( $input['post_id'] );
		$validation = $this->validate_elementor_post( $post_id );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$updated_fields = array();

		// Update title.
		if ( isset( $input['title'] ) ) {
			$title  = sanitize_text_field( $input['title'] );
			$result = wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $title,
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$updated_fields[] = 'title';
		}

		// Update page template.
		if ( isset( $input['page_template'] ) ) {
			$template = sanitize_text_field( $input['page_template'] );

			if ( ! in_array( $template, self::VALID_TEMPLATES, true ) ) {
				return new \WP_Error(
					'uael_invalid_template',
					__( 'Invalid page template value.', 'uael' ),
					array( 'status' => 400 )
				);
			}

			if ( 'default' === $template ) {
				delete_post_meta( $post_id, '_wp_page_template' );
			} else {
				update_post_meta( $post_id, '_wp_page_template', $template );
			}

			$updated_fields[] = 'page_template';
		}

		// Update featured image.
		if ( isset( $input['featured_image_id'] ) ) {
			$image_id = absint( $input['featured_image_id'] );

			if ( 0 === $image_id ) {
				delete_post_thumbnail( $post_id );
			} else {
				$attachment = get_post( $image_id );

				if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
					return new \WP_Error(
						'uael_invalid_attachment',
						__( 'Invalid attachment ID for featured image.', 'uael' ),
						array( 'status' => 400 )
					);
				}

				set_post_thumbnail( $post_id, $image_id );
			}

			$updated_fields[] = 'featured_image';
		}

		if ( empty( $updated_fields ) ) {
			return new \WP_Error(
				'uael_no_fields',
				__( 'No fields provided to update.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'success'        => true,
			'post_id'        => $post_id,
			'updated_fields' => $updated_fields,
		);
	}
}
