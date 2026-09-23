<?php
/**
 * Functions
 *
 * @package Astra Portfolio
 * @since 1.11.0
 */

if ( ! function_exists( 'astra_portfolio_doing_cli' ) ) :

	/**
	 * Doing WP CLI
	 *
	 * @since 1.11.0
	 *
	 * @return boolean  Return true if request is performed with WP CLI.
	 */
	function astra_portfolio_doing_cli() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		return false;
	}
endif;

if ( ! function_exists( 'astra_portfolio_log' ) ) :
	/**
	 * Log Messages
	 *
	 * @since 1.11.0
	 *
	 * @param  string  $message Log message.
	 * @param  string  $type    Log type.
	 * @param  boolean $dispaly Display log.
	 * @return mixed
	 */
	function astra_portfolio_log( $message = '', $type = '', $dispaly = true ) {

		// If doing WP_CLI?
		if ( astra_portfolio_doing_cli() ) {

			if ( false === $dispaly ) {
				return;
			}

			$message = wp_json_encode( $message );
			if ( 'debug' === $type ) {
				WP_CLI::debug( $message, 'wp-portfolio' );
			} elseif ( 'error' === $type ) {
				WP_CLI::error( $message );
			} else {
				WP_CLI::line( $message );
			}

			// If doing AJAX?
		} elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( 'debug' === $type ) {
				astra_portfolio_error_log( $message );
			} else {
				if ( 'error' === $type ) {
					wp_send_json_error( $message );
				} else {
					wp_send_json_success( $message );
				}
			}
		} else {
			astra_portfolio_error_log( $message );
		}
	}
endif;

if ( ! function_exists( 'astra_portfolio_error_log' ) ) :
	/**
	 * Error logs
	 *
	 * @since 1.11.0
	 *
	 * @param  string $message Batch status message.
	 * @return void
	 */
	function astra_portfolio_error_log( $message = '' ) {
		error_log( wp_json_encode( $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
endif;

if ( ! function_exists( 'astra_portfolio_batch_message' ) ) :
	/**
	 * Update Batch Status Message
	 *
	 * @since 1.11.0
	 *
	 * @param  string $message Batch status message.
	 * @return void
	 */
	function astra_portfolio_batch_message( $message = '' ) {
		update_option( 'astra-portfolio-batch-process-string', $message );
	}
endif;

if ( ! function_exists( 'astra_portfolio_batch_status' ) ) :
	/**
	 * Update Batch Status
	 *
	 * @since 1.11.0
	 *
	 * @param  string $status Batch status.
	 * @return void
	 */
	function astra_portfolio_batch_status( $status = '' ) {
		update_option( 'astra-portfolio-batch-process', $status );
	}
endif;
