<?php
/**
 * Astra Portfolio
 *
 * @package Astra Portfolio
 * @since 1.0.0
 */

if ( ! class_exists( 'Astra_Portfolio' ) ) :

	/**
	 * Astra_Portfolio
	 *
	 * @since 1.0.0
	 */
	class Astra_Portfolio {

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

			self::includes();
		}

		/**
		 * Include Files
		 *
		 * @since 1.0.0
		 */
		public function includes() {

			require_once ASTRA_PORTFOLIO_DIR . 'classes/functions.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio-update.php';

			require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio-templates.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio-helper.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio-api.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio-shortcode.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio-block.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio-rest-api.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio-page.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/class-astra-portfolio-admin.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/batch-processing/class-astra-portfolio-batch.php';

			// Compatibility.
			require_once ASTRA_PORTFOLIO_DIR . 'classes/compatibility/class-astra-portfolio-wordpress-seo.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/compatibility/class-astra-portfolio-rank-math.php';

			// Load the NPS Survey library.
			if ( ! class_exists( 'Astra_Portflio_Nps_Survey' ) ) {
				require_once ASTRA_PORTFOLIO_DIR . 'lib/class-astra-portfolio-nps-survey.php';
			}
		}

		/**
		 * Get assets js path
		 *
		 * @since 1.0.4
		 *
		 * @param  string $js_file_name JS file name.
		 * @return string               JS minified file path.
		 */
		public static function get_assets_js_path( $js_file_name = '' ) {
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				return $js_file_name . '.js';
			}

			return 'min/' . $js_file_name . '.min.js';
		}

		/**
		 * Get assets css path
		 *
		 * @since 1.0.4
		 *
		 * @param  string $css_file_name CSS file name.
		 * @return string                CSS minified file path.
		 */
		public static function get_assets_css_path( $css_file_name = '' ) {
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				return $css_file_name . '.css';
			}

			return 'min/' . $css_file_name . '.min.css';
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio::get_instance();

endif;
