<?php
/**
 * Astra Addon Customizer
 *
 * @package Astra Addon
 * @since 1.6.0
 */

if ( ! class_exists( 'Astra_Addon_Elementor_Compatibility' ) ) {

	/**
	 * Astra Addon Page Builder Compatibility base class
	 *
	 * @since 1.6.0
	 */
	class Astra_Addon_Elementor_Compatibility extends Astra_Addon_Page_Builder_Compatibility {
		/**
		 * Instance
		 *
		 * @since 1.6.0
		 *
		 * @var object Class object.
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @since 1.6.0
		 *
		 * @return object initialized object of class.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Render content for post.
		 *
		 * @param int $post_id Post id.
		 *
		 * @since 1.6.0
		 */
		public function render_content( $post_id ) {

			// set post to glabal post.
			$elementor_instance = Elementor\Plugin::instance();
			echo do_shortcode( $elementor_instance->frontend->get_builder_content_for_display( $post_id ) );
		}

		/**
		 * Load styles and scripts.
		 *
		 * @param int $post_id Post id.
		 *
		 * @since 1.6.0
		 */
		public function enqueue_scripts( $post_id ) {

			if ( '' !== $post_id ) {
				if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
					$css_file = new \Elementor\Core\Files\CSS\Post( $post_id );
				} elseif ( class_exists( '\Elementor\Post_CSS_File' ) ) {
					$css_file = new \Elementor\Post_CSS_File( $post_id );
				}

				$css_file->enqueue();

				// Only needed when Elementor's Atomic Widgets (Editor V4) feature is active.
				if ( class_exists( '\Elementor\Modules\AtomicWidgets\Module' ) && Elementor\Modules\AtomicWidgets\Module::is_active() ) {

					/**
					 * Elementor collects Atomic Widgets styles only for the main queried post,
					 * so register this Custom Layout's post ID as well.
					 *
					 * @since 4.13.8
					 */
					do_action( 'elementor/post/render', $post_id );

					// Elementor hooks its style pipeline on `wp_enqueue_scripts` only when the queried post is built with Elementor.
					// Elsewhere it runs late from `wp_footer`, printing the above styles in the footer. Trigger it here so they land in the head.
					$elementor_frontend = Elementor\Plugin::instance()->frontend;

					if ( method_exists( $elementor_frontend, 'enqueue_styles' ) ) {
						$elementor_frontend->enqueue_styles();
					}
				}
			}
		}

	}

}
