<?php
/**
 * Brainstorm Updater
 *
 * @package Astra Portfolio
 * @since 1.0.0
 */

// Ignore the PHPCS warning about constant declaration.
// @codingStandardsIgnoreStart
define( 'BSF_REMOVE_astra-portfolio_FROM_REGISTRATION_LISTING', true );
// @codingStandardsIgnoreEnd

if ( ! class_exists( 'Brainstorm_Updater_Astra_Portfolio' ) ) :

	/**
	 * Brainstorm Update
	 */
	class Brainstorm_Updater_Astra_Portfolio {

		/**
		 * Plugin ID
		 *
		 * @var string
		 * @access private
		 */
		private static $plugin_id = 'astra-portfolio';

		/**
		 * Instance
		 *
		 * @since 1.0.0
		 *
		 * @var object Class object.
		 * @access private
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @since 1.0.0
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

			// Load only the latest Graupi.
			$this->version_check();

			register_activation_hook( ASTRA_PORTFOLIO_FILE, array( $this, 'fetch_bundled_products' ) );

			add_action( 'init', array( $this, 'load' ), 999 );
			add_filter( 'bsf_skip_braisntorm_menu', array( $this, 'skip_menu' ) );
			add_filter( 'bsf_skip_author_registration', array( $this, 'skip_menu' ) );
			add_filter( 'bsf_is_product_bundled', array( $this, 'remove_astra_pro_bundled_products' ), 20, 3 );
			add_action( 'plugin_action_links_' . ASTRA_PORTFOLIO_BASE, array( $this, 'license_form_and_links' ) );
			add_action( 'network_admin_plugin_action_links_' . ASTRA_PORTFOLIO_BASE, array( $this, 'license_form_and_links' ) );
			add_filter( 'bsf_registration_page_url_astra-portfolio', array( $this, 'license_form_link' ) );

		}

		/**
		 * Fetch Bundled Products
		 *
		 * @since 1.0.5
		 * @return void
		 */
		public function fetch_bundled_products() {
			update_site_option( 'bsf_force_check_extensions', true );
		}

		/**
		 * Load the brainstorm updater.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function load() {
			global $bsf_core_version, $bsf_core_path;
			if ( is_file( realpath( $bsf_core_path . '/index.php' ) ) ) {
				include_once realpath( $bsf_core_path . '/index.php' );
			}
		}

		/**
		 * Update brainstorm product version and product path.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function version_check() {

			$bsf_core_version_file = ASTRA_PORTFOLIO_DIR . '/admin/bsf-core/version.yml';

			if ( is_file( $bsf_core_version_file ) ) {
				global $bsf_core_version, $bsf_core_path;
				$bsf_core_dir = ASTRA_PORTFOLIO_DIR . '/admin/bsf-core/';
				$version      = file_get_contents( $bsf_core_version_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

				if ( version_compare( $version, (string) $bsf_core_version, '>' ) ) {
					$bsf_core_version = $version;
					$bsf_core_path    = $bsf_core_dir;
				}
			}
		}

		/**
		 * Get an instance of WP_Filesystem_Direct.
		 *
		 * @since 1.9.0
		 * @return object A WP_Filesystem_Direct instance.
		 */
		public function get_filesystem() {
			global $wp_filesystem;

			if ( ! isset( $wp_filesystem ) ) {
				include_once ABSPATH . 'wp-admin/includes/file.php';

				// Check if the function exists before calling it.
				if ( function_exists( 'WP_Filesystem' ) ) {
					WP_Filesystem();
				}

				$wp_filesystem = $GLOBALS['wp_filesystem'];
			}

			return $wp_filesystem;
		}

		/**
		 * Skip Menu.
		 *
		 * @since 1.0.0
		 *
		 * @param array $products products.
		 * @return array $products updated products.
		 */
		public function skip_menu( $products = array() ) {

			$products[] = 'uabb';
			$products[] = 'convertpro';
			$products[] = 'astra-addon';
			$products[] = 'astra-pro-sites';
			$products[] = 'astra-portfolio';

			return $products;
		}

		/**
		 * Remove bundled products for Astra Pro Sites.
		 * For Astra Pro Sites the bundled products are only used for one click plugin installation when importing the Astra Site.
		 * License Validation and product updates are managed separately for all the products.
		 *
		 * @since 1.0.2
		 *
		 * @param  array  $product_parent  Array of parent product ids.
		 * @param  String $bsf_product    Product ID or  Product init or Product name based on $search_by.
		 * @param  String $search_by      Reference to search by id | init | name of the product.
		 *
		 * @return array                 Array of parent product ids.
		 */
		public function remove_astra_pro_bundled_products( $product_parent, $bsf_product, $search_by ) {

			// Bundled plugins are installed when the demo is imported on Ajax request and bundled products should be unchanged in the ajax.
			if ( ! defined( 'DOING_AJAX' ) ) {

				$key = array_search( 'astra-portfolio', $product_parent, true );

				if ( false !== $key ) {
					unset( $product_parent[ $key ] );
				}
			}

			return $product_parent;
		}

		/**
		 * Show action links on the plugin screen.
		 *
		 * @param   mixed $links Plugin Action links.
		 * @return  array        Filtered plugin action links.
		 */
		public function license_form_and_links( $links = array() ) {

			if ( function_exists( 'get_bsf_inline_license_form' ) ) {

				$args = array(
					'product_id'         => self::$plugin_id,
					'popup_license_form' => true,
				);

				return get_bsf_inline_license_form( $links, $args, 'edd' );
			}

			return $links;
		}

		/**
		 * License Form Link
		 *
		 * @since 1.0.0
		 *
		 * @param  string $link License form link.
		 * @return string       Popup License form link.
		 */
		public function license_form_link( $link = '' ) {
			return admin_url( 'plugins.php?bsf-inline-license-form=astra-portfolio' );
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Brainstorm_Updater_Astra_Portfolio::get_instance();

endif; // End if.
