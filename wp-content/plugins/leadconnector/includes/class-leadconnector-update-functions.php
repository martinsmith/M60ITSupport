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

/**
 * Plugin update functions.
 *
 * @package LeadConnector
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Update for version 3.0.11
 *
 * Creates the custom values table.
 */
function leadconnector_update_v_3_0_11() {
	require_once __DIR__ . '/CustomValues/class-leadconnector-customvalues.php';
	LeadConnector_CustomValues::create_table();

	// Reset the cached table-existence flags so any later read in the same
	// request rechecks the database after the schema has been applied.
	delete_transient( 'leadconnector_custom_values_table_exists' );
	wp_cache_delete( 'leadconnector_custom_values_table_exists', 'leadconnector_options' );

	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		global $wpdb;
		LeadConnector_Logger::get_instance()->info(
			sprintf( 'Ensured custom values table exists: %s', $wpdb->prefix . 'leadconnector_custom_values' )
		);
	}
}

/**
 * Update for version 3.0.28
 *
 * Migrates all "lc_" prefixed identifiers to "leadconnector_" to satisfy
 * WordPress.org prefix-length requirements (minimum 4 characters).
 */
function leadconnector_update_v_3_0_28() {
	leadconnector_update_v_3_0_11();

	require_once __DIR__ . '/CustomValues/class-leadconnector-customvalues.php';
	LeadConnector_CustomValues::migrate_legacy_custom_values_table();

	leadconnector_migrate_legacy_funnel_meta_keys();
	leadconnector_migrate_legacy_funnel_post_types();

	// Migrate option sub-keys inside LEAD_CONNECTOR_OPTION_NAME.
	$option_name = defined( 'LEAD_CONNECTOR_OPTION_NAME' ) ? LEAD_CONNECTOR_OPTION_NAME : 'lead_connector_option';
	$options     = get_option( $option_name );
	if ( is_array( $options ) ) {
		$opt_renames = array(
			'lc_email_smtp_enabled'  => 'leadconnector_email_smtp_enabled',
			'lc_email_smtp_email'    => 'leadconnector_email_smtp_email',
			'lc_email_smtp_port'     => 'leadconnector_email_smtp_port',
			'lc_email_smtp_server'   => 'leadconnector_email_smtp_server',
			'lc_email_smtp_password' => 'leadconnector_email_smtp_password',
			'lc_email_smtp_provider' => 'leadconnector_email_smtp_provider',
		);
		$changed     = false;
		foreach ( $opt_renames as $old_k => $new_k ) {
			if ( array_key_exists( $old_k, $options ) && ! array_key_exists( $new_k, $options ) ) {
				$options[ $new_k ] = $options[ $old_k ];
				unset( $options[ $old_k ] );
				$changed = true;
			}
		}
		if ( isset( $options['location_id'] ) && 'lc_disconnect' === $options['location_id'] ) {
			$options['location_id'] = 'leadconnector_disconnect';
			$changed                = true;
		}
		if ( $changed ) {
			update_option( $option_name, $options );
		}
	}

	leadconnector_migrate_legacy_seo_option_keys();

	$old_hook_ts = wp_next_scheduled( 'lc_twicedaily_refresh_req_v2' ); // Deprecated : Will be removed in a future version. Check above for the new cron hook. - Created only for migration purposes.
	if ( $old_hook_ts ) {
		wp_clear_scheduled_hook( 'lc_twicedaily_refresh_req_v2' );
	}
	wp_clear_scheduled_hook( 'lc_twicedaily_refresh_req' ); // Deprecated : Will be removed in a future version. Check above for the new cron hook. - Created only for migration purposes.

	delete_transient( 'lc_custom_values' ); // Deprecated : Will be removed in a future version. Check above for the new transient. - Created only for migration purposes.

	if ( function_exists( 'leadconnector_rebuild_funnel_slug_index' ) ) {
		leadconnector_rebuild_funnel_slug_index();
	}
}

/**
 * Update for version 3.0.30
 *
 * Encrypts any SMTP password stored in plaintext in plugin options. From this
 * version onward all writes go through leadconnector_encrypt_smtp_value(); this
 * migration brings legacy rows up to the same standard.
 */
function leadconnector_update_v_3_0_30() {
	$option_name = defined( 'LEAD_CONNECTOR_OPTION_NAME' ) ? LEAD_CONNECTOR_OPTION_NAME : 'lead_connector_option';
	$options     = get_option( $option_name );
	if ( ! is_array( $options ) ) {
		return;
	}

	$password_key = lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD;
	if ( ! isset( $options[ $password_key ] ) || ! is_string( $options[ $password_key ] ) || '' === $options[ $password_key ] ) {
		return;
	}

	if ( ! function_exists( 'leadconnector_decrypt_smtp_value' ) ) {
		require_once __DIR__ . '/leadconnector-functions.php';
	}

	$stored = $options[ $password_key ];

	// If the value already decrypts cleanly, it has been encrypted before and
	// no migration is required. The decryption helper validates the embedded
	// salt, so plaintext values cannot accidentally decrypt to a real string.
	if ( ! class_exists( 'LeadConnector_Data_Encryption' ) ) {
		require_once dirname( __DIR__ ) . '/admin/class-leadconnector-data-encryption.php';
	}
	$encryption = new LeadConnector_Data_Encryption();
	if ( is_string( $encryption->decrypt( $stored ) ) ) {
		return;
	}

	$options[ $password_key ] = leadconnector_encrypt_smtp_value( $stored );
	update_option( $option_name, $options );
}

/**
 * Check for plugin updates and perform necessary upgrades.
 *
 * This function checks the installed version of the plugin and runs any
 * necessary update functions sequentially.
 *
 * @since 3.0.11
 */
function leadconnector_plugin_update_check() {
	$installed_version = get_option( 'lead_connector_version', '0.0.0' );

	if ( version_compare( $installed_version, LEAD_CONNECTOR_VERSION, '>=' ) ) {
		return;
	}

	/**
	 * This is stored as version number and functions to call
	 */
	$updates = array(
		'3.0.11' => 'leadconnector_update_v_3_0_11',
		'3.0.28' => 'leadconnector_update_v_3_0_28',
		'3.0.30' => 'leadconnector_update_v_3_0_30',
	);

	foreach ( $updates as $version => $function_name ) {
		if ( version_compare( $installed_version, $version, '<' ) ) {
			if ( function_exists( $function_name ) ) {
				call_user_func( $function_name );
			}
		}
	}

	update_option( 'lead_connector_version', LEAD_CONNECTOR_VERSION );
}
add_action( 'plugins_loaded', 'leadconnector_plugin_update_check' );
