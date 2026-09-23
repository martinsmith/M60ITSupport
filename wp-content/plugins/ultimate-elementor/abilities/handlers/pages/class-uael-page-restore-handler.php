<?php
/**
 * Page Restore Handler.
 *
 * Restores a trashed page or post back to its previous status.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Page_Restore_Handler
 *
 * Implements UAEL_Ability_Handler for the pages/restore ability.
 *
 * @since 1.45.0
 */
class UAEL_Page_Restore_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'pages-restore';
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
			'label'               => __( 'Restore Page', 'uael' ),
			'description'         => __( 'Restores a trashed page or post back to its previous status.', 'uael' ),
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
						'description' => __( 'The trashed page or post ID to restore.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'post_id'    => array( 'type' => 'integer' ),
					'new_status' => array( 'type' => 'string' ),
					'message'    => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Restores a trashed page/post to its previous status. Only works on posts currently in the trash.',
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

		// Object-level authorization: the current user must be allowed to edit
		// this specific post, not merely hold manage_options. Mirrors the check
		// in the page delete handler.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'uael_forbidden',
				__( 'You are not allowed to restore this post.', 'uael' ),
				array( 'status' => 403 )
			);
		}

		if ( 'elementor-hf' === $post->post_type ) {
			return new \WP_Error(
				'uael_use_template_api',
				__( 'Use templates/delete for HFE templates.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		if ( 'trash' !== $post->post_status ) {
			return new \WP_Error(
				'uael_not_trashed',
				__( 'Post is not in the trash.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		$result = wp_untrash_post( $post_id );

		if ( ! $result ) {
			return new \WP_Error(
				'uael_restore_failed',
				__( 'Failed to restore post from trash.', 'uael' ),
				array( 'status' => 500 )
			);
		}

		$restored_post = get_post( $post_id );

		return array(
			'success'    => true,
			'post_id'    => $post_id,
			'new_status' => $restored_post->post_status,
			'message'    => sprintf(
				/* translators: %s: post title */
				__( '"%s" restored from trash.', 'uael' ),
				get_the_title( $post_id )
			),
		);
	}
}
