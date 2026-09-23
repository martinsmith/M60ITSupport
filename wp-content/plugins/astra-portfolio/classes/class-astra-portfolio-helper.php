<?php
/**
 * Astra_Portfolio_Helper
 *
 * @package Astra Portfolio
 * @since 1.0.0
 */

if ( ! class_exists( 'Astra_Portfolio_Helper' ) ) :

	/**
	 * Astra Portfolio Helper
	 *
	 * @since 1.0.0
	 */
	class Astra_Portfolio_Helper {

		/**
		 * Instance
		 *
		 * @var instance Class Instance.
		 * @access private
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
		}

		/**
		 * Get Page Settings.
		 *
		 * @since 1.0.0
		 * @return array Page settings.
		 */
		public static function get_page_settings() {

			$settings_defaults = array(
				'per-page'                 => '15',
				'show-portfolio-on'        => 'scroll',
				'no-of-columns'            => '3',
				'show-search'              => false,
				'categories'               => true,
				'other-categories'         => false,
				'no-more-sites-message'    => '',
				'preview-bar-loc'          => 'bottom',
				'responsive-button'        => true,
				'rewrite'                  => 'astra-portfolio',
				'rewrite-tags'             => 'astra-portfolio-tags',
				'rewrite-categories'       => 'astra-portfolio-categories',
				'rewrite-other-categories' => 'astra-portfolio-other-categories',
				'grid-style'               => 'style-1',
				'enable-masonry'           => true,
				'page-builder'             => '',
				'portfolio-title-loc'      => 'bottom',
				'scroll-speed'             => '3',
			);

			// Stored Settings.
			$settings = get_option( 'astra-portfolio-settings', $settings_defaults );
			$settings = wp_parse_args( $settings, $settings_defaults );

			return apply_filters( 'astra_portfolio_settings', $settings );

		}

		/**
		 * Get Page Setting.
		 *
		 * @since 1.0.0
		 *
		 * @param  string $key     Option key.
		 * @param  string $default Option default value.
		 * @return mixed Page setting.
		 */
		public static function get_page_setting( $key = '', $default = '' ) {

			$settings = self::get_page_settings();

			if ( array_key_exists( $key, $settings ) ) {
				return $settings[ $key ];
			}

			return $default;

		}

		/**
		 * Generate JSON file.
		 *
		 * @since 1.11.0
		 *
		 * @param  string $file    File name.
		 * @param  array  $content File content.
		 * @return mixed
		 */
		public function generate_json_file( $file = '', $content = array() ) {
			if ( empty( $file ) || empty( $content ) ) {
				return;
			}

			$this->generate_file( ASTRA_PORTFOLIO_DIR . 'assets/json/' . $file . '.json', wp_json_encode( $content ) );
		}

		/**
		 * Generate file
		 *
		 * @since 1.11.0
		 *
		 * @param  string $file    Genearte the file.
		 * @param  string $content File contents.
		 * @return void
		 */
		public function generate_file( $file = '', $content = '' ) {
			if ( empty( $file ) || empty( $content ) ) {
				return;
			}

			$this->get_filesystem()->put_contents( $file, $content );
		}

		/**
		 * Get an instance of WP_Filesystem_Direct.
		 *
		 * @since 1.9.0
		 * @return object A WP_Filesystem_Direct instance.
		 */
		public function get_filesystem() {
			global $wp_filesystem;

			require_once ABSPATH . '/wp-admin/includes/file.php';

			WP_Filesystem();

			return $wp_filesystem;
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio_Helper::get_instance();

endif;
