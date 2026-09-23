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
 * Fired during plugin deactivation
 *
 * @link       https://www.leadconnectorhq.com
 * @since      1.0.0
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    LeadConnector
 * @subpackage LeadConnector/includes
 */
class LeadConnector_Deactivator {


	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'leadconnector_twicedaily_refresh_req_v2' );
		wp_clear_scheduled_hook( 'leadconnector_twicedaily_refresh_req' );
		wp_clear_scheduled_hook( 'lc_twicedaily_refresh_req_v2' );
		wp_clear_scheduled_hook( 'lc_twicedaily_refresh_req' );

		// Single-event hooks. A pending occurrence would otherwise stay in the
		// autoloaded `cron` option after deactivation with no handler
		// registered to run it.
		wp_clear_scheduled_hook( 'leadconnector_save_custom_values_event' );
		wp_clear_scheduled_hook( 'leadconnector_sync_ai_page_wp_deleted_event' );
		wp_clear_scheduled_hook( 'leadconnector_sync_ai_page_wp_status_event' );
	}
}
