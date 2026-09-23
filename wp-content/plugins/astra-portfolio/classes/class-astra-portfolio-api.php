<?php
/**
 * Astra Portfolio API
 *
 * @package Astra Portfolio
 * @since 1.0.0
 */

if ( ! class_exists( 'Astra_Portfolio_API' ) ) :

	/**
	 * Astra_Portfolio_API
	 *
	 * @since 1.0.0
	 */
	class Astra_Portfolio_API {

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
		public function __construct() {
		}

		/**
		 * Setter for $api_url
		 *
		 * @since  1.0.0
		 */
		public static function get_api_endpoint() {
			return 'https://websitedemos.net/wp-json/wp/v2/';
		}

		/**
		 * Setter for $api_url
		 *
		 * @since  1.0.0
		 */
		public static function get_sites_api_url() {
			return apply_filters( 'astra_portfolio_api_url', self::get_api_endpoint() . 'astra-sites/' );
		}

		/**
		 * Get Astra portfolios.
		 *
		 * @since 1.0.0
		 *
		 * @param  array $args For selecting the demos (Search terms, pagination etc).
		 * @return array        Astra Portfolio list.
		 */
		public static function get_sites( $args = array() ) {

			$defaults = array(
				'page'     => '1',
				'per_page' => '100',
				'_fields'  => 'id,title,featured_media,astra-site-url,featured-image-url,astra-site-category,astra-site-page-builder',
			);

			$page_builder = Astra_Portfolio_Helper::get_page_setting( 'page-builder' );
			if ( $page_builder ) {
				$args['astra-site-page-builder'] = $page_builder;
			}

			$request_params = apply_filters( 'astra_portfolio_api_params', wp_parse_args( $args, $defaults ) );

			$url = add_query_arg( $request_params, self::get_sites_api_url() );

			$astra_demos = array(
				'sites'        => array(),
				'sites_count'  => 0,
				'api_response' => '',
			);

			$api_args = apply_filters(
				'astra_portfolio_api_args',
				array(
					'timeout' => 30,
				)
			);

			$response                    = wp_remote_get( $url, $api_args );
			$astra_demos['api_response'] = $response;

			if ( ! is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 200 ) {

				$astra_demos['sites_count']     = wp_remote_retrieve_header( $response, 'x-wp-total' );
				$astra_demos['x-wp-total']      = wp_remote_retrieve_header( $response, 'x-wp-total' );
				$astra_demos['x-wp-totalpages'] = wp_remote_retrieve_header( $response, 'x-wp-totalpages' );

				$result = json_decode( wp_remote_retrieve_body( $response ), true );

				// Else skip it.
				if ( is_array( $result ) ) {

					foreach ( $result as $key => $demo ) {

						if ( ! isset( $demo['id'] ) ) {
							continue;
						}

						$astra_demos['sites'][ $key ]['id']                      = isset( $demo['id'] ) ? esc_attr( $demo['id'] ) : '';
						$astra_demos['sites'][ $key ]['slug']                    = isset( $demo['slug'] ) ? esc_attr( $demo['slug'] ) : '';
						$astra_demos['sites'][ $key ]['title']                   = isset( $demo['title']['rendered'] ) ? esc_attr( $demo['title']['rendered'] ) : '';
						$astra_demos['sites'][ $key ]['featured_image_url']      = isset( $demo['featured-image-url'] ) ? esc_url( $demo['featured-image-url'] ) : '';
						$astra_demos['sites'][ $key ]['astra-site-category']     = isset( $demo['astra-site-category'] ) ? (array) $demo['astra-site-category'] : '';
						$astra_demos['sites'][ $key ]['astra-site-page-builder'] = isset( $demo['astra-site-page-builder'] ) ? (array) $demo['astra-site-page-builder'] : '';

						$site_url = '';
						if ( isset( $demo['astra-site-url'] ) ) {
							$site_url = set_url_scheme( '' . esc_url( $demo['astra-site-url'] ), 'https' );
						}
						$astra_demos['sites'][ $key ]['astra_demo_url'] = $site_url;
					}

					// Free up memory by un setting variables that are not required.
					unset( $result );
					unset( $response );
				}
			}

			return $astra_demos;

		}

		/**
		 * Get Astra Portfolio Categories.
		 *
		 * @since 1.8.0 Added $args parameter for passing taxonomy arguments.
		 * @since 1.0.0
		 *
		 * @param array $category_slug For selecting the demos (Search terms, pagination etc).
		 * @param array $args  Arguments.
		 * @since array     Category list.
		 */
		public static function get_categories( $category_slug = '', $args = array() ) {

			if ( empty( $category_slug ) ) {
				return null;
			}

			$defaults = array();

			$request_params = apply_filters( 'astra_portfolio_taxonomy_api_params', wp_parse_args( $args, $defaults ) );

			$url = add_query_arg( $request_params, self::get_api_endpoint() . $category_slug );

			$api_args = apply_filters(
				'astra_portfolio_api_args',
				array(
					'timeout' => 15,
				)
			);

			$response = wp_remote_get( $url, $api_args );

			if ( ! is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 200 ) {
				return json_decode( wp_remote_retrieve_body( $response ), true );
			}

			return $response;

		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio_API::get_instance();

endif;
