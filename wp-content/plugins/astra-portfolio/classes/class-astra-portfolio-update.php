<?php
/**
 * Astra Portfolio
 *
 * @package Astra Portfolio
 * @since 1.0.1
 */

if ( ! class_exists( 'Astra_Portfolio_Update' ) ) :

	/**
	 * Astra_Portfolio_Update
	 *
	 * @since 1.0.1
	 */
	class Astra_Portfolio_Update {

		/**
		 * Instance
		 *
		 * @var object Class object.
		 * @access private
		 * @since 1.0.1
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @since 1.0.1
		 * @return object initialized object of class.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 1.0.1
		 */
		public function __construct() {
			add_action( 'admin_init', __CLASS__ . '::init' );
		}

		/**
		 * Init
		 *
		 * @since 1.0.1
		 * @return void
		 */
		public static function init() {
			do_action( 'astra_portfolio_update_before' );

			// Get auto saved version number.
			$saved_version = get_option( 'astra-portfolio-auto-version', false );

			// Update auto saved version number.
			if ( ! $saved_version ) {
				update_option( 'astra-portfolio-auto-version', ASTRA_PORTFOLIO_VER );
			}

			// If equals then return.
			if ( version_compare( $saved_version, ASTRA_PORTFOLIO_VER, '=' ) ) {
				return;
			}

			// Update to older version than 1.0.1 version.
			if ( version_compare( $saved_version, '1.0.1', '<' ) ) {
				self::v_1_0_1();
			}

			// Update to older version than 1.0.2 version.
			if ( version_compare( $saved_version, '1.0.2', '<' ) ) {
				self::v_1_0_2();
			}

			// Update to older version than 1.0.5 version.
			if ( version_compare( $saved_version, '1.0.5', '<' ) ) {
				self::v_1_0_5();
			}

			// Update to older version than 1.1.1 version.
			if ( version_compare( $saved_version, '1.1.1', '<' ) ) {
				self::v_1_1_1();
			}

			// Update to older version than 1.3.0 version.
			if ( version_compare( $saved_version, '1.3.0', '<' ) ) {
				self::v_1_3_0();
			}

			// Update to older version than 1.5.0 version.
			if ( version_compare( $saved_version, '1.5.0', '<' ) ) {
				self::v_1_5_0();
			}

			// Update to older version than 1.7.2 version.
			if ( version_compare( $saved_version, '1.7.2', '<' ) ) {
				self::v_1_7_2();
			}

			// Update to older version than 1.2.0 version.
			if ( version_compare( $saved_version, '1.11.0', '<' ) ) {
				self::v_1_11_0();
			}

			// Update auto saved version number.
			update_option( 'astra-portfolio-auto-version', ASTRA_PORTFOLIO_VER );

			do_action( 'astra_portfolio_update_after' );
		}

		/**
		 * Set featured image IDs in Astra portfolio image meta.
		 *
		 * @since 1.0.1
		 * @return void
		 */
		public static function v_1_0_1() {
			$args = array(
				'post_type'      => 'astra-portfolio',

				// Query performance optimization.
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			);

			$query    = new WP_Query( $args );
			$excludes = array();

			// Have posts?
			if ( $query->have_posts() ) :

				foreach ( $query->posts as $key => $post_id ) {
					$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
					update_post_meta( $post_id, 'astra-portfolio-image-id', absint( $thumbnail_id ) );
				}

			endif;
		}

		/**
		 * Set all recent portfolios as a excluded.
		 * Because, We have now keep the `Single Page` portfolio type visible & query-able.
		 *
		 * @since 1.0.2
		 * @return void
		 */
		public static function v_1_0_2() {
			$args = array(
				'post_type'      => 'astra-portfolio',

				// Query performance optimization.
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			);

			$query    = new WP_Query( $args );
			$excludes = array();

			// Have posts?
			if ( $query->have_posts() ) :

				$exclude_ids = (array) $query->posts;
				if ( is_array( $exclude_ids ) ) {
					update_option( 'astra_portfolio_excludes', $exclude_ids );
				}

			endif;
		}

		/**
		 * Set imported portfolio item ids & refresh the Graupi data.
		 *
		 * @since 1.0.5
		 * @return void
		 */
		public static function v_1_0_5() {
			// Fetch the latest plugin info. Now, Plugin name is change with 'WP Portfolio'.
			update_site_option( 'bsf_force_check_extensions', true );

			// Set all imported portfolio items in exclude sites option which prevent
			// from re-import the item.
			$args = array(
				'post_type'      => 'astra-portfolio',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'astra-remote-post-id',
						'value'   => '',
						'compare' => '!=',
					),
				),

				// Query performance optimization.
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'post_status'    => 'any',
				'posts_per_page' => -1,

			);

			$query    = new WP_Query( $args );
			$excludes = array();

			// Have posts?
			if ( $query->have_posts() ) :

				while ( $query->have_posts() ) {
					$query->the_post();

					$excluded_id = get_post_meta( get_the_ID(), 'astra-remote-post-id', true );

					if ( ! empty( $excluded_id ) ) {
						$excludes[] = $excluded_id;
					}
				}

				wp_reset_postdata();

			endif;

			update_option( 'astra_portfolio_batch_excluded_sites', $excludes );
		}

		/**
		 * Set imported portfolio item ids & refresh the Graupi data.
		 *
		 * @since 1.1.1
		 * @return void
		 */
		public static function v_1_1_1() {
			// Set all imported portfolio items in exclude sites option which prevent
			// from re-import the item.
			$args = array(
				'post_type'      => 'astra-portfolio',

				// Query performance optimization.
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'post_status'    => 'any',
				'posts_per_page' => -1,

			);

			$query = new WP_Query( $args );

			$post_ids = (array) $query->posts;
			foreach ( $post_ids as $key => $post_id ) {
				$open_in = (int) get_post_meta( $post_id, 'astra-site-open-in-iframe', true );
				if ( 1 === $open_in ) {
					update_post_meta( $post_id, 'astra-site-open-portfolio-in', 'iframe' );
				}
			}

		}

		/**
		 * Set imported portfolio item ids & refresh the Graupi data.
		 *
		 * @since 1.3.0
		 * @return void
		 */
		public static function v_1_3_0() {

			// Default settings.
			$update = array(
				'per-page' => '15',
			);

			// Stored.
			$settings = get_option( 'astra-portfolio-settings', array() );

			// Merge settings.
			$updated_settings = wp_parse_args( $update, $settings );

			// Update.
			update_option( 'astra-portfolio-settings', $updated_settings );

		}

		/**
		 * Set default style
		 *
		 * @since 1.5.0
		 * @return void
		 */
		public static function v_1_5_0() {

			// Default settings.
			$update = array(
				'grid-style' => 'default',
			);

			// Stored.
			$settings = get_option( 'astra-portfolio-settings', array() );

			// Merge settings.
			$updated_settings = wp_parse_args( $update, $settings );

			// Update.
			update_option( 'astra-portfolio-settings', $updated_settings );
		}

		/**
		 * Set default style
		 *
		 * @since 1.7.2
		 * @return void
		 */
		public static function v_1_7_2() {
			delete_option( 'astra-portfolio-batch-process' );
			delete_option( 'astra_portfolio_total_requests' );
			delete_option( 'astra_portfolio_site_page_1' );
			delete_option( 'astra_portfolio_site_page_2' );
			delete_option( 'astra_portfolio_site_page_3' );
			delete_option( 'astra-portfolio-site-import-count' );
			delete_option( 'astra-portfolio-image-import-count' );
			delete_option( 'astra-portfolio-batch-process-string' );
			delete_option( 'astra-portfolio-batch-process-all-complete' );
		}

		/**
		 * Set default per page value
		 *
		 * @since 1.11.0
		 * @return void
		 */
		public static function v_1_11_0() {

			// Default settings.
			$defaults = array(
				'per-page' => '15',
				'par-page' => '15',
			);

			// Stored.
			$settings = get_option( 'astra-portfolio-settings', $defaults );

			// Merge settings.
			$settings['per-page'] = isset( $settings['par-page'] ) ? $settings['par-page'] : $defaults['par-page'];

			// Update.
			update_option( 'astra-portfolio-settings', $settings );
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio_Update::get_instance();

endif;
