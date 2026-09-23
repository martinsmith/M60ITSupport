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
 * Fired during plugin activation
 *
 * @link       https://www.leadconnectorhq.com
 * @since      1.0.0
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    LeadConnector
 * @subpackage LeadConnector/includes
 */
class LeadConnector_Activator {

	/**
	 * Initialize plugin tables and data on activation
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Get plugin options.
		$options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$location_id = isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ?
			$options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] : '';

		// Check if this is a new installation or upgrade.
		$installed_version = isset( $options['version'] ) ? $options['version'] : '0.0.0';
		$current_version   = LEAD_CONNECTOR_VERSION;

		// Store activation time for new installations.
		if ( ! isset( $options['activated_time'] ) ) {
			$options['activated_time'] = time();
		}

		// Store the current version.
		$options['version'] = $current_version;
		update_option( LEAD_CONNECTOR_OPTION_NAME, $options );

		// Run version-specific upgrades.
		leadconnector_plugin_update_check();
	}
}
