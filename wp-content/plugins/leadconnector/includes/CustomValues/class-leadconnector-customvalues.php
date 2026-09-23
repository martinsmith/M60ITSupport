<?php
/**
 *
 * LeadConnector Plugin
 * Copyright (C) 2020-2026 LeadConnector
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package LeadConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages custom-value storage, caching, and remote API look-ups.
 *
 * @package LeadConnector
 */
class LeadConnector_CustomValues {

	/**
	 * WordPress database access object.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Fully-prefixed custom-values table name.
	 *
	 * @var string
	 */
	private $table_name;

	private const LEADCONNECTOR_CUSTOM_VALUES_TRANSIENT_KEY             = 'leadconnector_custom_values';
	private const LEADCONNECTOR_CUSTOM_VALUES_API_ENDPOINT              = 'wordpress/lc-plugin/custom-values/{lcLocationId}/values?query={query}';
	private const LEADCONNECTOR_CUSTOM_VALUE_FROM_FIELD_ID_API_ENDPOINT = 'wordpress/lc-plugin/custom-values/{lcLocationId}/values/{fieldId}';

	/**
	 * Cache key used (in both the persistent object cache and a transient) to
	 * record that the custom-values table has been provisioned so dbDelta does
	 * not run on every page render.
	 */
	private const LEADCONNECTOR_CUSTOM_VALUES_TABLE_EXISTS_KEY = 'leadconnector_custom_values_table_exists';

	/**
	 * Per-request memoization of the table-existence check.
	 *
	 * @var bool|null
	 */
	private static $table_exists_runtime_cache = null;

	/**
	 * Admin class instance used for OAuth remote calls.
	 *
	 * @var LeadConnector_Admin
	 */
	private $admin_class;

	/**
	 * Initialize custom values handler with database and admin dependencies.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb        = $wpdb;
		$this->table_name  = $this->wpdb->prefix . 'leadconnector_custom_values';
		$this->admin_class = new LeadConnector_Admin( LEAD_CONNECTOR_PLUGIN_NAME, LEAD_CONNECTOR_VERSION );
	}

	/**
	 * Retrieve the current location ID from plugin options.
	 *
	 * @return string|null
	 */
	private function get_location_id() {
		$lead_connector_options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		if ( ! isset( $lead_connector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ) {
			return null;
		}

		$location_id = $lead_connector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ];

		if ( ! is_string( $location_id ) || ! preg_match( '/^[a-zA-Z0-9]{1,50}$/', $location_id ) ) {
			return null;
		}

		return $location_id;
	}

	/**
	 * Store custom values in the database and refresh the cache.
	 *
	 * @param array $custom_values_data Array of custom value entries.
	 * @return void
	 */
	public function store_custom_values( $custom_values_data ) {
		if ( is_array( $custom_values_data ) ) {
			$this->save_to_database( $custom_values_data );
			$this->update_cache();
		}
	}

	/**
	 * Insert or update custom value rows in the database.
	 *
	 * @param array $custom_values Array of custom value entries with fieldKey and id.
	 * @return void
	 */
	private function save_to_database( $custom_values ) {
		if ( empty( $custom_values ) ) {
			return;
		}

		if ( ! $this->ensure_table_exists() ) {
			return;
		}

		$cache_updates = array();

		$previous_suppress = $this->wpdb->suppress_errors( true );

		foreach ( $custom_values as $value ) {
			$field_key = isset( $value['fieldKey'] ) ? $value['fieldKey'] : ( isset( $value['field_key'] ) ? $value['field_key'] : null );
			$field_id  = isset( $value['id'] ) ? $value['id'] : null;

			if ( null === $field_key || null === $field_id ) {
				continue;
			}

			$this->wpdb->replace(
				$this->table_name,
				array(
					'field_key' => $field_key,
					'field_id'  => $field_id,
				),
				array( '%s', '%s' )
			);

			$cache_updates[ $field_key ] = $field_id;
		}

		$this->wpdb->suppress_errors( $previous_suppress );

		if ( ! empty( $cache_updates ) ) {
			$this->merge_field_mappings_into_cache( $cache_updates );
		}
	}

	/**
	 * Merge field-key mappings into the transient cache.
	 *
	 * @param array<string, string> $mappings Field key to field ID pairs.
	 * @return void
	 */
	private function merge_field_mappings_into_cache( array $mappings ) {
		$cached_values = get_transient( self::LEADCONNECTOR_CUSTOM_VALUES_TRANSIENT_KEY );
		if ( ! is_array( $cached_values ) ) {
			$cached_values = array();
		}

		foreach ( $mappings as $field_key => $field_id ) {
			$cached_values[ $field_key ] = $field_id;
		}

		set_transient( self::LEADCONNECTOR_CUSTOM_VALUES_TRANSIENT_KEY, $cached_values, 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Refresh the transient cache from the database.
	 *
	 * @return void
	 */
	private function update_cache() {
		if ( ! $this->ensure_table_exists() ) {
			// Cache an empty mapping so we do not retry until the existence
			// check expires; this prevents a missing table from triggering
			// repeated failed queries on every placeholder lookup.
			set_transient( self::LEADCONNECTOR_CUSTOM_VALUES_TRANSIENT_KEY, array(), 12 * HOUR_IN_SECONDS );
			return;
		}

		$previous_suppress = $this->wpdb->suppress_errors( true );
		// C12: Use $wpdb->prepare() with the %i identifier placeholder added in
		// WordPress 6.2 to safely interpolate the table name. esc_sql() is the
		// wrong tool for identifiers (it escapes string literals, not the
		// backtick-quoting required for table/column names) and WPCS flags it.
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare( 'SELECT field_key, field_id FROM %i', $this->table_name ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		$this->wpdb->suppress_errors( $previous_suppress );

		$cached_values = is_array( $results ) ? array_column( $results, 'field_id', 'field_key' ) : array();
		set_transient( self::LEADCONNECTOR_CUSTOM_VALUES_TRANSIENT_KEY, $cached_values, 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Create or upgrade the custom-values table using dbDelta.
	 *
	 * Centralizes the table schema so it can be invoked from the version
	 * upgrade routine and from the lazy auto-recreate path inside
	 * ensure_table_exists().
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'leadconnector_custom_values';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			field_key varchar(255) NOT NULL,
			field_id varchar(100) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY field_key (field_key)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Ensure the custom-values table exists, creating it on demand via dbDelta.
	 *
	 * The table is normally provisioned by the version upgrade routine, but it
	 * may be missing on sites where the database has been restored, the table
	 * was manually dropped, or the upgrade hook never ran (e.g. when the
	 * plugin is being copied between environments). Provisioning the schema
	 * here prevents wpdb from emitting "Table doesn't exist" errors into the
	 * response stream when public pages contain custom-value placeholders.
	 *
	 * Implementation note: we deliberately avoid issuing a direct
	 * `SHOW TABLES LIKE` query for the existence check. dbDelta uses
	 * `CREATE TABLE IF NOT EXISTS` and is the WordPress-canonical schema
	 * management function, which makes it safe to call when the table already
	 * exists. Avoiding a direct $wpdb query here also avoids triggering the
	 * WordPress.DB.DirectDatabaseQuery sniff for what is fundamentally a
	 * one-time-per-cache-window schema operation.
	 *
	 * Result is memoized per-request, in the persistent object cache, and in a
	 * transient so dbDelta does not re-run on every page render.
	 *
	 * @return bool True once the table has been ensured.
	 */
	private function ensure_table_exists() {
		if ( null !== self::$table_exists_runtime_cache ) {
			return self::$table_exists_runtime_cache;
		}

		$cache_key   = self::LEADCONNECTOR_CUSTOM_VALUES_TABLE_EXISTS_KEY;
		$cache_group = 'leadconnector_options';
		$ttl         = 12 * HOUR_IN_SECONDS;

		// Persistent object cache fast path.
		if ( '1' === wp_cache_get( $cache_key, $cache_group ) ) {
			self::$table_exists_runtime_cache = true;
			return true;
		}

		// Cross-request transient fallback for sites without a persistent
		// object cache backend.
		if ( '1' === get_transient( $cache_key ) ) {
			wp_cache_set( $cache_key, '1', $cache_group, $ttl );
			self::$table_exists_runtime_cache = true;
			return true;
		}

		// Cache miss — let dbDelta provision the schema. This is a no-op when
		// the table already exists thanks to CREATE TABLE IF NOT EXISTS.
		self::create_table();

		set_transient( $cache_key, '1', $ttl );
		wp_cache_set( $cache_key, '1', $cache_group, $ttl );

		self::$table_exists_runtime_cache = true;
		return true;
	}

	/**
	 * Look up a field ID from the transient cache.
	 *
	 * @param string $field_key Custom value field key.
	 * @return string|null
	 */
	private function get_field_id_from_cache( $field_key ) {
		$cached_values = get_transient( self::LEADCONNECTOR_CUSTOM_VALUES_TRANSIENT_KEY );
		return ( is_array( $cached_values ) && isset( $cached_values[ $field_key ] ) ) ? $cached_values[ $field_key ] : null;
	}

	/**
	 * Resolve a field ID from cache, refreshing from the database when needed.
	 *
	 * @param string $field_key Custom value field key.
	 * @return string|null
	 */
	private function get_field_id_from_field_key( $field_key ) {
		$field_id = $this->get_field_id_from_cache( $field_key );
		if ( null !== $field_id ) {
			return $field_id;
		}

		$this->update_cache();

		return $this->get_field_id_from_cache( $field_key );
	}

	/**
	 * Fetch custom values from the remote API by field key search.
	 *
	 * @param string $field_key Custom value field key.
	 * @return array|null
	 */
	private function get_custom_values_from_api( $field_key ) {
		$location_id = $this->get_location_id();
		if ( empty( $location_id ) ) {
			return null;
		}

		$endpoint = str_replace(
			array( '{lcLocationId}', '{query}' ),
			array( $location_id, str_replace( '_', '+', $field_key ) ),
			self::LEADCONNECTOR_CUSTOM_VALUES_API_ENDPOINT
		);

		$response = $this->admin_class->leadconnector_oauth_wp_remote_v2( 'get', $endpoint );
		return isset( $response['body']->data ) ? $response['body']->data : null;
	}

	/**
	 * Fetch a single custom value from the remote API by field ID.
	 *
	 * @param string $field_id The custom value field ID.
	 * @return object|null
	 */
	private function get_custom_value_from_field_id_api( $field_id ) {
		$location_id = $this->get_location_id();
		if ( empty( $location_id ) ) {
			return null;
		}

		if ( ! is_string( $field_id ) || ! preg_match( '/^[a-zA-Z0-9_\-]{1,100}$/', $field_id ) ) {
			return null;
		}

		$endpoint = str_replace(
			array( '{lcLocationId}', '{fieldId}' ),
			array( $location_id, $field_id ),
			self::LEADCONNECTOR_CUSTOM_VALUE_FROM_FIELD_ID_API_ENDPOINT
		);

		$response = $this->admin_class->leadconnector_oauth_wp_remote_v2( 'get', $endpoint );
		return isset( $response['body']->customValue ) ? $response['body']->customValue : null;
	}

	/**
	 * Sentinel value stored in transients to represent a confirmed null/missing
	 * custom value. Prevents repeated API lookups for nonexistent keys.
	 */
	private const NEGATIVE_CACHE_SENTINEL = '__lc_null__';

	/**
	 * Get the resolved value for a custom value field key.
	 *
	 * @param string $field_key Custom value field key.
	 * @return string|null
	 */
	public function get_value( $field_key ) {
		if ( ! is_string( $field_key ) || ! preg_match( '/^[a-zA-Z_]\w{0,254}$/', $field_key ) ) {
			return null;
		}

		$field_key_to_value_cache_key = lead_connector_constants\LEADCONNECTOR_FIELD_ID_VALUE_KEY_BASE . $field_key;
		$cached_values                = get_transient( $field_key_to_value_cache_key );
		$get_new_values               = get_transient( lead_connector_constants\LEADCONNECTOR_GET_NEW_VALUES_CACHE_KEY ) ?? false;
		if ( false !== $cached_values && ! $get_new_values ) {
			if ( self::NEGATIVE_CACHE_SENTINEL === $cached_values ) {
				return null;
			}
			return $cached_values;
		}

		$field_id = $this->get_field_id_from_field_key( $field_key );
		if ( empty( $field_id ) ) {
			$custom_values_from_api = $this->get_custom_values_from_api( $field_key );
			if ( isset( $custom_values_from_api ) && is_array( $custom_values_from_api ) ) {
				$values_array = is_object( $custom_values_from_api ) ? (array) $custom_values_from_api : $custom_values_from_api;

				if ( is_array( $values_array ) && isset( $values_array[0] ) && is_object( $values_array[0] ) ) {
					$assoc_array = array();
					foreach ( $values_array as $item ) {
						$item_field_key = leadconnector_api_prop( $item, 'fieldKey' );
						if ( null !== $item_field_key ) {
							$assoc_array[ $item_field_key ] = (array) $item;
							$item_id                        = leadconnector_api_prop( $item, 'id' );
							if ( null !== $item_id && $field_key === $item_field_key ) {
								$field_id = $item_id;
							}
						}
					}
					$this->save_to_database( $assoc_array );
				} else {
					$this->save_to_database( $values_array );
				}
			}
		}

		if ( ! empty( $field_id ) ) {
			$custom_values_from_api = $this->get_custom_value_from_field_id_api( $field_id );
			if ( isset( $custom_values_from_api->value ) && ! empty( $custom_values_from_api->value ) ) {
				set_transient( $field_key_to_value_cache_key, $custom_values_from_api->value, 5 * MINUTE_IN_SECONDS );
				return $custom_values_from_api->value;
			}
		}

		set_transient( $field_key_to_value_cache_key, self::NEGATIVE_CACHE_SENTINEL, MINUTE_IN_SECONDS );
		return null;
	}
	/**
	 * Delete all per-field custom value transients from the database.
	 *
	 * @return void
	 */
	public function remove_all_cached_custom_values_transients() {
		$cached_values = get_transient( self::LEADCONNECTOR_CUSTOM_VALUES_TRANSIENT_KEY );
		if ( is_array( $cached_values ) ) {
			foreach ( array_keys( $cached_values ) as $field_key ) {
				delete_transient( lead_connector_constants\LEADCONNECTOR_FIELD_ID_VALUE_KEY_BASE . $field_key );
				delete_transient( 'lc_field_id_value_' . $field_key );
			}
		}

		delete_transient( self::LEADCONNECTOR_CUSTOM_VALUES_TRANSIENT_KEY );
		delete_transient( 'lc_custom_values' );
	}

	/**
	 * Import legacy custom value mappings from the lc_custom_values transient.
	 *
	 * Legacy installs cached field_key => field_id pairs in that transient after
	 * reading the old lc_custom_values table. Rehydrating from the transient avoids
	 * direct schema queries while preserving existing mappings for the new table.
	 *
	 * @return void
	 */
	public static function migrate_legacy_custom_values_table() {
		if ( get_option( 'leadconnector_legacy_custom_values_migrated', false ) ) {
			return;
		}

		$legacy_cache = get_transient( 'lc_custom_values' );
		if ( ! is_array( $legacy_cache ) || empty( $legacy_cache ) ) {
			update_option( 'leadconnector_legacy_custom_values_migrated', true, false );
			return;
		}

		$payload = array();
		foreach ( $legacy_cache as $field_key => $field_id ) {
			if ( ! is_string( $field_key ) || '' === $field_key ) {
				continue;
			}

			if ( ! is_string( $field_id ) && ! is_numeric( $field_id ) ) {
				continue;
			}

			$payload[] = array(
				'field_key' => $field_key,
				'field_id'  => (string) $field_id,
			);
		}

		if ( ! empty( $payload ) ) {
			$handler = new self();
			$handler->store_custom_values( $payload );
		}

		delete_transient( 'lc_custom_values' );
		wp_cache_delete( 'lc_custom_values', 'transient' );
		update_option( 'leadconnector_legacy_custom_values_migrated', true, false );
	}
}
