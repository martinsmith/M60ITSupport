<?php
/**
 * Astra Portfolio Admin
 *
 * @since  1.0.0
 * @package Astra Portfolio
 */

if ( ! class_exists( 'Astra_Portfolio_Admin' ) ) :

	/**
	 * Astra_Portfolio_Admin
	 *
	 * @since 1.0.0
	 */
	class Astra_Portfolio_Admin {

		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
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
		 *
		 * @since 1.0.0
		 */
		private function __construct() {
			add_action( 'init', array( $this, 'register_post_and_taxonomies' ) );
			add_action( 'add_meta_boxes', array( $this, 'meta_box_settings' ) );
			add_action( 'admin_footer', array( $this, 'meta_box_templates' ) );
			add_action( 'save_post_astra-portfolio', array( $this, 'save_meta_boxes' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			add_filter( 'post_updated_messages', array( $this, 'filter_update_message' ) );
			add_filter( 'register_post_type_args', array( $this, 'change_portfolio_url_slug' ), 10, 2 );
			add_filter( 'register_taxonomy_args', array( $this, 'change_portfolio_taxonomy_url_slug' ), 10, 3 );

			add_action( 'admin_footer', __CLASS__ . '::show_nps_notice' );

			// Exclude image, video & website portfolio from the query.
			add_action( 'init', array( $this, 'exclude_portfolios' ) );

			add_action( 'save_post_astra-portfolio', array( $this, 'post_updated' ), 20, 3 );
			add_filter( 'admin_body_class', array( $this, 'add_portfolio_type_class' ) );
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_filter( 'post_row_actions', array( $this, 'hide_row_actions' ), 10, 2 );
		}

		/**
		 * Show the view row action link only for 'Single Page' portfolio type.
		 *
		 * @since 1.8.1
		 * @param string[] $actions An array of row action links. Defaults are
		 *                          'Edit', 'Quick Edit', 'Restore', 'Trash',
		 *                          'Delete Permanently', 'Preview', and 'View'.
		 * @param WP_Post  $post    The post object.
		 * @return array
		 */
		public function hide_row_actions( $actions, $post ) {

			// not portfolio type then return.
			if ( 'astra-portfolio' !== $post->post_type ) {
				return $actions;
			}

			$portfolio_type = get_post_meta( $post->ID, 'astra-portfolio-type', true );

			if ( ( empty( $portfolio_type ) || 'page' !== $portfolio_type ) && false !== apply_filters( 'astra_portfolio_exclude_portfolio_items', true ) ) {
				unset( $actions['view'] );
			}

			return $actions;
		}

		/**
		 * Load Astra Pro Text Domain.
		 * This will load the translation textdomain depending on the file priorities.
		 *      1. Global Languages /wp-content/languages/astra-portfolio/ folder
		 *      2. Local dorectory /wp-content/plugins/astra-portfolio/languages/ folder
		 *
		 * @since  1.4.0
		 * @return void
		 */
		public function load_textdomain() {
			// Default languages directory for Astra Pro.
			$lang_dir = ASTRA_PORTFOLIO_DIR . 'languages/';

			/**
			 * Filters the languages directory path to use for WP Portfolio.
			 *
			 * @param string $lang_dir The languages directory path.
			 */
			$lang_dir = apply_filters( 'astra_portfolio_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter.
			global $wp_version;

			$get_locale = get_locale();

			if ( $wp_version >= 4.7 ) {
				$get_locale = get_user_locale();
			}

			/**
			 * Language Locale for Astra Pro
			 *
			 * @var $get_locale The locale to use. Uses get_user_locale()` in WordPress 4.7 or greater,
			 *                  otherwise uses `get_locale()`.
			 */
			$locale = apply_filters( 'plugin_locale', $get_locale, 'astra-portfolio' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'astra-portfolio', $locale );

			// Setup paths to current locale file.
			$mofile_local  = $lang_dir . $mofile;
			$mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/astra-portfolio/ folder.
				load_textdomain( 'astra-portfolio', $mofile_global );
			} elseif ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/astra-portfolio/languages/ folder.
				load_textdomain( 'astra-portfolio', $mofile_local );
			} else {
				// Load the default language files.
				load_plugin_textdomain( 'astra-portfolio', false, $lang_dir );
			}
		}

		/**
		 * Exclude Portfolios
		 *
		 * Exclude portfolio items (website, image and video) from the query.
		 *
		 * @return void
		 * @since 1.1.0
		 */
		public function exclude_portfolios() {
			if ( apply_filters( 'astra_portfolio_exclude_portfolio_items', ! is_admin() ) ) {
				add_filter( 'posts_where', array( $this, 'where_clause' ), 20, 2 );
				add_filter( 'get_next_post_where', array( $this, 'post_navigation_clause' ), 20, 1 );
				add_filter( 'get_previous_post_where', array( $this, 'post_navigation_clause' ), 20, 1 );
			}
		}

		/**
		 * Fires once a post has been saved.
		 *
		 * The dynamic portion of the hook name, `$post->post_type`, refers to
		 * the post type slug.
		 *
		 * @since 1.0.2
		 *
		 * @param int     $post_ID Post ID.
		 * @param WP_Post $post    Post object.
		 * @param bool    $update  Whether this is an existing post being updated or not.
		 */
		public function post_updated( $post_ID, $post, $update ) {

			if ( 'astra-portfolio' !== $post->post_type ) {
				return;
			}

			$this->generate_excluded_ids( true );
		}

		/**
		 * Filters the post updated messages.
		 *
		 * @since 1.0.2
		 *
		 * @param array $messages Post updated messages. For defaults @see $messages declarations above.
		 */
		public function filter_update_message( $messages = array() ) {
			if ( 'astra-portfolio' !== get_current_screen()->id ) {
				return;
			}

			$portfolio_type = get_post_meta( get_the_ID(), 'astra-portfolio-type', true );
			if ( 'page' === $portfolio_type ) {
				return $messages;
			}

			$messages['post'][1]  = __( 'Post updated.', 'astra-portfolio' );
			$messages['post'][6]  = __( 'Post published.', 'astra-portfolio' );
			$messages['post'][8]  = __( 'Post submitted.', 'astra-portfolio' );
			$messages['post'][9]  = __( 'Post scheduled.', 'astra-portfolio' );
			$messages['post'][10] = __( 'Post draft updated.', 'astra-portfolio' );

			return $messages;
		}

		/**
		 * Admin Scripts
		 *
		 * @since 1.0.1
		 *
		 * @param  string $hook Current page hook.
		 * @return void
		 */
		public function admin_scripts( $hook = '' ) {

			if ( 'astra-portfolio' !== get_current_screen()->id && 'edit-astra-portfolio' !== get_current_screen()->id ) {
				return;
			}

			wp_enqueue_media();

			wp_enqueue_script( 'astra-portfolio-post', ASTRA_PORTFOLIO_URI . 'assets/js/' . Astra_Portfolio::get_instance()->get_assets_js_path( 'post' ), array( 'wp-util', 'jquery' ), ASTRA_PORTFOLIO_VER, true );
			wp_enqueue_style( 'astra-portfolio-post', ASTRA_PORTFOLIO_URI . 'assets/css/' . Astra_Portfolio::get_instance()->get_assets_css_path( 'post' ), null, ASTRA_PORTFOLIO_VER, 'all' );
		}

		/**
		 * Get portfolio image URL.
		 *
		 * @since 1.0.1
		 *
		 * @param  string $image_id Attachment image ID.
		 * @return string           Image URL.
		 */
		private function get_portfolio_image_url( $image_id = '' ) {

			if ( empty( $image_id ) ) {
				return;
			}

			$image_attributes = wp_get_attachment_image_src( $image_id, 'medium' );
			if ( $image_attributes ) {
				return $image_attributes[0];

			}

			$image_attributes = wp_get_attachment_image_src( $image_id, 'full' );
			return $image_attributes[0];
		}

		/**
		 * Render UAE NPS Survey Notice.
		 *
		 * @since 1.38.0
		 * @return void
		 */
		public static function show_nps_notice() {
			if ( class_exists( 'Nps_Survey' ) ) {
				\Nps_Survey::show_nps_notice(
					'nps-survey-astra-portfolio',
					array(
						'show_if'          => true, // Add your display conditions.
						'dismiss_timespan' => 2 * WEEK_IN_SECONDS,
						'display_after'    => 2 * WEEK_IN_SECONDS,
						'plugin_slug'      => 'astra-portfolio',
						'show_on_screens'  => array( 'edit-astra-portfolio', 'astra-portfolio_page_astra-portfolio-add-new', 'edit-astra-portfolio-categories', 'edit-astra-portfolio-other-categories', 'edit-astra-portfolio-tags', 'astra-portfolio_page_astra-portfolio' ),
						'message'          => array(
							// Step 1 i.e rating input.
							'logo'                  => esc_url( ASTRA_PORTFOLIO_URI . 'assets/images/wp-portfolio-logo.svg'  ),
							'plugin_name'           => __( 'WP Portfolio', 'astra-portfolio' ),
							'nps_rating_message'    => __( 'How likely are you to recommend WP Portfolio to your friends or colleagues?', 'astra-portfolio' ),
							// Step 2A i.e. positive.
							'feedback_title' => __( 'Thanks a lot for your feedback! 😍', 'astra-portfolio' ),
							'feedback_content' => __( 'Thanks for using WP Portfolio! Got feedback or suggestions to make it even better? We’d love to hear from you.', 'astra-portfolio' ),
							'plugin_rating_link'    => esc_url( 'https://www.trustpilot.com/review/wpportfolio.net' ),
							// Step 2B i.e. negative.
							'plugin_rating_title'   => __( 'Thank you for your feedback', 'astra-portfolio' ),
							'plugin_rating_content' => __( 'We value your input. How can we improve your experience?', 'astra-portfolio' ),
						),
					)
				);
			}
		} 


		/**
		 * Meta box templates.
		 *
		 * @since 1.0.1
		 *
		 * @return void
		 */
		public function meta_box_templates() {
			?>

			<script type="text/template" id="tmpl-astra-portfolio-set-media">
				<p><a href="#" class="astra-portfolio-set-media"><?php esc_html_e( 'Add image', 'astra-portfolio' ); ?></a></p>
			</script>
			<script type="text/template" id="tmpl-astra-portfolio-remove-media">
				<# if( data ) { #>
				<p class="hide-if-no-js">
					<img src="{{data}}" class="astra-portfolio-set-media" />
					<a href="#" class="astra-portfolio-remove-media"><i class="dashicons dashicons-no-alt"></i></a>
				</p>
				<# } #>
			</script>
			<?php
		}

		/**
		 * Register meta box(es).
		 *
		 * @since 1.0.0
		 */
		public function meta_box_settings() {
			if ( 'astra-portfolio' !== get_post_type() ) {
				return;
			}
			add_meta_box( 'astra-portfolio', __( 'Portfolio Settings', 'astra-portfolio' ), array( $this, 'meta_boxe_callback' ) );
		}

		/**
		 * Meta box display callback.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Post $post Current post object.
		 * @return void
		 */
		public function meta_boxe_callback( $post ) {

			// Get Blog ID.
			$site_id = get_post_meta( $post->ID, 'astra-blog-id', true );

			$site_url          = get_post_meta( $post->ID, 'astra-site-url', true );
			$call_to_action    = get_post_meta( $post->ID, 'astra-site-call-to-action', true );
			$open_in_new_tab   = get_post_meta( $post->ID, 'astra-site-open-in-new-tab', true );
			$open_portfolio_in = get_post_meta( $post->ID, 'astra-site-open-portfolio-in', true );

			$portfolio_type = get_post_meta( $post->ID, 'astra-portfolio-type', true );
			if ( empty( $portfolio_type ) ) {
				$portfolio_type = Astra_Portfolio_Page::get_instance()->get_default_portfolio_type();
				if ( empty( $portfolio_type ) ) {
					$portfolio_type = 'iframe';
				}
			}

			$thumbnail_id  = get_post_meta( $post->ID, 'astra-lightbox-image-id', true );
			$thumbnail_url = $this->get_portfolio_image_url( $thumbnail_id );

			$portfolio_featured_id  = get_post_meta( $post->ID, 'astra-portfolio-image-id', true );
			$portfolio_featured_url = $this->get_portfolio_image_url( $portfolio_featured_id );

			$portfolio_video_url = get_post_meta( $post->ID, 'astra-portfolio-video-url', true );

			// Set URL.
			$url = get_admin_url( $site_id ) . 'tools.php?page=astra-portfolio';

			wp_nonce_field( 'astra-portfolio-add-template-nonce', 'astra-portfolio-add-template' );
			?>
			<table class="widefat astra-portfolio-table">
				<tr class="astra-portfolio-row">
					<td class="astra-portfolio-heading"><?php esc_html_e( 'Portfolio Type', 'astra-portfolio' ); ?></td>
					<td class="astra-portfolio-content">
						<?php
						echo esc_html( $this->get_portfolio_type_label( $portfolio_type ) );
						?>
					</td>
				</tr>
				<tr class="astra-portfolio-row">
					<td class="astra-portfolio-heading"><?php esc_html_e( 'Thumbnail Image', 'astra-portfolio' ); ?></td>
					<td class="astra-portfolio-content">
						<div class="astra-portfolio-image">
							<?php $this->image_markup( $portfolio_featured_url ); ?>
							<input type="hidden" name="astra-portfolio-image-id" class="image-id" value="<?php echo esc_attr( $portfolio_featured_id ); ?>">
							<input type="hidden" name="astra-portfolio-image-url" class="image-url" value="<?php echo esc_attr( $portfolio_featured_url ); ?>">
						</div>
					</td>
				</tr>
				<?php
				switch ( $portfolio_type ) {

					case 'iframe':
						?>
									<tr class="astra-portfolio-row">
										<td class="astra-portfolio-heading"><?php esc_html_e( 'Enter URL', 'astra-portfolio' ); ?></td>
										<td class="astra-portfolio-content"><input class="astra-input-text" name="astra-site-url" type="text" value="<?php echo esc_attr( $site_url ); ?>" /></td>
									</tr>
									<tr class="astra-portfolio-row">
										<td class="astra-portfolio-heading"><?php esc_html_e( 'Open in New Tab', 'astra-portfolio' ); ?></td>
										<td class="astra-portfolio-content">
											<input type="checkbox" <?php checked( $open_in_new_tab, 1 ); ?> value="1" class="astra-input-text" name="astra-site-open-in-new-tab" />
										</td>
									</tr>
									<tr class="astra-portfolio-row">
										<td class="astra-portfolio-heading"><?php esc_html_e( 'Add Call-to-action', 'astra-portfolio' ); ?></td>
										<td class="astra-portfolio-content">
											<textarea class="astra-input-text" rows="4" name="astra-site-call-to-action"><?php echo wp_kses_post( $call_to_action ); ?></textarea>
											<p class="description"><?php esc_html_e( 'This allows you to add a call-to-action on the preview bar.', 'astra-portfolio' ); ?></p>
										</td>
									</tr>
									<?php
						break;

					case 'image':
						?>
									<tr class="astra-portfolio-row">
										<td class="astra-portfolio-heading"><?php esc_html_e( 'Portfolio Image', 'astra-portfolio' ); ?></td>
										<td class="astra-portfolio-content">
											<div class="astra-portfolio-image">
												<?php $this->image_markup( $thumbnail_url ); ?>
												<input type="hidden" name="astra-lightbox-image-id" class="image-id" value="<?php echo esc_attr( $thumbnail_id ); ?>">
												<input type="hidden" name="astra-lightbox-image-url" class="image-url" value="<?php echo esc_attr( $thumbnail_url ); ?>">
											</div>
										</td>
									</tr>
									<?php
						break;

					case 'video':
						?>
									<tr class="astra-portfolio-row">
										<td class="astra-portfolio-heading"><?php esc_html_e( 'Video URL', 'astra-portfolio' ); ?></td>
										<td class="astra-portfolio-content">
											<div class="astra-portfolio-image">
												<input class="astra-input-text" name="astra-portfolio-video-url" type="text" value="<?php echo esc_attr( $portfolio_video_url ); ?>" />
											</div>
										</td>
									</tr>
									<?php
						break;

					case 'page':
						?>
									<tr class="astra-portfolio-row">
										<td class="astra-portfolio-heading"><?php esc_html_e( 'Open Portfolio Item in', 'astra-portfolio' ); ?></td>
										<td class="astra-portfolio-content">
											<select name="astra-site-open-portfolio-in">
												<option value="new-tab" <?php selected( $open_portfolio_in, 'new-tab' ); ?> /><?php esc_html_e( 'New Tab', 'astra-portfolio' ); ?></option>
												<option value="same-tab" <?php selected( $open_portfolio_in, 'same-tab' ); ?> /><?php esc_html_e( 'Same Tab', 'astra-portfolio' ); ?></option>
												<option value="iframe" <?php selected( $open_portfolio_in, 'iframe' ); ?> /><?php esc_html_e( 'iFrame', 'astra-portfolio' ); ?></option>
											</select>
											<p class="description"><?php esc_html_e( 'Select where you wish to show a portfolio item when the user clicks on it.', 'astra-portfolio' ); ?></p>
										</td>
									</tr>
									<tr class="astra-portfolio-row">
										<td class="astra-portfolio-heading"><?php esc_html_e( 'Add Call-to-action', 'astra-portfolio' ); ?></td>
										<td class="astra-portfolio-content">
											<textarea class="astra-input-text" rows="4" name="astra-site-call-to-action"><?php echo wp_kses_post( $call_to_action ); ?></textarea>
											<p class="description"><?php esc_html_e( 'This allows you to add a call-to-action on the preview bar.', 'astra-portfolio' ); ?></p>
										</td>
									</tr>
									<?php
						break;
				}
				?>
			</table>
			<?php
		}

		/**
		 * Image field markup
		 *
		 * @since 1.0.2
		 *
		 * @param  string $image_url Image URL.
		 * @return void
		 */
		public function image_markup( $image_url = '' ) {
			?>
			<div class="astra-portfolio-image-inner">
				<?php if ( ! empty( $image_url ) ) : ?>
					<p class="hide-if-no-js">
						<img src="<?php echo esc_attr( $image_url ); ?>" class="astra-portfolio-set-media" />
						<a href="#" class="astra-portfolio-remove-media"><i class="dashicons dashicons-no-alt"></i></a>
					</p>
				<?php else : ?>
					<p><a href="#" class="astra-portfolio-set-media"><?php esc_html_e( 'Add image', 'astra-portfolio' ); ?></a></p>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Save meta boxes
		 *
		 * @since 1.0.0
		 *
		 * @param  int    $post_id     Post ID.
		 * @param  object $post     (WP_Post) Post .
		 * @param  bool   $update     Whether this is an existing post being updated or not.
		 * @return void
		 */
		public function save_meta_boxes( $post_id = 0, $post = '', $update = '' ) {

			if ( ! isset( $_POST['astra-portfolio-add-template'] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['astra-portfolio-add-template'], 'astra-portfolio-add-template-nonce' ) ) {
				return;
			}

			if ( isset( $_POST['astra-lightbox-image-id'] ) ) {
				update_post_meta( $post_id, 'astra-lightbox-image-id', absint( $_POST['astra-lightbox-image-id'] ) );
			}

			if ( isset( $_POST['astra-portfolio-image-id'] ) ) {
				update_post_meta( $post_id, 'astra-portfolio-image-id', absint( $_POST['astra-portfolio-image-id'] ) );
			}

			if ( isset( $_POST['astra-portfolio-video-url'] ) ) {
				update_post_meta( $post_id, 'astra-portfolio-video-url', esc_url_raw( $_POST['astra-portfolio-video-url'] ) );
			}

			if ( isset( $_POST['astra-site-url'] ) ) {
				update_post_meta( $post_id, 'astra-site-url', esc_url_raw( urldecode( $_POST['astra-site-url'] ) ) );
			}

			if ( isset( $_POST['astra-site-call-to-action'] ) ) {
				update_post_meta( $post_id, 'astra-site-call-to-action', wp_kses_post( $_POST['astra-site-call-to-action'] ) );
			}

			if ( isset( $_POST['astra-site-open-in-new-tab'] ) ) {
				update_post_meta( $post_id, 'astra-site-open-in-new-tab', absint( $_POST['astra-site-open-in-new-tab'] ) );
			} else {
				update_post_meta( $post_id, 'astra-site-open-in-new-tab', 0 );
			}

			if ( isset( $_POST['astra-site-open-portfolio-in'] ) ) {
				update_post_meta( $post_id, 'astra-site-open-portfolio-in', sanitize_key( $_POST['astra-site-open-portfolio-in'] ) );
			} else {
				update_post_meta( $post_id, 'astra-site-open-portfolio-in', 'new-tab' );
			}

			if ( isset( $_POST['astra-portfolio-type'] ) ) {
				update_post_meta( $post_id, 'astra-portfolio-type', sanitize_key( $_POST['astra-portfolio-type'] ) );
			}
		}

		/**
		 * Register Site Post & Site Taxonomies
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function register_post_and_taxonomies() {

			/**
			 * Register Post Type
			 *
			 * Register "Astra Portfolio" post type.
			 */
			$labels = array(
				'name'               => _x( 'Portfolio', 'post type general name', 'astra-portfolio' ),
				'singular_name'      => _x( 'Portfolio', 'post type singular name', 'astra-portfolio' ),
				'menu_name'          => _x( 'WP Portfolio', 'admin menu', 'astra-portfolio' ),
				'name_admin_bar'     => _x( 'Portfolio', 'add new on admin bar', 'astra-portfolio' ),
				'add_new'            => _x( 'Add New', 'new portfolio item', 'astra-portfolio' ),
				'add_new_item'       => __( 'Add New Portfolio', 'astra-portfolio' ),
				'new_item'           => __( 'New Portfolio', 'astra-portfolio' ),
				'edit_item'          => __( 'Edit Portfolio', 'astra-portfolio' ),
				'view_item'          => __( 'View Portfolio', 'astra-portfolio' ),
				'all_items'          => __( 'All Portfolio Items', 'astra-portfolio' ),
				'search_items'       => __( 'Search Portfolios', 'astra-portfolio' ),
				'parent_item_colon'  => __( 'Parent Portfolios:', 'astra-portfolio' ),
				'not_found'          => __( 'No Portfolios found.', 'astra-portfolio' ),
				'not_found_in_trash' => __( 'No Portfolios found in Trash.', 'astra-portfolio' ),
			);

			$args = apply_filters(
				'astra_portfolio_post_type_args',
				array(
					'labels'                => $labels,
					'description'           => __( 'Description.', 'astra-portfolio' ),
					'public'                => true,
					'publicly_queryable'    => true,
					'show_ui'               => true,
					'show_in_menu'          => true,
					'query_var'             => true,
					'has_archive'           => true,
					'hierarchical'          => false,
					'menu_position'         => null,
					'menu_icon'             => 'dashicons-portfolio',
					'show_in_rest'          => true,
					'rest_base'             => 'astra-portfolio',
					'rest_controller_class' => 'WP_REST_Posts_Controller',
					'supports'              => array( 'title', 'editor', 'thumbnail' ),
				)
			);

			register_post_type( 'astra-portfolio', $args );

			/**
			 * Register Post Meta
			 *
			 * For custom post types, this is 'post', for custom comment types, this is 'comment'.
			 */
			$args = array(
				'type'         => 'string', // Validate and sanitize the meta value as a string.
				'single'       => true, // Return a single value of the type. Default: false.
				'show_in_rest' => true, // Show in the WP REST API response. Default: false.
			);

			register_meta( 'astra-portfolio', 'astra-site-widgets-data', $args );

			/**
			 * Register Taxonomy
			 *
			 * Register "Astra Site Category" taxonomy.
			 */
			$tax_labels = array(
				'name'              => _x( 'Categories', 'taxonomy general name', 'astra-portfolio' ),
				'singular_name'     => _x( 'Categories', 'taxonomy singular name', 'astra-portfolio' ),
				'search_items'      => __( 'Search Categories', 'astra-portfolio' ),
				'all_items'         => __( 'All Categories', 'astra-portfolio' ),
				'parent_item'       => __( 'Parent Categories', 'astra-portfolio' ),
				'parent_item_colon' => __( 'Parent Categories:', 'astra-portfolio' ),
				'edit_item'         => __( 'Edit Categories', 'astra-portfolio' ),
				'update_item'       => __( 'Update Categories', 'astra-portfolio' ),
				'add_new_item'      => __( 'Add New Categories', 'astra-portfolio' ),
				'new_item_name'     => __( 'New Categories Name', 'astra-portfolio' ),
				'menu_name'         => __( 'Categories', 'astra-portfolio' ),
			);

			$tax_args = apply_filters(
				'astra_portfolio_categories_args',
				array(
					'hierarchical'          => true,
					'labels'                => $tax_labels,
					'show_ui'               => true,
					'show_admin_column'     => true,
					'query_var'             => true,
					'show_in_rest'          => true,
					'can_export'            => true,
					'rest_controller_class' => 'WP_REST_Terms_Controller',
				)
			);

			register_taxonomy( 'astra-portfolio-categories', array( 'astra-portfolio' ), $tax_args );

			/**
			 * Register Taxonomy
			 *
			 * @since 1.0.0
			 * Register "Page Builder" taxonomy.
			 */
			$tax_labels = array(
				'name'              => _x( 'Other Categories', 'taxonomy general name', 'astra-portfolio' ),
				'singular_name'     => _x( 'Other Categories', 'taxonomy singular name', 'astra-portfolio' ),
				'search_items'      => __( 'Search Other Categories', 'astra-portfolio' ),
				'all_items'         => __( 'All Other Categories', 'astra-portfolio' ),
				'parent_item'       => __( 'Parent Other Categories', 'astra-portfolio' ),
				'parent_item_colon' => __( 'Parent Other Categories:', 'astra-portfolio' ),
				'edit_item'         => __( 'Edit Other Categories', 'astra-portfolio' ),
				'update_item'       => __( 'Update Other Categories', 'astra-portfolio' ),
				'add_new_item'      => __( 'Add New Other Categories', 'astra-portfolio' ),
				'new_item_name'     => __( 'New Other Categories Name', 'astra-portfolio' ),
				'menu_name'         => __( 'Other Categories', 'astra-portfolio' ),
			);

			$tax_args = array(
				'hierarchical'          => true,
				'labels'                => $tax_labels,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'query_var'             => true,
				'show_in_rest'          => true,
				'can_export'            => true,
				'rest_controller_class' => 'WP_REST_Terms_Controller',
			);

			register_taxonomy( 'astra-portfolio-other-categories', array( 'astra-portfolio' ), $tax_args );

			/**
			 * Register Taxonomy
			 *
			 * @since 1.0.0
			 * Register "Tags" taxonomy.
			 */
			$tax_labels = array(
				'name'              => _x( 'Tags', 'taxonomy general name', 'astra-portfolio' ),
				'singular_name'     => _x( 'Tags', 'taxonomy singular name', 'astra-portfolio' ),
				'search_items'      => __( 'Search Tags', 'astra-portfolio' ),
				'all_items'         => __( 'All Tags', 'astra-portfolio' ),
				'parent_item'       => __( 'Parent Tags', 'astra-portfolio' ),
				'parent_item_colon' => __( 'Parent Tags:', 'astra-portfolio' ),
				'edit_item'         => __( 'Edit Tags', 'astra-portfolio' ),
				'update_item'       => __( 'Update Tags', 'astra-portfolio' ),
				'add_new_item'      => __( 'Add New Tags', 'astra-portfolio' ),
				'new_item_name'     => __( 'New Tags Name', 'astra-portfolio' ),
				'menu_name'         => __( 'Tags', 'astra-portfolio' ),
			);

			$tax_args = array(
				'hierarchical'          => false,
				'labels'                => $tax_labels,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'query_var'             => true,
				'show_in_rest'          => true,
				'can_export'            => true,
				'rest_controller_class' => 'WP_REST_Terms_Controller',
			);

			register_taxonomy( 'astra-portfolio-tags', array( 'astra-portfolio' ), $tax_args );

		}

		/**
		 * Add Terms for Taxonomy.
		 *
		 * => Example.
		 *
		 *  $taxonomy = 'astra-portfolio-categories';
		 *  $terms    = array(
		 *                  array(
		 *                      'name'  => 'Free',
		 *                      'args' => array(
		 *                          'alice_of'    => '',
		 *                          'parent'      => '',
		 *                          'slug'        => 'free-type',
		 *                          'description' => 'Free Post',
		 *                      ),
		 *                  ),
		 *                  array(
		 *                      'name'  => 'Premium',
		 *                  ),
		 *              );
		 *
		 *  $this->add_terms( $taxonomy, $terms );
		 *
		 * @see https://codex.wordpress.org/Function_Reference/wp_insert_term
		 *
		 * @since 1.0.0
		 * @param string $taxonomy Taxonomy Name.
		 * @param array  $terms    Terms list.
		 * @return void
		 */
		public function add_terms( $taxonomy = '', $terms = array() ) {

			$term_id_mapping = (array) get_option( $taxonomy . '-id-mapping', array() );

			foreach ( $terms as $key => $term ) {

				if ( isset( $term['name'] ) ) {
					$response = term_exists( $term['name'], $taxonomy );

					if ( empty( $response ) ) {

						/* translators: %s is term name */
						astra_portfolio_log( sprintf( __( 'Created %s', 'astra-portfolio' ), $term['name'] ), 'debug' );

						/**
						 * Add additional args if passed from request.
						 *
						 * @see https://codex.wordpress.org/Function_Reference/wp_insert_term
						 */
						$args = array();
						if ( array_key_exists( 'args', $term ) ) {
							$args = $term['args'];
						}

						$response = wp_insert_term( $term['name'], $taxonomy, $args );
					}

					if ( ! is_wp_error( $response ) && isset( $response['term_id'] ) && ! empty( $term['meta'] ) ) {
						/* translators: %s is Term name. */
						astra_portfolio_log( sprintf( __( 'Exists %s', 'astra-portfolio' ), $term['name'] ), 'debug' );
						foreach ( $term['meta'] as $meta_key => $meta_value ) {
							update_term_meta( $response['term_id'], $meta_key, $meta_value );
						}

						if ( isset( $term['meta']['old_id'] ) ) {
							$term_id_mapping[ $term['meta']['old_id'] ] = $response['term_id'];
						}
					}
				}
			}

			update_option( $taxonomy . '-id-mapping', $term_id_mapping );
		}

		/**
		 * Portfolio Type
		 *
		 * @since 1.0.2
		 *
		 * @param  string $portfolio_type Portfolio type.
		 * @return string                 Portfolio label.
		 */
		public function get_portfolio_type_label( $portfolio_type = '' ) {
			$portfolio_types = Astra_Portfolio_Page::get_instance()->get_portfolio_types();

			if ( ! empty( $portfolio_types ) ) {
				foreach ( $portfolio_types as $key => $portfolio ) {
					if ( $portfolio_type === $portfolio['key'] ) {
						return $portfolio['label'];
					}
				}
			}

			return '';
		}

		/**
		 * Filter where clause to hide selected posts.
		 *
		 * @since  1.0.2
		 *
		 * @param  String   $where Where clause.
		 * @param  WP_Query $query WP_Query &$this The WP_Query instance (passed by reference).
		 *
		 * @return String $where Where clause.
		 */
		public function where_clause( $where, $query ) {
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return $where;
			}

			// return if exclude.
			$exclude_ids = $this->get_excluded_items();
			if ( empty( $exclude_ids ) ) {
				return $where;
			}

			global $wpdb;
			$exclude_ids    = array_map( 'absint', $exclude_ids );
			$placeholders   = implode( ', ', array_fill( 0, count( $exclude_ids ), '%d' ) );
			$where         .= $wpdb->prepare( " AND {$wpdb->prefix}posts.ID NOT IN ( $placeholders ) ", $exclude_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated dynamically.

			return $where;
		}

		/**
		 * Filter post navigation query to hide the selected posts.
		 *
		 * @since  1.0.2
		 *
		 * @param  String $where Where clause.
		 */
		public function post_navigation_clause( $where ) {
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return $where;
			}

			// return if exclude.
			$exclude_ids = $this->get_excluded_items();
			if ( empty( $exclude_ids ) ) {
				return $where;
			}

			global $wpdb;
			$exclude_ids    = array_map( 'absint', $exclude_ids );
			$placeholders   = implode( ', ', array_fill( 0, count( $exclude_ids ), '%d' ) );
			$where         .= $wpdb->prepare( " AND p.ID NOT IN ( $placeholders ) ", $exclude_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated dynamically.

			return $where;
		}

		/**
		 * Convert the array of posts to comma separated string to make it compatible to wpdb query.
		 *
		 * @since  1.0.2
		 *
		 * @return String Comma separated string of post id's.
		 */
		public function hidden_post_string() {
			return implode( ', ', $this->get_excluded_items() );
		}

		/**
		 * Get Exclude IDs.
		 *
		 * Get all portfolio id's which DONT have meta key `astra-portfolio-type` with meta value `page`.
		 *
		 * @since 1.0.2
		 *
		 * @return array
		 */
		public function get_excluded_items() {
			return $this->generate_excluded_ids();
		}

		/**
		 * Generate Excluded IDs
		 *
		 * @since 1.11.0
		 *
		 * @param  boolean $force Forcefully generate the excluded IDs.
		 * @return array
		 */
		public function generate_excluded_ids( $force = false ) {
			$excluded_ids = (array) get_option( 'astra_portfolio_excludes', array() );

			if ( false === $force ) {
				return $excluded_ids;
			}

			global $wpdb;

			$excluded_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT `post_id` FROM {$wpdb->postmeta} WHERE `meta_key` = %s AND `meta_value` != %s",
					'astra-portfolio-type',
					'page'
				)
			);

			update_option( 'astra_portfolio_excludes', $excluded_ids );

			return $excluded_ids;
		}


		/**
		 * Filters the arguments for registering a portfolio taxonomy.
		 *
		 * @param array  $args        Array of arguments for registering a taxonomy.
		 * @param string $taxonomy    Taxonomy key.
		 * @param array  $object_type Array of names of object types for the taxonomy.
		 *
		 * @return array             Filtered arguments.
		 */
		public function change_portfolio_taxonomy_url_slug( $args, $taxonomy, $object_type ) {

			if ( 'astra-portfolio-tags' === $taxonomy ) {
				$rewrite = Astra_Portfolio_Helper::get_page_setting( 'rewrite-tags', '' );
				if ( ! empty( $rewrite ) ) {
					$args['rewrite']['slug'] = $rewrite;
				}
			}

			if ( 'astra-portfolio-categories' === $taxonomy ) {
				$rewrite = Astra_Portfolio_Helper::get_page_setting( 'rewrite-categories', '' );
				if ( ! empty( $rewrite ) ) {
					$args['rewrite']['slug'] = $rewrite;
				}
			}

			if ( 'astra-portfolio-other-categories' === $taxonomy ) {
				$rewrite = Astra_Portfolio_Helper::get_page_setting( 'rewrite-other-categories', '' );
				if ( ! empty( $rewrite ) ) {
					$args['rewrite']['slug'] = $rewrite;
				}
			}

			return $args;
		}

		/**
		 * Change Portfolio Slug.
		 *
		 * @since 1.4.1 Modify the post type args only for Portfolio post type.
		 *
		 * @since 1.0.5
		 *
		 * @param  array  $args       Post type arguments.
		 * @param  string $post_type Post type slug.
		 * @return array             Filtered arguments.
		 */
		public function change_portfolio_url_slug( $args, $post_type ) {

			if ( 'astra-portfolio' !== $post_type ) {
				return $args;
			}

			$rewrite = Astra_Portfolio_Helper::get_page_setting( 'rewrite', '' );
			if ( ! empty( $rewrite ) ) {
				$args['rewrite']['slug'] = $rewrite;
			}

			// Process only if within the portfolio edit page.
			if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No action is being performed, just checking the edit screen.
				$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No action is being performed, just checking the edit screen.
				if ( $post_id ) {
					$portfolio_type = get_post_meta( $post_id, 'astra-portfolio-type', true );
					// Unset editor support for the portfolio if it is not a 'page' type portfolio.
					if ( 'page' !== $portfolio_type ) {
						unset( $args['supports'][1] );
					}
				}
			}

			return $args;
		}

		/**
		 * Added page builder class
		 *
		 * @param string $classes Admin classes.
		 * @since 1.0.3
		 */
		public function add_portfolio_type_class( $classes = '' ) {
			if ( 'astra-portfolio' !== get_current_screen()->id && 'edit-astra-portfolio' !== get_current_screen()->id ) {
				return $classes;
			}

			$portfolio_type = get_post_meta( get_the_ID(), 'astra-portfolio-type', true );

			return $classes . ' astra-portfolio-type-' . $portfolio_type;
		}

		/**
		 * Check portfolio type excluded from the existing portfolio list.
		 * Now, We exclude all the portfolio types from the WP Query except `page` portfolio type.
		 *
		 * @since 1.3.1
		 * @param  string $portfolio_type Requested portfolio type.
		 * @return boolean                 Is portfolio excluded from the query.
		 */
		public function is_portfolio_type_excluded_from_search( $portfolio_type = '' ) {

			$portfolio_types = Astra_Portfolio_Page::get_instance()->get_portfolio_types();
			$portfolio_slugs = wp_list_pluck( $portfolio_types, 'key' );

			// Remove the `page` type.
			$key = array_search( 'page', $portfolio_slugs, true );
			if ( false !== $key ) {
				unset( $portfolio_slugs[ $key ] );
			}

			if ( in_array( $portfolio_type, $portfolio_slugs, true ) ) {
				return true;
			}

			return false;
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio_Admin::get_instance();

endif;
