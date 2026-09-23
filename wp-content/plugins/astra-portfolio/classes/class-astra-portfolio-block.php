<?php
/**
 * Astra Portfolio Block
 *
 * @package Astra Portfolio
 * @since x.x.x
 */

if ( ! class_exists( 'Astra_Portfolio_Block' ) ) :

	/**
	 * Astra_Portfolio_Shortcode
	 *
	 * @since 1.0.0
	 */
	class Astra_Portfolio_Block {


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
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );
			add_action( 'init', array( $this, 'register_blocks' ) );
		}

		public function register_blocks() {
			register_block_type(
				'astra-portfolio/wp-portfolio',
				array(
					'render_callback' => array( $this, 'html' ),
					'attributes'      => $this->get_attributes(),
				)
			);
		}

		public function html( $attributes ) {
			$default_attributes = $this->get_attributes();
			$attributes         = wp_parse_args( $attributes, $default_attributes );
			ob_start();
			$attributes_json = wp_json_encode( $attributes );

			$data = array();

			$data = shortcode_atts(
				$this->get_attributes(),
				$data
			);

			// $data = wp_json_encode($data);

			$stored = Astra_Portfolio_Helper::get_page_settings();
			$data   = wp_parse_args( $data, $stored );

			add_thickbox();
			?>
			<div class="portfolio-block-wrapper" portfolio-block-attributes='<?php echo esc_attr( $attributes_json ); ?>'></div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Enqueue frontend scripts and styles for the Astra Portfolio block.
		 *
		 * @since 1.0.0
		 */
		public function frontend_enqueue_scripts() {
			wp_enqueue_script( 'astra-portfolio-lightbox', ASTRA_PORTFOLIO_URI . 'assets/vendor/js/' . Astra_Portfolio::get_instance()->get_assets_js_path( 'magnific-popup' ), array( 'jquery' ), ASTRA_PORTFOLIO_VER, true );
			wp_register_style( 'astra-portfolio-lightbox', ASTRA_PORTFOLIO_URI . 'assets/vendor/css/' . Astra_Portfolio::get_instance()->get_assets_css_path( 'magnific-popup' ), null, ASTRA_PORTFOLIO_VER, 'all' );

			$script_dep_path = ASTRA_PORTFOLIO_DIR . 'dist/fscript.asset.php';
			$script_info     = file_exists( $script_dep_path ) ? include_once $script_dep_path : array(
				'dependencies' => array(),
				'version'      => ASTRA_PORTFOLIO_VER,
			);

			$stored = Astra_Portfolio_Helper::get_page_settings();

			$classes = apply_filters(
				'astra_portfolio_column_classes',
				array(
					'1' => 'astra-portfolio-col-md-12',
					'2' => 'astra-portfolio-col-md-6',
					'3' => 'astra-portfolio-col-md-4',
					'4' => 'astra-portfolio-col-md-3',
				)
			);

			$column_class = 'astra-portfolio-col-md-3';
			if ( ! empty( $stored['no-of-columns'] ) && isset( $classes[ $stored['no-of-columns'] ] ) ) {
				$column_class = $classes;
			}

			wp_register_script(
				'portfolio-front-block-script',
				ASTRA_PORTFOLIO_URI . 'dist/fscript.js',
				array_merge(
					$script_info['dependencies'],
					array(
						'wp-element',
						'wp-i18n',
						'wp-dom-ready',
						'astra-portfolio-lightbox',
					)
				),
				$script_info['version'],
				true
			);
			wp_register_style(
				'portfolio-front-style',
				ASTRA_PORTFOLIO_URI . 'dist/fscript.css',
				array( 'dashicons' ),
				ASTRA_PORTFOLIO_VER,
			);
			wp_localize_script(
				'portfolio-front-block-script',
				'columnClass',
				$column_class
			);

			wp_localize_script(
				'portfolio-front-block-script',
				'astraPortfolioData',
				array( 'apiUrl' => get_rest_url() . 'wp/v2/astra-portfolio' )
			);

			wp_enqueue_style( 'portfolio-front-style' );
			wp_enqueue_script( 'portfolio-front-block-script' );
		}

		/**
		 * Enqueue Admin scripts and styles for the Astra Portfolio block.
		 *
		 * @since 1.0.0
		 */
		public function admin_enqueue_scripts( $hook ) {
			/**
			* Add dependencies dynamically.
			*/
			$script_dep_path = ASTRA_PORTFOLIO_DIR . 'dist/script.asset.php';
			$script_info     = file_exists( $script_dep_path ) ? include_once $script_dep_path : array(
				'dependencies' => array(),
				'version'      => ASTRA_PORTFOLIO_VER,
			);

			$stored = Astra_Portfolio_Helper::get_page_settings();

			$classes = apply_filters(
				'astra_portfolio_column_classes',
				array(
					'1' => 'astra-portfolio-col-md-12',
					'2' => 'astra-portfolio-col-md-6',
					'3' => 'astra-portfolio-col-md-4',
					'4' => 'astra-portfolio-col-md-3',
				)
			);

			$column_class = 'astra-portfolio-col-md-3';
			if ( ! empty( $stored['no-of-columns'] ) && isset( $classes[ $stored['no-of-columns'] ] ) ) {
				$column_class = $classes;
			}

			$image_url = ASTRA_PORTFOLIO_URI . 'assets/images/block.png';

			wp_register_script(
				'portfolio-editor-script',
				ASTRA_PORTFOLIO_URI . 'dist/script.js',
				array_merge(
					$script_info['dependencies'],
					array(
						'wp-blocks',
						'wp-element',
						'wp-editor',
						'wp-components',
						'wp-data',
						'wp-i18n',
					)
				),
				$script_info['version'],
				true
			);
			wp_register_style(
				'portfolio-editor-style',
				ASTRA_PORTFOLIO_URI . 'dist/style-script.css',
				array(),
				ASTRA_PORTFOLIO_VER,
			);

			wp_localize_script(
				'portfolio-editor-script',
				'stored',
				$stored
			);

			wp_localize_script(
				'portfolio-editor-script',
				'columnClass',
				$column_class
			);

			wp_localize_script(
				'portfolio-editor-script',
				'astraPortfolioData',
				array(
					'apiUrl'   => get_rest_url() . 'wp/v2/astra-portfolio',
					'imageUrl' => ASTRA_PORTFOLIO_URI, // Add the image URL here]
				)
			);

			// Handle $hook validation and enqueue scripts/styles only if valid.
			if ( isset( $hook ) && ( $hook === 'post.php' || $hook === 'post-new.php' ) ) {
				wp_enqueue_style( 'portfolio-editor-style' );
				wp_enqueue_script( 'portfolio-editor-script' );
			}
		}

		/**
		 * Enqueue Assets.
		 *
		 * @version 1.0.2   Added lightbox assets.
		 * @version 1.0.0
		 *
		 * @return void
		 */

		/**
		 * Get Attributes
		 *
		 * @since 1.7.0
		 *
		 * @return array Shortcode attributes.
		 */
		public function get_attributes() {
			$stored = Astra_Portfolio_Helper::get_page_settings();

			return array(
				'showPortfolioOn'           => array(
					'type'    => 'string',
					'default' => isset( $stored['show-portfolio-on'] ) ? $stored['show-portfolio-on'] : '',
				),
				'previewBar'                => array(
					'type'    => 'string',
					'default' => isset( $stored['preview-bar-loc'] ) ? $stored['preview-bar-loc'] : '',
				),
				'columns'                   => array(
					'type'    => 'string',
					'default' => isset( $stored['no-of-columns'] ) ? $stored['no-of-columns'] : '',
				),
				'titlePosition'             => array(
					'type'    => 'string',
					'default' => isset( $stored['portfolio-title-loc'] ) ? $stored['portfolio-title-loc'] : '',
				),
				'callToAction'              => array(
					'type'    => 'string',
					'default' => isset( $stored['no-more-sites-message'] ) ? $stored['no-more-sites-message'] : '',
				),
				'itemsPerPage'              => array(
					'type'    => 'number',
					'default' => isset( $stored['per-page'] ) ? intval( $stored['per-page'] ) : 10,
				),
				'scrollSpeed'               => array(
					'type'    => 'string',
					'default' => isset( $stored['scroll-speed'] ) ? $stored['scroll-speed'] : '',
				),
				'enableMasonry'             => array(
					'type'    => 'boolean',
					'default' => isset( $stored['enable-masonry'] ) ? filter_var( $stored['enable-masonry'], FILTER_VALIDATE_BOOLEAN ) : false,
				),
				'thumbnailHoverStyle'       => array(
					'type'    => 'string',
					'default' => isset( $stored['grid-style'] ) ? $stored['grid-style'] : '',
				),
				'quick-view-text'           => __( 'Quick View', 'astra-portfolio' ),
				'show-quick-view'           => 'yes',
				'showCategories'            => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showOtherCategories'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'showSearch'                => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'loadMoreBgColor'           => array(
					'type'    => 'string',
					'default' => '#046bd2',
				),
				'loadMoreButtonText'        => array(
					'type'    => 'string',
					'default' => 'Load More',
				),
				'loadMoreHoverBgColor'      => array(
					'type'    => 'string',
					'default' => '#000',
				),
				'loadMoreTextColor'         => array(
					'type'    => 'string',
					'default' => '#fff',
				),
				'loadMoreHoverTextColor'    => array(
					'type'    => 'string',
					'default' => '#fff',
				),
				'loadMoreSize'              => array(
					'type'    => 'number',
					'default' => '16',
				),
				'loadMoreVerticalPadding'   => array(
					'type'    => 'number',
					'default' => '12',
				),
				'loadMoreHorizantalPadding' => array(
					'type'    => 'number',
					'default' => '22',
				),
				'loadMoreBorderRadius'      => array(
					'type'    => 'number',
					'default' => '4',
				),
				'loadMoreBorderWidth'       => array(
					'type'    => 'number',
					'default' => '0',
				),
			);
		}


		/**
		 * Get API URL
		 *
		 * In some case user want to change the Rest API URL. So, We have provided
		 * the filter `astra_portfolio_api_site_uri` to change the Rest API URL.
		 *
		 * @since 1.3.0
		 * @return string   Rest API URL.
		 */
		public static function get_api_api() {
			return apply_filters( 'astra_portfolio_api_site_uri', get_rest_url() . 'wp/v2/' );
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio_Block::get_instance();

endif;
