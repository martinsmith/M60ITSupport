<?php
/**
 * Batch Processing
 *
 * @package Astra Portfolio
 * @since 1.0.0
 */

if ( ! class_exists( 'Astra_Portfolio_Batch' ) ) :

	/**
	 * Astra_Portfolio_Batch
	 *
	 * @since 1.0.0
	 */
	class Astra_Portfolio_Batch {

		/**
		 * Instance
		 *
		 * @since 1.0.0
		 * @var object Class object.
		 * @access private
		 */
		private static $instance;

		/**
		 * Process Posts
		 *
		 * @since 1.4.2
		 * @var object Class object.
		 * @access public
		 */
		public $process_importer;

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

			// Core Helpers - Image Download.
			require_once ASTRA_PORTFOLIO_DIR . 'classes/batch-processing/helpers/class-astra-portfolio-import-image.php';

			// Core Helpers - Batch Processing.
			require_once ASTRA_PORTFOLIO_DIR . 'classes/batch-processing/helpers/class-wp-async-request.php';
			require_once ASTRA_PORTFOLIO_DIR . 'classes/batch-processing/helpers/class-wp-background-process.php';

			// Process.
			require_once ASTRA_PORTFOLIO_DIR . 'classes/batch-processing/class-astra-portfolio-batch-importer.php';

			$this->process_importer = new Astra_Portfolio_Batch_Importer();
			add_action( 'admin_head', array( $this, 'start_process' ) );
		}

		/**
		 * Start Image Import
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function start_process() {

			if ( ! function_exists( 'get_current_screen' ) ) {
				return;
			}

			$screen = get_current_screen();

			$current_screen_id = isset( $screen->base ) ? $screen->base : '';
			if ( 'astra-portfolio_page_astra-portfolio' !== $current_screen_id ) {
				return;
			}

			$status = get_option( 'astra-portfolio-batch-process', false );

			if ( 'complete' === $status ) {
				Astra_Notices::add_notice(
					array(
						'id'               => 'astra-portfolio-batch-process-complete',
						'type'             => 'success',
						'dismissible'      => true,
						'dismissible-meta' => 'transient',
						'show_if'          => true,
						/* translators: %1$s portfolio page url. */
						'message'          => sprintf( __( 'Starter Templates have been imported as portfolio items! Please <a href="%1$s">take a look</a> and publish the ones that you like.', 'astra-portfolio' ), esc_url( admin_url( 'edit.php?post_type=astra-portfolio' ) ) ),
					)
				);

				delete_option( 'astra-portfolio-batch-process' );
				delete_option( 'astra-portfolio-batch-process-string' );

			} elseif ( 'in-process' === $status ) {
				Astra_Notices::add_notice(
					array(
						'id'               => 'astra-portfolio-batch-process-start',
						'type'             => 'info',
						'dismissible'      => true,
						'dismissible-meta' => 'transient',
						'dismissible-time' => WEEK_IN_SECONDS,
						'show_if'          => true,
						'message'          => $this->get_batch_started_message(),
					)
				);
			}
		}

		/**
		 * Get batch started message
		 *
		 * @since 1.11.0
		 *
		 * @return string
		 */
		public function get_batch_started_message() {
			return __( 'Syncing template library in the background. The process can take anywhere between 10 to 15 minutes. We will notify you once done.', 'astra-portfolio' );
		}

		/**
		 * Start Batch
		 *
		 * @since 1.11.0
		 *
		 * @return mixed
		 */
		public function start_batch() {

			if ( 'no' === Astra_Portfolio_Page::get_instance()->get_last_export_checksums() ) {
				astra_portfolio_log( 'Upto-date' );
				return;
			}

			$this->process_batch();

		}

		/**
		 * Process batch
		 *
		 * @since 1.11.0
		 * @return void
		 */
		public function process_batch() {

			astra_portfolio_batch_message( 'Importing..' );
			astra_portfolio_batch_status( 'in-process' );

			// Categories.
			astra_portfolio_error_log( '$this->process_importer->push_to_queue - import_categories' );
			$this->process_importer->push_to_queue(
				array(
					'instance' => Astra_Portfolio_Batch_Importer::get_instance(),
					'method'   => 'import_categories',
				)
			);

			// Other Categories.
			astra_portfolio_error_log( '$this->process_importer->push_to_queue - import_other_categories' );
			$this->process_importer->push_to_queue(
				array(
					'instance' => Astra_Portfolio_Batch_Importer::get_instance(),
					'method'   => 'import_other_categories',
				)
			);

			// Set Import Count.
			astra_portfolio_error_log( '$this->process_importer->push_to_queue - set_requests_count' );
			$this->process_importer->push_to_queue(
				array(
					'instance' => Astra_Portfolio_Batch_Importer::get_instance(),
					'method'   => 'set_requests_count',
				)
			);

			// Store all data.
			astra_portfolio_error_log( '$this->process_importer->push_to_queue - store_all_data' );
			$this->process_importer->push_to_queue(
				array(
					'instance' => Astra_Portfolio_Batch_Importer::get_instance(),
					'method'   => 'store_all_data',
				)
			);

			// Import All Sites.
			astra_portfolio_error_log( '$this->process_importer->push_to_queue - import_all_sites' );
			$this->process_importer->push_to_queue(
				array(
					'instance' => Astra_Portfolio_Batch_Importer::get_instance(),
					'method'   => 'import_all_sites',
				)
			);

			// Dispatch.
			astra_portfolio_error_log( 'Dispatched.' );
			$this->process_importer->save()->dispatch();
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Portfolio_Batch::get_instance();

endif;
