<?php
/**
 * Image Importer
 *
 * => How to use?
 *
 *  $image = array(
 *      'url' => '<image-url>',
 *      'id'  => '<image-id>',
 *  );
 *
 *  $downloaded_image = Astra_Portfolio_Import_Image::get_instance()->import( $image );
 *
 * @package Astra Portfolio
 *
 * @since 1.4.2
 */

if ( ! class_exists( 'Astra_Portfolio_Import_Image' ) ) :

	/**
	 * Image Importer
	 *
	 * @since 1.4.2
	 */
	class Astra_Portfolio_Import_Image {

		/**
		 * Instance
		 *
		 * @since 1.4.2
		 * @var object Class object.
		 * @access private
		 */
		private static $instance;

		/**
		 * Images IDs
		 *
		 * @var array   The Array of already image IDs.
		 * @since 1.4.2
		 */
		private $already_imported_ids = array();

		/**
		 * Initiator
		 *
		 * @since 1.4.2
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
		 * @since 1.4.2
		 */
		public function __construct() {

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// Include image.php.
			require_once ABSPATH . 'wp-admin/includes/image.php';

			WP_Filesystem();
		}

		/**
		 * Process Image Download
		 *
		 * @since 1.4.2
		 * @param  array $attachments Attachment array.
		 * @return array              Attachment array.
		 */
		public function process( $attachments ) {

			$downloaded_images = array();

			foreach ( $attachments as $key => $attachment ) {
				$downloaded_images[] = $this->import( $attachment );
			}

			return $downloaded_images;
		}

		/**
		 * Get Hash Image.
		 *
		 * @since 1.4.2
		 * @param  string $attachment_url Attachment URL.
		 * @return string                 Hash string.
		 */
		private function get_hash_image( $attachment_url ) {
			return sha1( $attachment_url );
		}

		/**
		 * Get Saved Image.
		 *
		 * @since 1.4.2
		 * @param  string $attachment   Attachment Data.
		 * @return string                 Hash string.
		 */
		private function get_saved_image( $attachment ) {

			if ( apply_filters( 'astra_portfolio_image_importer_skip_image', false, $attachment ) ) {
				astra_portfolio_log( 'IMAGE: Avoided though filter \'astra_portfolio_image_importer_skip_image\' ' . $attachment['url'], 'debug' );
				return $attachment;
			}

			global $wpdb;

			// Already imported? Then return!
			if ( isset( $this->already_imported_ids[ $attachment['id'] ] ) ) {
				astra_portfolio_log( 'IMAGE: Already imported! - ' . $attachment['url'], 'debug' );
				return $this->already_imported_ids[ $attachment['id'] ];
			}

			// 1. Is already imported in Batch Import Process?
			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT `post_id` FROM `' . $wpdb->postmeta . '`
						WHERE `meta_key` = \'_astra_portfolio_image_hash\'
							AND `meta_value` = %s
					;',
					$this->get_hash_image( $attachment['url'] )
				)
			);

			// 2. Is image already imported though XML?
			if ( empty( $post_id ) ) {

				// Get file name without extension.
				// To check it exist in attachment.
				$filename = preg_replace( '/\\.[^.\\s]{3,4}$/', '', basename( $attachment['url'] ) );

				$post_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta}
							WHERE meta_key = '_wp_attached_file'
							AND meta_value LIKE %s
						",
						'%' . $filename . '%'
					)
				);
			}

			if ( $post_id ) {
				$new_attachment                                  = array(
					'id'  => $post_id,
					'url' => wp_get_attachment_url( $post_id ),
				);
				$this->already_imported_ids[ $attachment['id'] ] = $new_attachment;

				astra_portfolio_log( 'IMAGE: Downloaded Image URL: ' . $new_attachment['url'], 'debug' );

				return $new_attachment;
			}

			return false;
		}

		/**
		 * Import Image
		 *
		 * @since 1.4.2
		 * @param  array $attachment Attachment array.
		 * @return array              Attachment array.
		 */
		public function import( $attachment ) {

			$saved_image = $this->get_saved_image( $attachment );

			if ( $saved_image ) {
				return $saved_image;
			}

			$response      = wp_safe_remote_get( $attachment['url'], array( 'timeout' => 30 ) );
			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( is_wp_error( $response ) ) {
				/* translators: %1$s is error message and %2$s is image URL */
				astra_portfolio_log( sprintf( __( 'IMAGE: Failed! WP Error - %1$s %2$s', 'astra-portfolio' ), $response->get_error_message(), $attachment['url'] ), 'debug' );
				return $attachment;
			} elseif ( 200 !== $response_code ) {
				/* translators: %1$s is error response code and %2$s is image URL */
				astra_portfolio_log( sprintf( __( 'IMAGE: Failed! Invalid Response Code %1$s! Expected response code 200 - %2$s', 'astra-portfolio' ), $response_code, $attachment['url'] ), 'debug' );
				return $attachment;
			} elseif ( empty( $response_body ) ) {
				astra_portfolio_log( __( 'IMAGE: Failed! Empty image content.', 'astra-portfolio' ), 'debug' );
				return $attachment;
			}

			// Get file name without extension.
			$filename = basename( wp_parse_url( $attachment['url'], PHP_URL_PATH ) );

			$upload = wp_upload_bits(
				$filename,
				null,
				$response_body
			);

			if ( isset( $upload['error'] ) && ! empty( $upload['error'] ) ) {
				/* translators: %1$s is error message and %2$s is image URL */
				astra_portfolio_log( sprintf( __( 'IMAGE: Failed! WP Error - %1$s %2$s', 'astra-portfolio' ), $upload['error'], $attachment['url'] ), 'debug' );
				return $attachment;
			}

			$post = array(
				'post_title' => $filename,
				'guid'       => $upload['url'],
			);

			// Validate MIME type using actual file content, not just extension.
			$filetype_data = wp_check_filetype_and_ext( $upload['file'], $filename );
			$info          = wp_check_filetype( $upload['file'] );

			if ( $info && ! empty( $info['type'] ) ) {
				$post['post_mime_type'] = $info['type'];
			} else {
				// For now just return the origin attachment.
				return $attachment;
			}

			// Reject files where content-type validation fails (e.g., PHP disguised as image).
			if ( ! empty( $filetype_data['ext'] ) && ! empty( $filetype_data['type'] ) ) {
				// Verify it's actually an image MIME type.
				if ( 0 !== strpos( $filetype_data['type'], 'image/' ) ) {
					wp_delete_file( $upload['file'] );
					astra_portfolio_log( sprintf( __( 'IMAGE: Failed! File content does not match an image MIME type - %s', 'astra-portfolio' ), $attachment['url'] ), 'debug' );
					return $attachment;
				}
			}

			$post_id = wp_insert_attachment( $post, $upload['file'] );
			wp_update_attachment_metadata(
				$post_id,
				wp_generate_attachment_metadata( $post_id, $upload['file'] )
			);
			update_post_meta( $post_id, '_astra_portfolio_image_hash', $this->get_hash_image( $attachment['url'] ) );

			$new_attachment = array(
				'id'  => $post_id,
				'url' => $upload['url'],
			);

			$this->already_imported_ids[ $attachment['id'] ] = $new_attachment;

			return $new_attachment;
		}

	}

	/**
	 * Initialize class object with 'get_instance()' method
	 */
	Astra_Portfolio_Import_Image::get_instance();

endif;
