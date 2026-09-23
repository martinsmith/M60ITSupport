<?php
/**
 * Database Background Process
 *
 * @package Astra
 * @since 2.1.3
 */

if ( class_exists( 'WP_Background_Process' ) ) {

	/**
	 * Database Background Process
	 *
	 * @since 2.1.3
	 */
	class WP_Background_Process_Astra_Addon extends WP_Background_Process {
		/**
		 * Database Process
		 *
		 * @var string
		 */
		protected $action = 'addon_database_migration';

		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @since 2.1.3
		 *
		 * @param object $process Queue item object.
		 * @return mixed
		 */
		protected function task( $process ) {

			// Detach astra-settings option filters (e.g. WPML admin texts) so migrations read raw values and never persist translated strings back.
			$detached_option_filters = astra_detach_option_filters();

			do_action( 'astra_addon_batch_process_task-' . $process, $process );

			if ( function_exists( $process ) ) {
				call_user_func( $process );
			}

			if ( 'update_db_version' === $process ) {
				Astra_Addon_Background_Updater::update_db_version();
			}

			astra_restore_option_filters( $detached_option_filters );

			return false;
		}

		/**
		 * Complete
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 *
		 * @since 2.1.3
		 */
		protected function complete() {

			if ( function_exists( 'error_log' ) ) {
			error_log( 'Astra Addon: Batch Process Completed!' );
			}
			do_action( 'astra_addon_database_migration_complete' );

			parent::complete();
		}

	}

}
