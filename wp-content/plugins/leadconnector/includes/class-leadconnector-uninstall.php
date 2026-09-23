<?php
/**
 * Uninstall cleanup routines for LeadConnector.
 *
 * @package LeadConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes plugin data when the user has opted in to delete data on uninstall.
 */
class LeadConnector_Uninstall {

	/**
	 * Whether the user opted in to remove all plugin data on uninstall.
	 *
	 * Enabled when any of the following is true:
	 * - wp-config.php: define( 'LEADCONNECTOR_DELETE_DATA_ON_UNINSTALL', true );
	 * - Option: leadconnector_delete_data_on_uninstall
	 * - Main plugin options array key: delete_data_on_uninstall
	 *
	 * @return bool
	 */
	public static function should_delete_data() {
		if ( defined( 'LEADCONNECTOR_DELETE_DATA_ON_UNINSTALL' ) && LEADCONNECTOR_DELETE_DATA_ON_UNINSTALL ) {
			return true;
		}

		if ( get_option( lead_connector_constants\LEADCONNECTOR_DELETE_DATA_ON_UNINSTALL_OPTION, false ) ) {
			return true;
		}

		if ( ! defined( 'LEAD_CONNECTOR_OPTION_NAME' ) ) {
			return false;
		}

		$options = get_option( LEAD_CONNECTOR_OPTION_NAME, array() );
		return is_array( $options ) && ! empty( $options['delete_data_on_uninstall'] );
	}

	/**
	 * Run uninstall cleanup.
	 *
	 * @return void
	 */
	public static function run() {
		self::clear_cron_events();

		if ( ! self::should_delete_data() ) {
			return;
		}

		self::delete_funnel_posts();
		self::delete_plugin_options();
		self::delete_seo_options();
		self::delete_custom_values_table();
		self::delete_transients();
		self::delete_log_files();
	}

	/**
	 * Clear scheduled cron hooks registered by the plugin.
	 *
	 * @return void
	 */
	private static function clear_cron_events() {
		$hooks = array(
			'leadconnector_twicedaily_refresh_req_v2',
			'leadconnector_twicedaily_refresh_req',
			'lc_twicedaily_refresh_req_v2',
			'lc_twicedaily_refresh_req',
			// Single-event hooks. These were previously never cleared, so any
			// pending occurrence outlived the plugin in the autoloaded `cron`
			// option — together with its serialized args, which for
			// leadconnector_save_custom_values_event can be the whole
			// custom-values payload.
			'leadconnector_save_custom_values_event',
			'leadconnector_sync_ai_page_wp_deleted_event',
			'leadconnector_sync_ai_page_wp_status_event',
		);

		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Delete funnel custom post type posts (current and legacy types).
	 *
	 * @return void
	 */
	private static function delete_funnel_posts() {
		$post_types = array(
			'leadconn_funnels',
			'lc_funnels',
			'leadconnector_funnels',
		);

		foreach ( $post_types as $post_type ) {
			$post_ids = get_posts(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'any',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( ! is_array( $post_ids ) ) {
				continue;
			}

			foreach ( $post_ids as $post_id ) {
				wp_delete_post( (int) $post_id, true );
			}
		}
	}

	/**
	 * Delete plugin options stored in the options table.
	 *
	 * @return void
	 */
	private static function delete_plugin_options() {
		$option_names = array(
			lead_connector_constants\LEADCONNECTOR_DELETE_DATA_ON_UNINSTALL_OPTION,
			'leadconnector_legacy_custom_values_migrated',
			'lead_connector_version',
			'lead_connector_plugin_options',
			'lead_connector_option',
		);

		if ( defined( 'LEAD_CONNECTOR_OPTION_NAME' ) ) {
			$option_names[] = LEAD_CONNECTOR_OPTION_NAME;
		}

		$option_names[] = 'leadconnector_funnel_slug_index';

		foreach ( array_unique( $option_names ) as $option_name ) {
			delete_option( $option_name );
		}

		wp_cache_delete( 'slug_index', 'leadconnector_funnels' );
	}

	/**
	 * Delete per-path SEO override options (current and legacy prefixes).
	 *
	 * @return void
	 */
	private static function delete_seo_options() {
		global $wpdb;

		$prefixes = array(
			'leadconnector_seo_overrides_by_path-%',
			'lc_seo_overrides_by_path-%',
		);

		foreach ( $prefixes as $like_pattern ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$option_names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
					$like_pattern
				)
			);

			if ( ! is_array( $option_names ) ) {
				continue;
			}

			foreach ( $option_names as $option_name ) {
				delete_option( $option_name );
			}
		}
	}

	/**
	 * Drop the custom values database table.
	 *
	 * Uses the %i identifier placeholder (introduced in WordPress 6.2 — the
	 * plugin's declared minimum) so the DDL is prepared via the canonical
	 * $wpdb->prepare() pipeline and satisfies the
	 * WordPress.DB.PreparedSQL.InterpolatedNotPrepared and
	 * PluginCheck.Security.DirectDB.UnescapedDBParameter sniffs. Core's
	 * prepare() applies identifier-aware escaping for %i (backtick-quoting
	 * plus disallowed-character validation), which is stricter and safer
	 * than esc_sql() with manual backticks.
	 *
	 * @return void
	 */
	private static function delete_custom_values_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'leadconnector_custom_values';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
	}

	/**
	 * Delete plugin transients from the options table.
	 *
	 * @return void
	 */
	private static function delete_transients() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_leadconnector_%'
				OR option_name LIKE '_transient_timeout_leadconnector_%'
				OR option_name LIKE '_transient_lc_%'
				OR option_name LIKE '_transient_timeout_lc_%'"
		);
	}

	/**
	 * Remove log files under every directory the logger may have written to.
	 *
	 * The canonical home is `WP_CONTENT_DIR/leadconnector-logs/` (set in
	 * 3.0.32 — see #H9). For backwards compatibility we also sweep the
	 * legacy `wp-content/uploads/leadconnector-logs/` location and an
	 * admin-provided `LEADCONNECTOR_LOG_DIR` override.
	 *
	 * @return void
	 */
	private static function delete_log_files() {
		$candidates = array();

		if ( defined( 'LEADCONNECTOR_LOG_DIR' ) && is_string( LEADCONNECTOR_LOG_DIR ) && '' !== LEADCONNECTOR_LOG_DIR ) {
			$candidates[] = rtrim( LEADCONNECTOR_LOG_DIR, '/\\' );
		}

		if ( defined( 'WP_CONTENT_DIR' ) && is_string( WP_CONTENT_DIR ) && '' !== WP_CONTENT_DIR ) {
			$candidates[] = rtrim( WP_CONTENT_DIR, '/\\' ) . '/leadconnector-logs';
		}

		$upload_dir = wp_upload_dir( null, false );
		if ( is_array( $upload_dir ) && empty( $upload_dir['error'] ) && ! empty( $upload_dir['basedir'] ) ) {
			$candidates[] = rtrim( (string) $upload_dir['basedir'], '/\\' ) . '/leadconnector-logs';
		}

		foreach ( array_unique( $candidates ) as $log_dir ) {
			self::delete_log_dir_recursive( $log_dir );
		}
	}

	/**
	 * Recursively delete one log directory using WP_Filesystem when
	 * available and a SPL iterator fallback otherwise.
	 *
	 * @param string $log_dir Absolute directory path.
	 * @return void
	 */
	private static function delete_log_dir_recursive( $log_dir ) {
		if ( ! is_string( $log_dir ) || '' === $log_dir || ! is_dir( $log_dir ) ) {
			return;
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem ) {
			$wp_filesystem->delete( $log_dir, true );
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $log_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file_info ) {
			if ( $file_info->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				rmdir( $file_info->getRealPath() );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file_info->getRealPath() );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $log_dir );
	}
}
