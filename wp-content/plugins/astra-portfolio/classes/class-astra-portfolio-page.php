<?php
/**
 * Astra Portfolio
 *
 * @package Astra Portfolio
 * @since 1.0.0
 */

if ( ! class_exists( 'Astra_Portfolio_Page' ) ) :

	/**
	 * Astra_Portfolio_Page
	 *
	 * @since 1.0.0
	 */
	class Astra_Portfolio_Page {

		/**
		 * Last Export Checksums
		 *
		 * @since 1.11.0
		 * @var object Class object.
		 * @access public
		 */
		public $last_export_checksums;

		/**
		 * View all actions
		 *
		 * @since 1.0.0
		 * @var array $view_actions
		 */
		public static $view_actions = array();

		/**
		 * Menu page title
		 *
		 * @since 1.0.0
		 * @var array $menu_page_title
		 */
		public static $menu_page_title = 'WP Portfolio';

		/**
		 * Plugin slug
		 *
		 * @since 1.0.0
		 * @var array $plugin_slug
		 */
		public static $plugin_slug = 'astra-portfolio';

		/**
		 * Default Menu position
		 *
		 * @since 1.0.0
		 * @var array $default_menu_position
		 */
		public static $default_menu_position = 'edit.php?post_type=astra-portfolio';

		/**
		 * Parent Page Slug
		 *
		 * @since 1.0.0
		 * @var array $parent_page_slug
		 */
		public static $parent_page_slug = 'general';

		/**
		 * Current Slug
		 *
		 * @since 1.0.0
		 * @var array $current_slug
		 */
		public static $current_slug = 'general';

		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class Instance.
		 * @since 1.0.0
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @since 1.0.0
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
		 */
		public function __construct() {

			if ( ! is_admin() ) {
				return;
			}

			add_action( 'after_setup_theme', __CLASS__ . '::init_admin_settings', 102 );
			add_action( 'plugin_action_links_' . ASTRA_PORTFOLIO_BASE, array( $this, 'action_links' ) );
			add_filter( 'admin_url', array( $this, 'admin_url' ), 10, 3 );
			add_action( 'before_delete_post', array( $this, 'delete_remote_id_from_excluded_ids' ) );
			add_action( 'wp_ajax_astra_portfolio_batch_status', array( $this, 'show_batch_status' ) );

			add_action( 'wp_ajax_astra_portfolio_import_term', array( $this, 'import_term' ) );

			add_action( 'wp_ajax_astra-portfolio-get-request-count', array( $this, 'requests_count' ) );

			add_action( 'wp_ajax_astra-portfolio-import-sites', array( $this, 'import_sites' ) );
			add_action( 'wp_ajax_astra-portfolio-get-all-data', array( $this, 'get_all_data_ajax' ) );
			add_action( 'wp_ajax_astra-portfolio-import-single-site', array( $this, 'import_site' ) );
			add_action( 'wp_ajax_astra-portfolio-save-settings', array( $this, 'save_ajax_settings' ) );
			add_action( 'wp_ajax_astra-portfolio-checksums-check', array( $this, 'check_checksums' ) );
			add_action( 'wp_ajax_astra-portfolio-checksums-update', array( $this, 'update_checksums' ) );
		}

		/**
		 * Save AJAX settings
		 *
		 * @since 1.11.0
		 *
		 * @return void
		 */
		public function save_ajax_settings() {
			check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'Permission denied.', 'astra-portfolio' ) );
			}

			self::get_instance()->save_settings();

			wp_send_json_success();
		}

		/**
		 * Import Site
		 *
		 * @since 1.11.0
		 *
		 * @param  array $args Site arguments.
		 * @return void
		 */
		public function import_site( $args = array() ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'astra-portfolio' ) );
				}
			}

			$requests    = get_option(
				'astra-portfolio-requests',
				array(
					'pages'       => 0,
					'no_of_items' => 0,
				)
			);
			$no_of_items = isset( $requests['no_of_items'] ) ? absint( $requests['no_of_items'] ) : 0;

			$defaults = array(
				'site_id'   => isset( $_POST['site_id'] ) ? $_POST['site_id'] : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'site_data' => isset( $_POST['site_data'] ) ? $_POST['site_data'] : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			);

			$args = wp_parse_args( $args, $defaults );

			$site_id = $args['site_id'] ? sanitize_key( $args['site_id'] ) : 0;
			$site_id = str_replace( 'id-', '', $site_id );

			global $wpdb;
			$exist_ids = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT `post_id` FROM {$wpdb->postmeta} WHERE meta_key='astra-remote-post-id' AND meta_value=%s",
					$site_id
				)
			);

			if ( $exist_ids ) {
				astra_portfolio_log( 'PORTFOLIO: Exists Remote ID: ' . $site_id . ' ' . wp_json_encode( $exist_ids ), 'debug' );
			} else {
				$featured_image_url = isset( $args['site_data']['featured-image-url'] ) ? esc_url_raw( $args['site_data']['featured-image-url'] ) : '';

				// Import Featured Image.
				if ( ! empty( $featured_image_url ) ) {
					$image = array(
						'id'  => wp_rand( 0000, 9999 ),
						'url' => $featured_image_url,
					);

					$downloaded_image = Astra_Portfolio_Import_Image::get_instance()->import( $image );
				}

				$site_page_builder = Astra_Portfolio_Helper::get_page_setting( 'page-builder', 0 );

				$title    = ( isset( $args['site_data']['title'] ) ) ? sanitize_text_field( $args['site_data']['title'] ) : '';
				$site_url = ( isset( $args['site_data']['astra-site-url'] ) ) ? esc_url_raw( $args['site_data']['astra-site-url'] ) : '';

				$category_id_mapping = (array) get_option( 'astra-portfolio-categories-id-mapping', array() );

				$old_categories = isset( $args['site_data']['astra-site-category'] ) ? array_map( 'trim', $args['site_data']['astra-site-category'] ) : array();

				$site_categories = array();
				if ( ! empty( $old_categories ) ) {
					foreach ( $old_categories as $cat_id => $cat_slug ) {
						if ( array_key_exists( $cat_id, $category_id_mapping ) ) {
							$site_categories[] = $category_id_mapping[ $cat_id ];
						}
					}
				}

				$other_category_id_mapping = (array) get_option( 'astra-portfolio-other-categories-id-mapping', array() );

				$old_page_builder_slug = isset( $args['site_data']['astra-site-page-builder'] ) ? sanitize_key( $args['site_data']['astra-site-page-builder'] ) : '';

				$page_builder_term = get_term_by( 'slug', $old_page_builder_slug, 'astra-portfolio-other-categories' );
				$page_builder_id   = 0;
				if ( $page_builder_term ) {
					$page_builder_id = $page_builder_term->term_id;
				}

				// New portfolio.
				$args = array(
					'post_type'   => 'astra-portfolio',
					'post_status' => 'draft',
					'post_title'  => $title,
					'meta_input'  => array(
						'astra-remote-post-id' => $site_id,
						'astra-site-url'       => $site_url,
						'astra-portfolio-type' => 'iframe',
					),
				);

				// Create new post and get new post ID.
				$post_id = wp_insert_post( $args );

				if ( $post_id && ! is_wp_error( $post_id ) ) {

					if ( $site_categories ) {
						wp_set_post_terms( $post_id, $site_categories, 'astra-portfolio-categories' );
					}

					if ( $page_builder_id ) {
						wp_set_post_terms( $post_id, $page_builder_id, 'astra-portfolio-other-categories' );
					}

					if ( ! empty( $featured_image_url ) ) {

						$image = array(
							'id'  => wp_rand( 0000, 9999 ),
							'url' => $featured_image_url,
						);

						$downloaded_image = Astra_Portfolio_Import_Image::get_instance()->import( $image );

						// Is image downloaded.
						if ( $downloaded_image['id'] !== $image['id'] ) {
							// And finally assign featured image to post.
							set_post_thumbnail( $post_id, $downloaded_image['id'] );

							// Add portfolio image meta.
							update_post_meta( $post_id, 'astra-portfolio-image-id', $downloaded_image['id'] );
						}
					}

					astra_portfolio_log( 'PORTFOLIO: Imported ' . get_the_title( $post_id ) . '(' . $post_id . ')', 'success' );

				} else {
					$message = 'Portfolio already exist!';
					if ( is_wp_error( $post_id ) ) {
						$message = $post_id->get_error_message();
					}

					astra_portfolio_log( 'PORTFOLIO: Failed! ' . $message . ' - ' . $title, 'error' );
				}
			}

		}

		/**
		 * Get all data Ajax
		 *
		 * @since  1.11.0
		 * @return void
		 */
		public function get_all_data_ajax() {
			check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'Permission denied.', 'astra-portfolio' ) );
			}

			$all_data = $this->get_all_data();

			wp_send_json_success( $all_data );
		}

		/**
		 * Get all data
		 *
		 * @since  1.11.0
		 *
		 * @return array
		 */
		public function get_all_data() {

			$all_data       = array();
			$requests       = get_option( 'astra-portfolio-requests', array( 'pages' => 0 ) );
			$total_requests = isset( $requests['pages'] ) ? absint( $requests['pages'] ) : 0;

			for ( $page = 1; $page <= $total_requests; $page++ ) {
				$current_data = get_option( 'astra-portfolio-raw-sites-' . $page, array() );
				if ( ! empty( $current_data ) ) {
					foreach ( $current_data as $page_id => $page_data ) {

						if ( ! empty( $page_data ) ) {
							$all_data[ $page_id ] = $page_data;
						}
					}
				}
			}

			return $all_data;
		}

		/**
		 * Import Sites
		 *
		 * @since 1.11.0
		 *
		 * @param array $args Site arguments.
		 * @return void
		 */
		public function import_sites( $args = array() ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'astra-portfolio' ) );
				}
			}

			$defaults = array(
				'page_no'      => isset( $_POST['page_no'] ) ? absint( $_POST['page_no'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'page_builder' => isset( $_POST['page_builder'] ) ? sanitize_key( $_POST['page_builder'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			);

			$args = wp_parse_args( $args, $defaults );

			$page_no      = isset( $args['page_no'] ) ? absint( $args['page_no'] ) : 0;
			$page_builder = isset( $args['page_builder'] ) ? sanitize_key( $args['page_builder'] ) : '';

			astra_portfolio_log( 'Requesting Page ' . $page_no . '..', 'debug' );
			astra_portfolio_log( $args, 'debug' );

			if ( $page_no ) {

				$api_args = array(
					'timeout' => 60,
				);

				$args = array(
					'page'         => $page_no,
					'per_page'     => 100,
					'page-builder' => $page_builder,
					'_site_fields' => 'title,featured-image-url,astra-site-url,astra-site-category,astra-site-page-builder',
				);

				$url = add_query_arg( $args, 'https://websitedemos.net/wp-json/astra-sites/v1/sites-and-pages' );

				astra_portfolio_log( $args, 'debug' );

				$result   = array();
				$response = wp_remote_get( $url, $api_args );
				if ( ! is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 200 ) {
					$result = json_decode( wp_remote_retrieve_body( $response ), true );
					update_option( 'astra-portfolio-raw-sites-' . $page_no, $result );

					astra_portfolio_log( 'Found ' . count( $result ) . ' sites.', 'success' );

					if ( astra_portfolio_doing_cli() ) {
						Astra_Portfolio_Helper::get_instance()->generate_json_file( 'astra-portfolio-raw-sites-' . $page_no, $result );
					}
				}

				astra_portfolio_log( 'Success', 'success', false );
			} else {
				astra_portfolio_log( 'Failed.', 'error', false );
			}
		}

		/**
		 * Update Checksums
		 *
		 * @since 1.11.0
		 * @return void
		 */
		public function update_checksums() {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );
			}

			$latest_checksums = get_option( 'astra-portfolio-last-export-checksums-latest', '' );

			$page_builder_id = Astra_Portfolio_Helper::get_page_setting( 'page-builder', 0 );

			update_option( 'astra-portfolio-last-export-checksums-' . $page_builder_id, $latest_checksums );

			astra_portfolio_batch_status( 'complete' );
			delete_option( 'astra-portfolio-batch-process-string' );

			// In AJAX this function return success.
			astra_portfolio_log( 'Latest Checksums Updated.', 'success' );
		}

		/**
		 * Check checksums
		 *
		 * @since 1.11.0
		 * @return void
		 */
		public function check_checksums() {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$sync_type = self::get_instance()->get_sync_type();
				if ( 'batch' === $sync_type ) {
					Astra_Portfolio_Batch::get_instance()->process_batch();
				}
			}

			if ( 'no' === $this->get_last_export_checksums() ) {
				astra_portfolio_batch_status( 'complete' );
				delete_option( 'astra-portfolio-batch-process-string' );

				astra_portfolio_log( 'Found latest checksums. No more sites available for import.', 'error' );
			} else {
				astra_portfolio_log( 'Found some latest sites. Starting the sync process.', 'success' );
			}
		}

		/**
		 * Get Last Exported Checksum Status
		 *
		 * @since 1.11.0
		 *
		 * @return string Checksums Status.
		 */
		public function get_last_export_checksums() {

			// Store the checksums for each page builder.
			$page_builder_id           = Astra_Portfolio_Helper::get_page_setting( 'page-builder', 0 );
			$old_last_export_checksums = get_option( 'astra-portfolio-last-export-checksums-' . $page_builder_id, '' );

			$new_last_export_checksums = $this->set_last_export_checksums();

			$checksums_status = 'no';

			if ( empty( $old_last_export_checksums ) ) {
				$checksums_status = 'yes';
			}

			if ( $new_last_export_checksums !== $old_last_export_checksums ) {
				$checksums_status = 'yes';
			}

			return apply_filters( 'astra_portfolio_checksums_status', $checksums_status );
		}

		/**
		 * Set Last Exported Checksum
		 *
		 * @since 1.11.0
		 *
		 * @return mixed
		 */
		public function set_last_export_checksums() {

			if ( ! empty( $this->last_export_checksums ) ) {
				return $this->last_export_checksums;
			}

			$api_args = array(
				'timeout' => 60,
			);

			$response = wp_remote_get( 'https://websitedemos.net/wp-json/astra-sites/v1/get-last-export-checksums', $api_args );
			if ( ! is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 200 ) {
				$result = json_decode( wp_remote_retrieve_body( $response ), true );

				// Set last export checksums.
				if ( ! empty( $result['last_export_checksums'] ) ) {

					update_option( 'astra-portfolio-last-export-checksums-latest', $result['last_export_checksums'] );

					$this->last_export_checksums = $result['last_export_checksums'];
				}
			}

			return $this->last_export_checksums;
		}

		/**
		 * Sites Requests Count
		 *
		 * @since 1.11.0
		 *
		 * @param  array $args Request count.
		 * @return void
		 */
		public function requests_count( $args = array() ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'astra-portfolio' ) );
				}
			}

			$defaults = array(
				'page_builder' => isset( $_REQUEST['page_builder'] ) ? sanitize_key( $_REQUEST['page_builder'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);

			$args = wp_parse_args( $args, $defaults );

			// Get total count.
			$total_requests = $this->get_total_requests( $args );
			if ( $total_requests ) {
				astra_portfolio_log(
					array(
						'total_requests' => $total_requests,
						'args'           => $args,
					),
					'success',
					false
				);
			} else {
				astra_portfolio_log(
					array(
						'total_requests' => $total_requests,
						'args'           => $args,
					),
					'error',
					false
				);
			}
		}

		/**
		 * Get Total Requests
		 *
		 * @since 1.11.0
		 *
		 * @param  array $args Request arguments.
		 * @return mixed
		 */
		public function get_total_requests( $args = array(), $retry_count = 0 ) {

			$max_retries = 10;

			if ( $retry_count >= $max_retries ) {
				astra_portfolio_log( 'get_total_requests: Max retries (' . $max_retries . ') reached. Aborting.', 'error' );
				return 0;
			}

			$api_args = array(
				'timeout' => 60,
			);

			$defaults = array(
				'per_page' => 100,
			);

			$args = wp_parse_args( $args, $defaults );

			$url = add_query_arg( $args, 'https://websitedemos.net/wp-json/astra-sites/v1/get-total-pages' );

			$response = wp_remote_get( $url, $api_args );

			if ( ! is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 200 ) {
				$total_requests = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( isset( $total_requests['pages'] ) ) {

					update_option( 'astra-portfolio-requests', $total_requests );

					if ( astra_portfolio_doing_cli() ) {
						Astra_Portfolio_Helper::get_instance()->generate_json_file( 'astra-portfolio-requests', $total_requests );
					}

					return $total_requests['pages'];
				}
			}

			return $this->get_total_requests( $args, $retry_count + 1 );
		}

		/**
		 * Import Categories.
		 *
		 * @since 1.11.0
		 *
		 * @return void
		 */
		public function import_categories() {
			$args = array(
				'new_taxonomy'           => 'astra-portfolio-categories',
				'taxonomy'               => 'astra-site-category',
				'import_status_string'   => esc_html__( 'Importing Categories..', 'astra-portfolio' ),
				'import_start_string'    => esc_html__( 'Importing Categories..', 'astra-portfolio' ),
				'import_complete_string' => esc_html__( 'Categories Imported Successfully.', 'astra-portfolio' ),
			);

			$this->import_term( $args );
		}

		/**
		 * Set Request Count
		 *
		 * @since 1.11.0
		 *
		 * @return void
		 */
		public function set_requests_count() {

			// Total Request by Page Builder.
			$page_builders = $this->get_page_builders();

			$page_builder_id   = Astra_Portfolio_Helper::get_page_setting( 'page-builder', 0 );
			$page_builder_slug = isset( $page_builders[ $page_builder_id ]['slug'] ) ? sanitize_key( $page_builders[ $page_builder_id ]['slug'] ) : '';
			$page_builder_name = isset( $page_builders[ $page_builder_id ]['name'] ) ? sanitize_text_field( $page_builders[ $page_builder_id ]['name'] ) : 'All';

			astra_portfolio_log( 'Importing "' . $page_builder_name . '" Page Builder Sites.' );

			// Download sites data in 100 sites.
			$args = array(
				'page_builder' => $page_builder_slug,
			);

			$this->requests_count( $args );
		}

		/**
		 * Store All Data
		 *
		 * @since 1.11.0
		 *
		 * @return void
		 */
		public function store_all_data() {

			$page_builders     = $this->get_page_builders();
			$page_builder_id   = Astra_Portfolio_Helper::get_page_setting( 'page-builder', 0 );
			$page_builder_slug = isset( $page_builders[ $page_builder_id ]['slug'] ) ? sanitize_key( $page_builders[ $page_builder_id ]['slug'] ) : '';
			$requests          = get_option( 'astra-portfolio-requests', array( 'pages' => 0 ) );
			$pages             = isset( $requests['pages'] ) ? absint( $requests['pages'] ) : 0;
			for ( $page_no = 1; $page_no <= $pages; $page_no++ ) {
				$args = array(
					'page_no'      => $page_no,
					'page_builder' => $page_builder_slug,
				);

				astra_portfolio_log( $args, 'debug' );
				$this->import_sites( $args );
			}
		}

		/**
		 * Import Other Categories
		 *
		 * @since 1.11.0
		 *
		 * @return void
		 */
		public function import_other_categories() {
			$args = array(
				'new_taxonomy'           => 'astra-portfolio-other-categories',
				'taxonomy'               => 'astra-site-page-builder',
				'import_status_string'   => esc_html__( 'Importing Other Categories..', 'astra-portfolio' ),
				'import_start_string'    => esc_html__( 'Importing Other Categories..', 'astra-portfolio' ),
				'import_complete_string' => esc_html__( 'Other Categories Imported Successfully.', 'astra-portfolio' ),
			);

			$this->import_term( $args );
		}

		/**
		 * Import Terms
		 *
		 * @since 1.11.0
		 *
		 * @param  array $args Term arguments.
		 * @return void
		 */
		public function import_term( $args = array() ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'astra-portfolio' ) );
				}
			}

			$defaults = array(
				'new_taxonomy'           => isset( $_POST['new_taxonomy'] ) ? sanitize_key( $_POST['new_taxonomy'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'taxonomy'               => isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'import_status_string'   => isset( $_POST['import_status_string'] ) ? wp_kses_post( $_POST['import_status_string'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'import_complete_string' => isset( $_POST['import_complete_string'] ) ? sanitize_text_field( $_POST['import_complete_string'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'import_start_string'    => isset( $_POST['import_start_string'] ) ? esc_html( $_POST['import_start_string'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			);

			$args = wp_parse_args( $args, $defaults );

			$new_taxonomy = isset( $args['new_taxonomy'] ) ? sanitize_key( $args['new_taxonomy'] ) : '';

			$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';

			$import_status_string = isset( $args['import_status_string'] ) ? wp_kses_post( $args['import_status_string'] ) : '';
			$import_start_string  = isset( $args['import_start_string'] ) ? esc_html( $args['import_start_string'] ) : '';

			$import_complete_string = isset( $args['import_complete_string'] ) ? sanitize_text_field( $args['import_complete_string'] ) : '';

			$terms = Astra_Portfolio_API::get_instance()->get_categories( $taxonomy );

			if ( ! $terms ) {
				astra_portfolio_log(
					array(
						'data'    => $terms,
						'message' => esc_html__( 'Invalid response.', 'astra-portfolio' ),
					),
					'error'
				);
			}

			$term_mapping = array();
			foreach ( $terms as $key => $term ) {
				$old_id      = isset( $term['id'] ) ? absint( $term['id'] ) : 0;
				$name        = isset( $term['name'] ) ? $term['name'] : '';
				$alias_of    = isset( $term['alias_of'] ) ? $term['alias_of'] : '';
				$description = isset( $term['description'] ) ? $term['description'] : '';
				$parent      = isset( $term['parent'] ) ? $term['parent'] : '';
				$slug        = isset( $term['slug'] ) ? $term['slug'] : '';

				if ( ! empty( $name ) ) {
					$term_mapping[] = array(
						'name' => $name,
						'args' => array(
							'alias_of'    => $alias_of,
							'description' => $description,
							'parent'      => $parent,
							'slug'        => $slug,
						),
						'meta' => array(
							'old_id' => $old_id,
						),
					);
				}
			}

			if ( ! empty( $import_status_string ) ) {
				update_option( 'astra-portfolio-batch-process-string', $import_status_string );
			}

			Astra_Portfolio_Admin::get_instance()->add_terms( $new_taxonomy, $term_mapping );

			if ( astra_portfolio_doing_cli() ) {
				Astra_Portfolio_Helper::get_instance()->generate_json_file( $new_taxonomy, $term_mapping );
			}

			astra_portfolio_log( $import_complete_string, 'success' );
		}

		/**
		 * Show the current batch process status
		 *
		 * @since 1.7.0
		 *
		 * @return void
		 */
		public function show_batch_status() {
			check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'Permission denied.', 'astra-portfolio' ) );
			}

			$message          = get_option( 'astra-portfolio-batch-process-string', 'Sync' );
			$process_complete = get_option( 'astra-portfolio-batch-process-all-complete', 'no' );

			if ( 'yes' === $process_complete ) {
				wp_send_json_error( $message );
			}

			wp_send_json_success( $message );
		}

		/**
		 * Remove excluded remote post ID to re-import it.
		 *
		 * @since 1.7.0
		 *
		 * @param int $postid Post ID.
		 */
		public function delete_remote_id_from_excluded_ids( $postid = 0 ) {

			$excluded_remote_id = get_post_meta( $postid, 'astra-remote-post-id', true );
			if ( empty( $excluded_remote_id ) ) {
				return;
			}

			$page_builder_id = Astra_Portfolio_Helper::get_page_setting( 'page-builder', 0 );

			delete_option( 'astra-portfolio-last-export-checksums-' . $page_builder_id );
		}

		/**
		 * Filters the admin area URL.
		 *
		 * @since 1.0.2
		 *
		 * @param string   $url     The complete admin area URL including scheme and path.
		 * @param string   $path    Path relative to the admin area URL. Blank string if no path is specified.
		 * @param int|null $blog_id Site ID, or null for the current site.
		 */
		public function admin_url( $url, $path, $blog_id ) {

			if ( 'post-new.php?post_type=astra-portfolio' !== $path ) {
				return $url;
			}

			$url  = get_site_url( $blog_id, 'wp-admin/', 'admin' );
			$path = 'edit.php?post_type=astra-portfolio&page=astra-portfolio-add-new';

			if ( $path && is_string( $path ) ) {
				$url .= ltrim( $path, '/' );
			}

			return $url;
		}

		/**
		 * Admin settings init
		 */
		public static function init_admin_settings() {

			self::$menu_page_title = __( 'Settings', 'astra-portfolio' );
			
			// Migrate analytics tracking options
			self::maybe_migrate_analytics_tracking();

			if ( isset( $_REQUEST['page'] ) && strpos( $_REQUEST['page'], self::$plugin_slug ) !== false ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				// Let extensions hook into saving.
				do_action( 'astra_portfolio_settings_scripts' );

				self::save_settings();
			}

			add_action( 'admin_menu', __CLASS__ . '::add_admin_menu', 99 );
			add_action( 'astra_portfolio_menu_general_action', __CLASS__ . '::general_page' );
			add_action( 'astra_portfolio_menu_style_action', __CLASS__ . '::style_page' );
			add_action( 'astra_portfolio_menu_advanced_action', __CLASS__ . '::advanced_page' );
			add_action( 'init', __CLASS__ . '::process_form', 11 );
			add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_scripts' );

			// Current user can edit?
			if ( current_user_can( 'edit_posts' ) ) {
				add_action( 'admin_menu', __CLASS__ . '::register' );
				add_filter( 'submenu_file', __CLASS__ . '::submenu_file', 999, 2 );
			}
		}

		/**
		 * Migrates analytics tracking option from 'bsf_analytics_optin' to 'astra-portfolio_analytics_optin'.
		 *
		 * Checks if the old analytics tracking option ('bsf_analytics_optin') is set to 'yes'
		 * and if the new option ('astra-portfolio_analytics_optin') is not already set.
		 * If so, updates the new tracking option to 'yes' to maintain user consent during migration.
		 *
		 * @since 1.13.2
		 * @access public
		 *
		 * @return void
		 */
		public static function maybe_migrate_analytics_tracking() {

			$old_tracking = get_site_option( 'bsf_analytics_optin', false );
			$new_tracking = get_site_option( 'astra-portfolio_analytics_optin', false );

			if ( 'yes' === $old_tracking && false === $new_tracking ) {
				update_site_option( 'astra-portfolio_analytics_optin', 'yes' );
				$time = get_option( 'bsf_analytics_installed_time' );
				update_site_option( 'astra-portfolio_analytics_installed_time', $time );
			}
		}

		/**
		 * Sets the active menu item for the builder admin submenu.
		 *
		 * @since 1.0.2
		 *
		 * @param string $submenu_file  Submenu file.
		 * @param string $parent_file   Parent file.
		 * @return string               Submenu file.
		 */
		public static function submenu_file( $submenu_file, $parent_file ) {
			global $pagenow;

			$screen = get_current_screen();

			if ( isset( $_GET['page'] ) && 'astra-portfolio-add-new' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$submenu_file = 'astra-portfolio-add-new';
			} elseif ( 'post.php' === $pagenow && 'astra-portfolio' === $screen->post_type ) {
				$submenu_file = 'edit.php?post_type=astra-portfolio';
			} elseif ( 'edit-tags.php' === $pagenow && 'astra-portfolio-tags' === $screen->taxonomy ) {
				$submenu_file = 'edit-tags.php?taxonomy=astra-portfolio-tags&post_type=astra-portfolio';
			} elseif ( 'edit-tags.php' === $pagenow && 'astra-portfolio-categories' === $screen->taxonomy ) {
				$submenu_file = 'edit-tags.php?taxonomy=astra-portfolio-categories&post_type=astra-portfolio';
			} elseif ( 'edit-tags.php' === $pagenow && 'astra-portfolio-other-categories' === $screen->taxonomy ) {
				$submenu_file = 'edit-tags.php?taxonomy=astra-portfolio-other-categories&post_type=astra-portfolio';
			}

			return $submenu_file;
		}

		/**
		 * Registers the add new portfolio form admin menu for adding portfolios.
		 *
		 * @since 1.0.2
		 *
		 * @return void
		 */
		public static function register() {
			global $submenu, $_registered_pages;

			$parent        = 'edit.php?post_type=astra-portfolio';
			$tags_url      = 'edit-tags.php?taxonomy=astra-portfolio-tags&post_type=astra-portfolio';
			$cat_url       = 'edit-tags.php?taxonomy=astra-portfolio-categories&post_type=astra-portfolio';
			$other_cat_url = 'edit-tags.php?taxonomy=astra-portfolio-other-categories&post_type=astra-portfolio';
			$add_new_hook  = 'astra-portfolio_page_astra-portfolio-add-new';

			$submenu[ $parent ]     = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ $parent ][10] = array( __( 'All Portfolio Items', 'astra-portfolio' ), 'edit_posts', $parent ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ $parent ][20] = array( __( 'Add New', 'astra-portfolio' ), 'edit_posts', 'astra-portfolio-add-new', '' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ $parent ][30] = array( __( 'Categories', 'astra-portfolio' ), 'manage_categories', $cat_url ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ $parent ][40] = array( __( 'Other Categories', 'astra-portfolio' ), 'manage_categories', $other_cat_url ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ $parent ][50] = array( __( 'Tags', 'astra-portfolio' ), 'manage_categories', $tags_url ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			add_action( $add_new_hook, __CLASS__ . '::add_new_page' );
			$_registered_pages[ $add_new_hook ] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		/**
		 * Add new page
		 *
		 * @since 1.0.2
		 */
		public static function add_new_page() {
			$types = self::get_portfolio_types();

			require_once ASTRA_PORTFOLIO_DIR . 'includes/add-new-form.php';
		}

		/**
		 * Create the portfolio from add new portfolio form.
		 *
		 * @since 1.0.2
		 *
		 * @return void
		 */
		public static function process_form() {
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 'astra-portfolio-add-new' !== $page ) {
				return;
			}

			if ( ! isset( $_POST['astra-portfolio-add-template'] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['astra-portfolio-add-template'], 'astra-portfolio-add-template-nonce' ) ) {
				return;
			}

			$title = sanitize_text_field( $_POST['astra-portfolio-template']['title'] );
			$type  = sanitize_text_field( $_POST['astra-portfolio-template']['type'] );

			// Insert portfolio.
			$post_id = wp_insert_post(
				array(
					'post_title'     => $title,
					'post_type'      => 'astra-portfolio',
					'post_status'    => 'draft',
					'ping_status'    => 'closed',
					'comment_status' => 'closed',
					'meta_input'     => array(
						'astra-portfolio-type' => $type,
					),
				)
			);

			// Redirect to the new portfolio.
			wp_safe_redirect( admin_url( '/post.php?post=' . $post_id . '&action=edit' ) );

			exit;
		}

		/**
		 * Get portfolio type
		 *
		 * @since 1.0.2
		 *
		 * @return array Portfolio types.
		 */
		public static function get_portfolio_types() {

			$all_types = apply_filters(
				'astra_portfolio_add_new_types',
				array(
					array(
						'key'   => 'iframe',
						'label' => __( 'Website', 'astra-portfolio' ),
					),
					array(
						'key'   => 'image',
						'label' => __( 'Image', 'astra-portfolio' ),
					),
					array(
						'key'   => 'video',
						'label' => __( 'Video', 'astra-portfolio' ),
					),
					array(
						'key'   => 'page',
						'label' => __( 'Single Page', 'astra-portfolio' ),
					),
				)
			);

			return $all_types;
		}

		/**
		 * View actions
		 */
		public static function get_view_actions() {

			if ( empty( self::$view_actions ) ) {

				$actions            = array(
					'general'  => array(
						'label'    => __( 'General', 'astra-portfolio' ),
						'show'     => true,
						'priority' => 10,
					),
					'style'    => array(
						'label'    => __( 'Style', 'astra-portfolio' ),
						'show'     => true,
						'priority' => 20,
					),
					'advanced' => array(
						'label'    => __( 'Advanced', 'astra-portfolio' ),
						'show'     => true,
						'priority' => 30,
					),
				);
				self::$view_actions = apply_filters( 'astra_portfolio_menu_options', $actions );
			}

			return self::$view_actions;
		}

		/**
		 * Save All admin settings here
		 */
		public static function save_settings() {

			// Only admins can save settings.
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Process only if tab slug is set.
			if ( ! isset( $_REQUEST['tab_slug'] ) ) {
				return;
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );
			} else {
				if ( ! wp_verify_nonce( $_REQUEST['astra-portfolio-import'], 'astra-portfolio-importing' ) ) {
					return;
				}
			}

			// Stored Settings.
			$stored_data = Astra_Portfolio_Helper::get_page_settings();

			if ( 'general' === $_REQUEST['tab_slug'] ) {

				$stored_data['other-categories'] = ( isset( $_REQUEST['other-categories'] ) ) ? sanitize_text_field( $_REQUEST['other-categories'] ) : false;

				$stored_data['categories'] = ( isset( $_REQUEST['categories'] ) ) ? sanitize_text_field( $_REQUEST['categories'] ) : false;

				$stored_data['show-search'] = ( isset( $_REQUEST['show-search'] ) ) ? sanitize_text_field( $_REQUEST['show-search'] ) : false;

				$stored_data['page-builder'] = ( isset( $_REQUEST['page-builder'] ) ) ? sanitize_text_field( $_REQUEST['page-builder'] ) : '';

				$stored_data['responsive-button'] = ( isset( $_REQUEST['responsive-button'] ) ) ? sanitize_text_field( $_REQUEST['responsive-button'] ) : false;
			}

			if ( 'style' === $_REQUEST['tab_slug'] ) {

				if ( isset( $_REQUEST['show-portfolio-on'] ) ) {
					$stored_data['show-portfolio-on'] = sanitize_text_field( $_REQUEST['show-portfolio-on'] );
				}
				if ( isset( $_REQUEST['grid-style'] ) ) {
					$stored_data['grid-style'] = sanitize_text_field( $_REQUEST['grid-style'] );
				}
				if ( isset( $_REQUEST['preview-bar-loc'] ) ) {
					$stored_data['preview-bar-loc'] = sanitize_text_field( $_REQUEST['preview-bar-loc'] );
				}
				if ( isset( $_REQUEST['no-more-sites-message'] ) ) {
					$stored_data['no-more-sites-message'] = wp_kses_post( stripcslashes( $_REQUEST['no-more-sites-message'] ) );
				}
				if ( isset( $_REQUEST['portfolio-title-loc'] ) ) {
					$stored_data['portfolio-title-loc'] = sanitize_text_field( $_REQUEST['portfolio-title-loc'] );
				}
				if ( isset( $_REQUEST['scroll-speed'] ) ) {
					$stored_data['scroll-speed'] = sanitize_text_field( $_REQUEST['scroll-speed'] );
				}

				$stored_data['enable-masonry'] = ( isset( $_REQUEST['enable-masonry'] ) ) ? sanitize_text_field( $_REQUEST['enable-masonry'] ) : false;

				if ( isset( $_REQUEST['no-of-columns'] ) ) {
					$stored_data['no-of-columns'] = absint( $_REQUEST['no-of-columns'] );
				}

				if ( isset( $_REQUEST['per-page'] ) ) {
					$stored_data['per-page'] = absint( $_REQUEST['per-page'] );
				}
			}

			if ( 'advanced' === $_REQUEST['tab_slug'] ) {

				if ( isset( $_REQUEST['rewrite'] ) ) {
					$stored_data['rewrite'] = sanitize_title( $_REQUEST['rewrite'] );
				}
				if ( isset( $_REQUEST['rewrite-tags'] ) ) {
					$stored_data['rewrite-tags'] = sanitize_title( $_REQUEST['rewrite-tags'] );
				}
				if ( isset( $_REQUEST['rewrite-categories'] ) ) {
					$stored_data['rewrite-categories'] = sanitize_title( $_REQUEST['rewrite-categories'] );
				}
				if ( isset( $_REQUEST['rewrite-other-categories'] ) ) {
					$stored_data['rewrite-other-categories'] = sanitize_title( $_REQUEST['rewrite-other-categories'] );
				}
				
				// Handle analytics opt-in.
				if ( isset( $_REQUEST['astra-portfolio_analytics_optin'] ) ) {
					update_site_option( 'astra-portfolio_analytics_optin', 'yes' );
				} else {
					update_site_option( 'astra-portfolio_analytics_optin', 'no' );
				}
			}

			// Update settings.
			update_option( 'astra-portfolio-settings', $stored_data );

			// Rewrite permalinks if new rewrite string found.
			if (
				isset( $_REQUEST['rewrite'] ) ||
				isset( $_REQUEST['rewrite-tags'] ) ||
				isset( $_REQUEST['rewrite-categories'] ) ||
				isset( $_REQUEST['rewrite-other-categories'] )
			) {
				flush_rewrite_rules();
			}

			// Let extensions hook into saving.
			do_action( 'astra_portfolio_settings_save' );
		}

		/**
		 * Enqueues the needed CSS/JS for Backend.
		 *
		 * @param  string $hook Current hook.
		 *
		 * @since 1.0.0
		 */
		public static function admin_scripts( $hook = '' ) {

			if ( 'astra-portfolio_page_astra-portfolio' === $hook ) {
				wp_register_script( 'astra-portfolio-api', ASTRA_PORTFOLIO_URI . 'assets/js/' . Astra_Portfolio::get_instance()->get_assets_js_path( 'astra-portfolio-api' ), array( 'jquery' ), ASTRA_PORTFOLIO_VER, true );
				wp_enqueue_style( 'astra-portfolio-admin-page', ASTRA_PORTFOLIO_URI . 'assets/css/' . Astra_Portfolio::get_instance()->get_assets_css_path( 'admin-page' ), null, ASTRA_PORTFOLIO_VER, 'all' );
				wp_enqueue_script( 'astra-portfolio-admin-page', ASTRA_PORTFOLIO_URI . 'assets/js/' . Astra_Portfolio::get_instance()->get_assets_js_path( 'admin-page' ), array( 'jquery' ), ASTRA_PORTFOLIO_VER, true );

				$l10n = array(
					'sync_type'            => self::get_sync_type(),
					'ajax_url'             => admin_url( 'admin-ajax.php' ),
					'admin_page_url'       => admin_url( 'edit.php?post_type=astra-portfolio' ),
					'settings_page_url'    => admin_url( 'edit.php?post_type=astra-portfolio&page=astra-portfolio' ),
					'_ajax_nonce'          => wp_create_nonce( 'astra-portfolio' ),
					'batch_started_notice' => '<div class="astra-portfolio-notice notice astra-active-notice notice-info" dismissible-meta="transient">
						<p>' . Astra_Portfolio_Batch::get_instance()->get_batch_started_message() . '</p>
					</div>',
				);
				wp_localize_script( 'astra-portfolio-admin-page', 'AstraPortfolioAdminPageVars', $l10n );
			}

			if ( 'astra-portfolio_page_astra-portfolio-add-new' === $hook ) {
				wp_enqueue_style( 'astra-portfolio-add-new-form', ASTRA_PORTFOLIO_URI . 'assets/css/' . Astra_Portfolio::get_instance()->get_assets_css_path( 'add-new-form' ), null, ASTRA_PORTFOLIO_VER, 'all' );
			}
		}

		/**
		 * Get Sync Type
		 *
		 * @since 1.11.0
		 *
		 * @return string
		 */
		public static function get_sync_type() {
			$sync_type = isset( $_GET['sync_type'] ) ? sanitize_key( $_GET['sync_type'] ) : 'batch'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return $sync_type;
		}

		/**
		 * Init Nav Menu
		 *
		 * @param mixed $action Action name.
		 * @since 1.0.0
		 */
		public static function init_nav_menu( $action = '' ) {

			if ( '' !== $action ) {
				self::render_tab_menu( $action );
			}
		}

		/**
		 * Render tab menu
		 *
		 * @param mixed $action Action name.
		 * @since 1.0.0
		 */
		public static function render_tab_menu( $action = '' ) {
			?>
			<div id="astra-portfolio-menu-page" class="wrap">
				<h1><?php esc_html_e( 'WP Portfolio', 'astra-portfolio' ); ?></h1>
				<?php self::render( $action ); ?>
			</div>
			<?php
		}

		/**
		 * Prints HTML content for tabs
		 *
		 * @param mixed $action Action name.
		 * @since 1.0.0
		 */
		public static function render( $action ) {
			?>
			<div class="nav-tab-wrapper">

				<?php
				$view_actions = self::get_view_actions();

				foreach ( $view_actions as $slug => $data ) {

					if ( ! $data['show'] ) {
						continue;
					}

					$url = self::get_page_url( $slug );

					if ( $slug === self::$parent_page_slug ) {
						update_option( 'astra_parent_page_url', $url );
					}

					$active = ( $slug === $action ) ? 'nav-tab-active' : '';
					?>
						<a class='nav-tab <?php echo esc_attr( $active ); ?>' href='<?php echo esc_url( $url ); ?>'> <?php echo esc_html( $data['label'] ); ?> </a>
				<?php } ?>
			</div><!-- .nav-tab-wrapper -->

			<?php
			// Settings update message.
			if ( isset( $_REQUEST['message'] ) && ( 'saved' === $_REQUEST['message'] || 'saved_ext' === $_REQUEST['message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				?>
					<div id="message" class="notice notice-success is-dismissive"><p> <?php esc_html_e( 'Settings saved successfully.', 'astra-portfolio' ); ?> </p></div>
				<?php
			}

		}

		/**
		 * Get and return page URL
		 *
		 * @param string $menu_slug Menu name.
		 * @since 1.0.0
		 * @return  string page url
		 */
		public static function get_page_url( $menu_slug ) {

			$parent_page = self::$default_menu_position;

			if ( strpos( $parent_page, '?' ) !== false ) {
				$query_var = '&page=' . self::$plugin_slug;
			} else {
				$query_var = '?page=' . self::$plugin_slug;
			}

			$parent_page_url = admin_url( $parent_page . $query_var );

			$url = $parent_page_url . '&action=' . $menu_slug;

			return esc_url( $url );
		}

		/**
		 * Add main menu
		 *
		 * @since 1.0.0
		 */
		public static function add_admin_menu() {

			$parent_page    = self::$default_menu_position;
			$page_title     = self::$menu_page_title;
			$capability     = 'manage_options';
			$page_menu_slug = self::$plugin_slug;
			$page_menu_func = __CLASS__ . '::menu_callback';

			add_submenu_page( 'edit.php?post_type=astra-portfolio', $page_title, $page_title, $capability, $page_menu_slug, $page_menu_func );
		}

		/**
		 * Menu callback
		 *
		 * @since 1.0.0
		 */
		public static function menu_callback() {

			$allowed_slugs = array( 'general', 'style', 'advanced' );
			$current_slug  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : self::$current_slug; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Validate against allowlist — fall back to default slug if invalid.
			if ( ! in_array( $current_slug, $allowed_slugs, true ) ) {
				$current_slug = self::$current_slug;
			}

			$active_tab   = str_replace( '_', '-', $current_slug );
			$current_slug = str_replace( '-', '_', $current_slug );

			?>
			<div class="astra-portfolio-menu-page-wrapper">
				<?php self::init_nav_menu( $active_tab ); ?>
				<?php do_action( 'astra_portfolio_menu_' . $current_slug . '_action' ); ?>
			</div>
			<?php
		}

		/**
		 * Check Cron Status
		 *
		 * Gets the current cron status by performing a test spawn. Cached for one hour when all is well.
		 *
		 * @since 1.7.0
		 *
		 * @param bool $cache Whether to use the cached result from previous calls.
		 * @return true|WP_Error Boolean true if the cron spawner is working as expected, or a WP_Error object if not.
		 */
		public static function test_cron( $cache = true ) {
			global $wp_version;

			if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
				return new WP_Error( 'wp_portfolio_cron_error', __( 'ERROR! Cron schedules are disabled by setting constant DISABLE_WP_CRON to true.<br/>To start the import process please enable the cron by setting false. E.g. define( \'DISABLE_WP_CRON\', false );', 'astra-portfolio' ) );
			}

			if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
				return new WP_Error( 'wp_portfolio_cron_error', __( 'ERROR! Cron schedules are disabled by setting constant ALTERNATE_WP_CRON to true.<br/>To start the import process please enable the cron by setting false. E.g. define( \'ALTERNATE_WP_CRON\', false );', 'astra-portfolio' ) );
			}

			$cached_status = get_transient( 'astra-portfolio-cron-test-ok' );

			if ( $cache && $cached_status ) {
				return true;
			}

			$sslverify     = version_compare( $wp_version, 4.0, '<' );
			$doing_wp_cron = sprintf( '%.22F', microtime( true ) );

			$cron_request = apply_filters(
				'cron_request',
				array(
					'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
					'key'  => $doing_wp_cron,
					'args' => array(
						'timeout'   => 3,
						'blocking'  => true,
						'sslverify' => apply_filters( 'https_local_ssl_verify', $sslverify ),
					),
				)
			);

			$cron_request['args']['blocking'] = true;

			$result = wp_remote_post( $cron_request['url'], $cron_request['args'] );

			if ( is_wp_error( $result ) ) {
				return $result;
			} elseif ( wp_remote_retrieve_response_code( $result ) >= 300 ) {
				return new WP_Error(
					'unexpected_http_response_code',
					sprintf(
						/* translators: 1: The HTTP response code. */
						__( 'Unexpected HTTP response code: %s', 'astra-portfolio' ),
						intval( wp_remote_retrieve_response_code( $result ) )
					)
				);
			} else {
				set_transient( 'astra-portfolio-cron-test-ok', 1, 3600 );
				return true;
			}

		}

		/**
		 * Include General page
		 *
		 * @since 1.0.0
		 * @since 1.7.0 Convert into the General page tab.
		 */
		public static function general_page() {

			$data = Astra_Portfolio_Helper::get_page_settings();

			$status = get_option( 'astra-portfolio-batch-process' );

			require_once ASTRA_PORTFOLIO_DIR . 'includes/general-page.php';
		}

		/**
		 * Include Style Page
		 *
		 * @since 1.7.0
		 */
		public static function style_page() {

			$data = Astra_Portfolio_Helper::get_page_settings();

			$status = get_option( 'astra-portfolio-batch-process' );

			require_once ASTRA_PORTFOLIO_DIR . 'includes/style-page.php';
		}

		/**
		 * Include Advanced page
		 *
		 * @since 1.7.0
		 */
		public static function advanced_page() {

			$data = Astra_Portfolio_Helper::get_page_settings();

			$status = get_option( 'astra-portfolio-batch-process' );

			require_once ASTRA_PORTFOLIO_DIR . 'includes/advanced-page.php';
		}

		/**
		 * Show action links on the plugin screen.
		 *
		 * @param   mixed $links Plugin Action links.
		 * @return  array
		 */
		public function action_links( $links ) {
			$action_links = array(
				'settings' => '<a href="' . admin_url( 'edit.php?post_type=astra-portfolio&page=astra-portfolio' ) . '" aria-label="' . esc_attr__( 'Settings', 'astra-portfolio' ) . '">' . esc_html__( 'Settings', 'astra-portfolio' ) . '</a>',
			);

			return array_merge( $action_links, $links );
		}

		/**
		 * Default portfolio type
		 *
		 * @since 1.3.0
		 *
		 * @return mixed
		 */
		public static function get_default_portfolio_type() {

			$default_type = apply_filters( 'astra_portfolio_default_portfolio_type', '' );

			$types = self::get_portfolio_types();

			foreach ( $types as $key => $type ) {
				if ( $type['key'] === $default_type ) {
					return $default_type;
				}
			}

			return '';
		}

		/**
		 * Complete Import Sites
		 *
		 * @since 1.8.0
		 * @return boolean
		 */
		public function complete_import_sites() {
			$site_import_count = (int) get_option( 'astra-portfolio-site-import-count', 0 );
			$exclude_ids       = (array) get_option( 'astra_portfolio_batch_excluded_sites', array() );
			$total_requests    = (array) get_option( 'astra_portfolio_total_requests', array( 'total' => '' ) );
			$total             = (int) $total_requests['total'];

			if ( $exclude_ids ) {
				$site_import_count = count( $exclude_ids );
			}

			if ( 0 === $total ) {
				return true;
			}

			if ( $site_import_count >= $total ) {
				return true;
			}

			return false;
		}

		/**
		 * Get Page Builders
		 *
		 * @since 1.11.0
		 *
		 * @return array
		 */
		public function get_page_builders() {
			return array(
				'33' => array(
					'slug' => 'elementor',
					'name' => esc_html__( 'Elementor', 'astra-portfolio' ),
				),
				'34' => array(
					'slug' => 'beaver-builder',
					'name' => esc_html__( 'Beaver Builder', 'astra-portfolio' ),
				),
				'41' => array(
					'slug' => 'brizy',
					'name' => esc_html__( 'Brizy', 'astra-portfolio' ),
				),
				'42' => array(
					'slug' => 'gutenberg',
					'name' => esc_html__( 'Gutenberg', 'astra-portfolio' ),
				),
			);
		}
	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio_Page::get_instance();

endif;
