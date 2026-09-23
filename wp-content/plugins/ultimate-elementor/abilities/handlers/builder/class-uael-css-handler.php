<?php
/**
 * CSS Handler.
 *
 * Forces Elementor to regenerate CSS files for a post or globally.
 * Unified handler replacing template-builder/regenerate-css.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_CSS_Handler
 *
 * Implements UAEL_Ability_Handler for the builder/regenerate-css ability.
 *
 * @since 1.45.0
 */
class UAEL_CSS_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'builder-regenerate-css';
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
			'label'               => __( 'Regenerate CSS', 'uael' ),
			'description'         => __( 'Forces Elementor to regenerate CSS files for a post so frontend changes appear immediately. Call after builder/build or any structural change.', 'uael' ),
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
						'description' => __( 'Any Elementor-enabled post ID. Pass 0 to regenerate all Elementor CSS globally.', 'uael' ),
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
					'instructions' => 'Call this after builder/build or any structural modification to ensure CSS is regenerated and changes appear on the frontend immediately. Accepts any Elementor-enabled post (templates, pages, posts). Pass post_id=0 to regenerate all CSS globally.',
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
		$post_id = absint( $input['post_id'] ?? $input['template_id'] ?? 0 );

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new \WP_Error(
				'uael_elementor_not_active',
				__( 'Elementor is not active.', 'uael' ),
				array( 'status' => 500 )
			);
		}

		if ( 0 === $post_id ) {
			// Global CSS regeneration is a site-wide, expensive operation that can
			// be abused as a performance-degradation vector by low-privilege users.
			// Restrict it to administrators, even though per-post regen allows editors.
			if ( ! current_user_can( 'manage_options' ) ) {
				return new \WP_Error(
					'uael_forbidden',
					__( 'You are not allowed to regenerate all Elementor CSS globally.', 'uael' ),
					array( 'status' => 403 )
				);
			}

			// Global CSS regeneration.
			\Elementor\Plugin::$instance->files_manager->clear_cache();

			return array(
				'success' => true,
				'message' => __( 'Regenerated all Elementor CSS files globally.', 'uael' ),
			);
		}

		// Validate post exists (accepts any Elementor-enabled post).
		$post = $this->validate_elementor_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		UAEL_Element_Helpers::clear_elementor_cache( $post_id );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: Post title */
				__( 'Regenerated CSS for "%s".', 'uael' ),
				$post->post_title
			),
		);
	}
}
