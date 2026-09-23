<?php
/**
 * Page List Handler.
 *
 * Lists Elementor-edited pages and posts with filtering options.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Page_List_Handler
 *
 * Implements UAEL_Ability_Handler for the pages/list ability.
 *
 * @since 1.45.0
 */
class UAEL_Page_List_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'pages-list';
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
			'label'               => __( 'List Pages', 'uael' ),
			'description'         => __( 'Lists Elementor-edited pages and posts with optional filtering by type, status, and search.', 'uael' ),
			'category'            => 'uael-pages',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_type' => array(
						'type'        => 'string',
						'enum'        => array( 'page', 'post', 'any' ),
						'default'     => 'page',
						'description' => __( 'Filter by post type. Use "any" for all supported types.', 'uael' ),
					),
					'status'    => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft', 'any' ),
						'default'     => 'publish',
						'description' => __( 'Filter by post status.', 'uael' ),
					),
					'per_page'  => array(
						'type'        => 'integer',
						'default'     => 20,
						'description' => __( 'Number of results per page. Maximum 100.', 'uael' ),
					),
					'search'    => array(
						'type'        => 'string',
						'description' => __( 'Search by title.', 'uael' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'pages' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'                 => array( 'type' => 'integer' ),
								'title'              => array( 'type' => 'string' ),
								'post_type'          => array( 'type' => 'string' ),
								'status'             => array( 'type' => 'string' ),
								'edit_url'           => array( 'type' => 'string' ),
								'elementor_edit_url' => array( 'type' => 'string' ),
								'view_url'           => array( 'type' => 'string' ),
								'modified_date'      => array( 'type' => 'string' ),
							),
						),
					),
					'total' => array( 'type' => 'integer' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => true,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Lists pages and posts edited with Elementor. Use to show the user their content or find a specific page before updating.',
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
	 * @return array List of pages and total count.
	 */
	public function execute( $input ) {
		$post_type = ! empty( $input['post_type'] ) ? sanitize_text_field( $input['post_type'] ) : 'page';
		$status    = ! empty( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'publish';
		$per_page  = ! empty( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
		$per_page  = min( $per_page, 100 );

		// Build post type argument.
		if ( 'any' === $post_type ) {
			$supported_cpts = get_option( 'elementor_cpt_support', array( 'page', 'post' ) );
			$query_types    = is_array( $supported_cpts ) ? $supported_cpts : array( 'page', 'post' );
		} else {
			$query_types = array( $post_type );
		}

		// Exclude elementor-hf CPT — those are templates, not pages.
		$query_types = array_diff( $query_types, array( 'elementor-hf' ) );

		$args = array(
			'post_type'      => $query_types,
			'post_status'    => 'any' === $status ? array( 'publish', 'draft' ) : $status,
			'posts_per_page' => $per_page,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_elementor_edit_mode',
					'value' => 'builder',
				),
			),
		);

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		$query = new \WP_Query( $args );
		$pages = array();

		foreach ( $query->posts as $post ) {
			$pages[] = array(
				'id'                 => $post->ID,
				'title'              => get_the_title( $post->ID ),
				'post_type'          => $post->post_type,
				'status'             => $post->post_status,
				'edit_url'           => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
				'elementor_edit_url' => admin_url( 'post.php?post=' . $post->ID . '&action=elementor' ),
				'view_url'           => get_permalink( $post->ID ),
				'modified_date'      => $post->post_modified,
			);
		}

		return array(
			'pages' => $pages,
			'total' => (int) $query->found_posts,
		);
	}
}
