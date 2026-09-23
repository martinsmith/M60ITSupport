<?php
/**
 * Compatibility for Rank Math
 *
 * @package Astra Portfolio
 * @since 1.9.0
 */

if ( ! class_exists( 'Astra_Portfolio_Rank_Math' ) ) :

	/**
	 * Astra Portfolio WordPress SEO
	 *
	 * @since 1.9.0
	 */
	class Astra_Portfolio_Rank_Math {

		/**
		 * Instance
		 *
		 * @access private
		 * @var object Class object.
		 * @since 1.9.0
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @since 1.9.0
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
		 * @since 1.9.0
		 */
		public function __construct() {
			add_filter( 'rank_math/sitemap/entry', array( $this, 'exclude_from_sitemap' ), 10, 3 );
		}

		/**
		 * Filter URL entry before it gets added to the sitemap.
		 *
		 * @since 1.9.0
		 *
		 * @param string $url  URL.
		 * @param string $post_type URL type.
		 * @param object $post Data object for the URL.
		 */
		public function exclude_from_sitemap( $url = '', $post_type = '', $post = '' ) {
			if ( apply_filters( 'astra_portfolio_exclude_portfolio_items', ! is_admin() ) ) {
				$exclude_ids = (array) Astra_Portfolio_Admin::get_instance()->get_excluded_items();
				if ( ! empty( $exclude_ids ) && is_object( $post ) ) {
					if ( in_array( $post->ID, $exclude_ids, true ) ) {
						return '';
					}
				}
			}

			return $url;
		}
	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio_Rank_Math::get_instance();

endif;
