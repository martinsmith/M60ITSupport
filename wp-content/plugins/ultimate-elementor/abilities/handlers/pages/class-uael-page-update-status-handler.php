<?php
/**
 * Page Update Status Handler.
 *
 * Updates the publish/draft status of an Elementor page or post.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Page_Update_Status_Handler
 *
 * Implements UAEL_Ability_Handler for the pages/update-status ability.
 *
 * @since 1.45.0
 */
class UAEL_Page_Update_Status_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'pages-update-status';
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
			'label'               => __( 'Update Page Status', 'uael' ),
			'description'         => __( 'Publishes or unpublishes (drafts) an Elementor page or post.', 'uael' ),
			'category'            => 'uael-pages',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id', 'status' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'The page or post ID.', 'uael' ),
					),
					'status'  => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft' ),
						'description' => __( 'The new status: publish or draft.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'post_id'    => array( 'type' => 'integer' ),
					'old_status' => array( 'type' => 'string' ),
					'new_status' => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Changes a page/post between publish and draft status. Cannot be used on trashed posts — restore them first.',
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
		$new_status = sanitize_text_field( $input['status'] );
		$validation = $this->validate_elementor_post( $post_id );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$post       = $validation;
		$old_status = $post->post_status;

		if ( 'trash' === $old_status ) {
			return new \WP_Error(
				'uael_post_trashed',
				__( 'Post is in the trash. Use pages/restore first.', 'uael' ),
				array( 'status' => 400 )
			);
		}

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'    => true,
			'post_id'    => $post_id,
			'old_status' => $old_status,
			'new_status' => $new_status,
		);
	}
}
