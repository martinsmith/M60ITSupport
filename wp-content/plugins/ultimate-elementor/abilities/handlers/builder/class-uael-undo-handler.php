<?php
/**
 * Builder Undo Handler.
 *
 * Reverts the most recent AI builder change on a post by restoring the snapshot
 * captured automatically before that change (see UAEL_Element_Helpers::save_elementor_data()).
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Undo_Handler
 *
 * Implements UAEL_Ability_Handler for the builder/undo ability.
 *
 * @since 1.45.0
 */
class UAEL_Undo_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'builder-undo';
	}

	/**
	 * Registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Undo Last Builder Change', 'uael' ),
			'description'         => __( 'Reverts the most recent AI builder change on a post by restoring the snapshot taken automatically just before that change. Keeps a single level of undo; for older versions use Elementor revision history.', 'uael' ),
			'category'            => 'uael-builder',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'The post, page, or HFE template ID to undo the last change on.', 'uael' ),
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
					'idempotent'   => false,
					'instructions' => 'Use when the user says the last builder edit was a mistake. Restores the single most recent change only. Confirm the post_id with the user first. If no snapshot exists, tell the user to use Elementor\'s revision history instead.',
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
			),
		);
	}

	/**
	 * Execute: restore the last pre-edit snapshot.
	 *
	 * @param array $input Validated input.
	 * @return array|WP_Error Result or error.
	 */
	public function execute( $input ) {
		$post_id = absint( $input['post_id'] ?? $input['template_id'] ?? 0 );

		// Object-level authorization + Elementor post validation (shared trait).
		$post = $this->validate_elementor_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$snapshot = get_post_meta( $post->ID, UAEL_Element_Helpers::UNDO_SNAPSHOT_META, true );

		if ( empty( $snapshot ) || empty( $snapshot['data'] ) ) {
			return new WP_Error(
				'uael_no_undo_snapshot',
				__( 'No recent change is available to undo for this post. Try Elementor revision history.', 'uael' ),
				array( 'status' => 404 )
			);
		}

		// Restore the previous data directly (raw write) and refresh caches.
		update_post_meta( $post->ID, '_elementor_data', wp_slash( $snapshot['data'] ) );
		UAEL_Element_Helpers::clear_elementor_cache( $post->ID );

		// Single level of undo: consume the snapshot so it cannot be applied twice.
		delete_post_meta( $post->ID, UAEL_Element_Helpers::UNDO_SNAPSHOT_META );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: post title */
				__( 'Reverted the last change on "%s".', 'uael' ),
				$post->post_title
			),
		);
	}
}
