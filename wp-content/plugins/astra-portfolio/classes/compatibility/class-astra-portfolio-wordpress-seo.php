<?php
/**
 * Compatibility for Yoast SEO
 *
 * @package Astra Portfolio
 * @since 1.8.1
 */

if ( ! class_exists( 'Astra_Portfolio_WordPress_SEO' ) ) :

	/**
	 * Astra Portfolio WordPress SEO
	 *
	 * @since 1.8.1
	 */
	class Astra_Portfolio_WordPress_SEO {

		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
		 * @since 1.8.1
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @since 1.8.1
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
		 * @since 1.8.1
		 */
		public function __construct() {
			add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( $this, 'exclude_from_sitemap' ) );
		}

		/**
		 * Exclude form Sitemap
		 *
		 * @since 1.8.1
		 *
		 * @param  array $ids Excluded post ids.
		 * @return array
		 */
		public function exclude_from_sitemap( $ids = array() ) {
			if ( apply_filters( 'astra_portfolio_exclude_portfolio_items', ! is_admin() ) ) {
				$exclude_ids = (array) Astra_Portfolio_Admin::get_instance()->get_excluded_items();
				return array_merge( $ids, $exclude_ids );
			}

			return $ids;
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio_WordPress_SEO::get_instance();

endif;
