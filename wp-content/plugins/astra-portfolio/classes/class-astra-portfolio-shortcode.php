<?php
/**
 * Astra Portfolio Shortcode
 *
 * @package Astra Portfolio
 * @since 1.0.0
 */

if ( ! class_exists( 'Astra_Portfolio_Shortcode' ) ) :

	/**
	 * Astra_Portfolio_Shortcode
	 *
	 * @since 1.0.0
	 */
	class Astra_Portfolio_Shortcode {

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
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_shortcode( 'wp_portfolio', array( $this, 'page_templates' ) );
			add_shortcode( 'astra-portfolio', array( $this, 'page_templates' ) );
			add_action( 'astra_portfolio_shortcode_bottom', array( $this, 'shortcode_bottom' ) );
		}

		/**
		 * Enqueue Assets.
		 *
		 * @version 1.0.2   Added lightbox assets.
		 * @version 1.0.0
		 *
		 * @return void
		 */
		public function enqueue_assets() {
			// Lightbox.
			wp_register_script( 'astra-portfolio-lightbox', ASTRA_PORTFOLIO_URI . 'assets/vendor/js/' . Astra_Portfolio::get_instance()->get_assets_js_path( 'magnific-popup' ), array( 'jquery' ), ASTRA_PORTFOLIO_VER, true );
			wp_register_style( 'astra-portfolio-lightbox', ASTRA_PORTFOLIO_URI . 'assets/vendor/css/' . Astra_Portfolio::get_instance()->get_assets_css_path( 'magnific-popup' ), null, ASTRA_PORTFOLIO_VER, 'all' );

			// Lazyload & Image Loaded.
			wp_register_script( 'astra-portfolio-lazyload', ASTRA_PORTFOLIO_URI . 'assets/vendor/js/' . Astra_Portfolio::get_instance()->get_assets_js_path( 'lazy' ), array( 'jquery' ), ASTRA_PORTFOLIO_VER, true );

			// History.
			wp_register_script( 'astra-portfolio-history', ASTRA_PORTFOLIO_URI . 'assets/vendor/js/' . Astra_Portfolio::get_instance()->get_assets_js_path( 'history' ), array( 'jquery' ), ASTRA_PORTFOLIO_VER, true );

			$shortcode_js_dependencies = array(
				'wp-util',
				'astra-portfolio-api',
				'imagesloaded',
				'jquery',
				'astra-portfolio-lazyload',
				'astra-portfolio-lightbox',
				'astra-portfolio-history',
			);

			if ( Astra_Portfolio_Helper::get_page_setting( 'enable-masonry', true ) ) {
				$shortcode_js_dependencies[] = 'jquery-masonry';
			}

			// API.
			wp_register_script( 'astra-portfolio-api', ASTRA_PORTFOLIO_URI . 'assets/js/' . Astra_Portfolio::get_instance()->get_assets_js_path( 'astra-portfolio-api' ), array( 'jquery' ), ASTRA_PORTFOLIO_VER, true );
			wp_register_script( 'astra-portfolio-shortcode', ASTRA_PORTFOLIO_URI . 'assets/js/' . Astra_Portfolio::get_instance()->get_assets_js_path( 'shortcode' ), $shortcode_js_dependencies, ASTRA_PORTFOLIO_VER, true );

			$data = array(
				'ApiURL'  => self::get_api_api(),
				'filters' => array(
					'page_builder' => array(
						'title'   => __( 'Page Builder', 'astra-portfolio' ),
						'slug'    => 'astra-portfolio-other-categories',
						'trigger' => 'astra-api-category-loaded',
					),
					'categories'   => array(
						'title'   => __( 'Categories', 'astra-portfolio' ),
						'slug'    => 'astra-portfolio-categories',
						'trigger' => 'astra-api-category-loaded',
					),
				),
			);
			wp_localize_script( 'astra-portfolio-api', 'astraPortfolioApi', $data );

			// Styles.
			wp_register_style( 'astra-portfolio-shortcode', ASTRA_PORTFOLIO_URI . 'assets/css/' . Astra_Portfolio::get_instance()->get_assets_css_path( 'shortcode' ), array( 'astra-portfolio-lightbox' ), ASTRA_PORTFOLIO_VER, 'all' );
			wp_register_style( 'astra-portfolio-grid', ASTRA_PORTFOLIO_URI . 'assets/css/' . Astra_Portfolio::get_instance()->get_assets_css_path( 'grid' ), null, ASTRA_PORTFOLIO_VER, 'all' );

			$custom_css = '
                .spinner {
                    background-image: url(' . site_url() . '/wp-includes/images/spinner.gif);
                }
            ';

			wp_add_inline_style( 'astra-portfolio-shortcode', $custom_css );

		}

		/**
		 * Get Attributes
		 *
		 * @since 1.7.0
		 *
		 * @return array Shortcode attributes.
		 */
		public function get_attributes() {
			return array(
				'other-categories'        => '',
				'categories'              => '',
				'columns'                 => '',
				'per-page'                => '',
				'show-portfolio-on'       => '',
				'show-search'             => '',
				'show-other-categories'   => '',
				'show-categories'         => '',
				'tags'                    => '',
				'quick-view-text'         => __( 'Quick View', 'astra-portfolio' ),
				'show-quick-view'         => 'yes',
				'other-category-show-all' => 'no',
				'category-show-all'       => 'yes',
				'grid-style'              => '',
				'page-builder'            => '',
				'scroll-speed'            => '',
			);
		}

		/**
		 * Shortcode
		 *
		 * @since 1.0.0
		 * @param  array $data Shortcode attributes.
		 * @return mixed    Shortcode markup.
		 */
		public function page_templates( $data = array() ) {

			$atts = $data;
			$data = shortcode_atts(
				$this->get_attributes(),
				$data
			);

			// If shortcode have empty parameters. Then,
			// Get values from settings page.
			$stored = Astra_Portfolio_Helper::get_page_settings();
			$data   = wp_parse_args( $data, $stored );

			if ( empty( $data['show-search'] ) ) {
				$data['show-search'] = ( isset( $stored['show-search'] ) && $stored['show-search'] ) ? 'yes' : 'no';
			}
			if ( empty( $data['show-other-categories'] ) ) {
				$data['show-other-categories'] = ( isset( $stored['other-categories'] ) && $stored['other-categories'] ) ? 'yes' : 'no';
			}
			if ( empty( $data['show-categories'] ) ) {
				$data['show-categories'] = ( isset( $stored['categories'] ) && $stored['categories'] ) ? 'yes' : 'no';
			}
			if ( empty( $data['columns'] ) ) {
				$data['columns'] = ( isset( $stored['no-of-columns'] ) && $stored['no-of-columns'] ) ? $stored['no-of-columns'] : '3';
			}
			if ( isset( $atts['par-page'] ) && ! empty( $atts['par-page'] ) ) {
				$data['per-page'] = $atts['par-page'];
			} elseif ( empty( $data['per-page'] ) ) {
				$data['per-page'] = ( isset( $stored['per-page'] ) && $stored['per-page'] ) ? $stored['per-page'] : '15';
			}
			if ( empty( $data['show-portfolio-on'] ) ) {
				$data['show-portfolio-on'] = ( isset( $stored['show-portfolio-on'] ) && $stored['show-portfolio-on'] ) ? $stored['show-portfolio-on'] : 'scroll';
			}
			if ( empty( $data['grid-style'] ) ) {
				$data['grid-style'] = ( isset( $stored['grid-style'] ) && $stored['grid-style'] ) ? $stored['grid-style'] : 'style-1';
			}
			if ( empty( $data['scroll-speed'] ) ) {
				$data['scroll-speed'] = ( isset( $stored['scroll-speed'] ) && $stored['scroll-speed'] ) ? $stored['scroll-speed'] : '3';
			}

			// Enqueue assets.
			wp_enqueue_script( 'astra-portfolio-shortcode' );
			wp_enqueue_style( 'astra-portfolio-shortcode' );
			wp_enqueue_style( 'astra-portfolio-grid' );

			$wpml_current_lang = apply_filters( 'wpml_current_language', null );

			$settings = apply_filters(
				'astra_portfolio_shortcode_localize_vars',
				array(
					'scrollToTop'        => true,
					'apiEndpoint'        => self::get_api_api(),
					'apiDomain'          => site_url(),
					'ajaxurl'            => esc_url( admin_url( 'admin-ajax.php' ) ),
					'ApiURLSep'          => ( get_option( 'permalink_structure', false ) ) ? '?' : '&',
					'settings'           => $data,
					'siteLoadingEnabled' => true,
					'siteLoadingTitle'   => __( 'Loading...', 'astra-portfolio' ),
					'siteLoadingMessage' => __( 'One moment please.', 'astra-portfolio' ),
					'current_lang'       => $wpml_current_lang,
				)
			);

			wp_localize_script( 'astra-portfolio-shortcode', 'astraPortfolio', $settings );

			// Add thickbox.
			add_thickbox();

			// Stored Settings.
			$settings = Astra_Portfolio_Helper::get_page_settings();

			$row_class = apply_filters( 'astra_portfolio_row_class', 'astra-portfolio-row' );
			$classes   = apply_filters(
				'astra_portfolio_column_classes',
				array(
					'1' => 'astra-portfolio-col-md-12',
					'2' => 'astra-portfolio-col-md-6',
					'3' => 'astra-portfolio-col-md-4',
					'4' => 'astra-portfolio-col-md-3',
				)
			);

			$column_class = 'astra-portfolio-col-md-3';
			if ( ! empty( $data['columns'] ) && isset( $classes[ $data['columns'] ] ) ) {
				$column_class = $classes[ $data['columns'] ];
			}

			// Load template.
			return astra_portfolio_get_template_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe, as escaping is handled inside template and escaping here breaks the html
				'shortcode.php',
				array(
					'args' => array(
						'data'         => $data,
						'row_class'    => $row_class,
						'column_class' => $column_class,
					),
				)
			);
		}

		/**
		 * Shortcode Bottom
		 *
		 * @since 1.0.6
		 *
		 * @param  array $args Shortcode arguments.
		 * @return void
		 */
		public function shortcode_bottom( $args ) {
			/**
			 * Responsive Buttons
			 */
			?>
			<script type="text/template" id="tmpl-astra-portfolio-responsive-view">
				<?php
				// Load template.
				echo astra_portfolio_get_template_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaping here breaks the html
					'tmpl-responsive-view.php',
					array(
						'args' => $args,
					)
				);
				?>
			</script>

			<?php
			/**
			 * Category & Other Category
			 */
			?>
			<script type="text/template" id="tmpl-astra-portfolio-filters">

				<?php
				// Load template.
				echo astra_portfolio_get_template_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaping here breaks the html
					'tmpl-portfolio-filters.php',
					array(
						'args' => $args,
					)
				);
				?>

			</script>

			<?php
			/**
			 * No items found.
			 */
			?>
			<script type="text/template" id="tmpl-astra-portfolio-no-items-found">

				<?php
				// Load template.
				echo astra_portfolio_get_template_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaping here breaks the html
					'tmpl-no-items-found.php',
					array(
						'args' => $args,
					)
				);
				?>

			</script>

			<?php
			/**
			 * Portfolio List
			 */
			?>
			<script type="text/template" id="tmpl-astra-portfolio-list">

				<?php
				// Load template.
				echo astra_portfolio_get_template_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaping here breaks the html
					'tmpl-portfolio-list.php',
					array(
						'args' => $args,
					)
				);
				?>

			</script>
			<?php
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
	Astra_Portfolio_Shortcode::get_instance();

endif;
