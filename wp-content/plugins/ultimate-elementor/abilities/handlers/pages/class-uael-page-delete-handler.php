<?php
/**
 * Page Delete Handler.
 *
 * Moves an Elementor page or post to the trash.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Page_Delete_Handler
 *
 * Implements UAEL_Ability_Handler for the pages/delete ability.
 *
 * @since 1.45.0
 */
class UAEL_Page_Delete_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'pages-delete';
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
			'label'               => __( 'Delete Page', 'uael' ),
			'description'         => __( 'Moves an Elementor page or post to the trash. Does not permanently delete.', 'uael' ),
			'category'            => 'uael-pages',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'The page or post ID to trash.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'post_id' => array( 'type' => 'integer' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => true,
					'idempotent'   => true,
					'instructions' => 'Moves page/post to trash. Ask user to confirm. Does not permanently delete.',
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
		$post_id = absint( $input['post_id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'uael_post_not_found',
				__( 'Post not found.', 'uael' ),
				array( 'status' => 404 )
			);
		}

		if ( 'elementor-hf' === $post->post_type ) {
			return new \WP_Error(
				'uael_use_template_api',
				__( 'Use templates/delete for HFE templates.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		$validation = $this->validate_elementor_post( $post_id );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Deleting requires the delete capability for this specific post, not
		// merely edit (which validate_elementor_post checks).
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new \WP_Error(
				'uael_forbidden',
				__( 'You are not allowed to delete this post.', 'uael' ),
				array( 'status' => 403 )
			);
		}

		$result = wp_trash_post( $post_id );

		if ( ! $result ) {
			return new \WP_Error(
				'uael_trash_failed',
				__( 'Failed to move post to trash.', 'uael' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'message' => sprintf(
				/* translators: %s: post title */
				__( '"%s" moved to trash.', 'uael' ),
				get_the_title( $post_id )
			),
		);
	}
}
