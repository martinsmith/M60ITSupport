<?php
/**
 * Single Page Background Process
 *
 * @package Astra Portfolio
 * @since 1.11.0
 */

if ( class_exists( 'WP_Background_Process' ) ) :

	/**
	 * Image Background Process
	 *
	 * @since 1.11.0
	 */
	class Astra_Portfolio_Batch_Importer extends WP_Background_Process {

		/**
		 * Instance
		 *
		 * @since 1.11.0
		 * @access private
		 * @var object Class object.
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @since 1.11.0
		 * @return object initialized object of class.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Image Process
		 *
		 * @var string
		 */
		protected $action = 'astra_portfolio_importer';

		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @since 1.11.0
		 *
		 * @param object $object Queue item object.
		 * @return mixed
		 */
		protected function task( $object ) {

			$process = $object['instance'];
			$method  = $object['method'];

			switch ( $method ) {
				case 'import_categories':
						astra_portfolio_error_log( 'Case: import_categories ' );
						Astra_Portfolio_Page::get_instance()->import_categories();
					break;
				case 'import_other_categories':
						astra_portfolio_error_log( 'Case: import_other_categories ' );
						Astra_Portfolio_Page::get_instance()->import_other_categories();
					break;
				case 'set_requests_count':
						astra_portfolio_error_log( 'Case: set_requests_count ' );
						Astra_Portfolio_Page::get_instance()->set_requests_count();
					break;
				case 'store_all_data':
						astra_portfolio_error_log( 'Case: store_all_data ' );
						Astra_Portfolio_Page::get_instance()->store_all_data();
					break;
				case 'import_all_sites':
						astra_portfolio_error_log( 'Case: import_all_sites ' );
						$all_sites = Astra_Portfolio_Page::get_instance()->get_all_data();

					foreach ( $all_sites as $site_id => $site ) {
						$args = array(
							'site_id'   => $site_id,
							'site_data' => $site,
						);

						Astra_Portfolio_Page::get_instance()->import_site( $args );
					}
					break;
			}

			return false;
		}

		/**
		 * Complete
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 *
		 * @since 1.11.0
		 */
		protected function complete() {
			parent::complete();

			astra_portfolio_batch_message( 'Batch Process Complete.' );
			astra_portfolio_batch_status( 'complete' );

			astra_portfolio_error_log( 'Batch Process Complete' );
		}

	}

endif;
