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
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://www.leadconnectorhq.com
 * @since      1.0.0
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/admin/partials
 */
function leadconnector_render_plugin_settings_page() {
	$options             = get_option( LEAD_CONNECTOR_OPTION_NAME );
	$enabled_text_widget = 0;
	$api_key             = '';
	$text_widget_error   = '0';
	$error_details       = '';
	$warning_msg         = '';

	if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] ) ) {
		$enabled_text_widget = absint( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] );
	}

	if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] ) ) {
		$api_key = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] );
	}

	if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] ) ) {
		$text_widget_error = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] );
	}

	if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] ) ) {
		$error_details = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] );
	}
	if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_WARNING_TEXT ] ) ) {
		$warning_msg = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_WARNING_TEXT ] );
	}

	$ui_setting = wp_json_encode(
		array(
			'enable_text_widget' => $enabled_text_widget,
			'text_widget_error'  => $text_widget_error,
			'warning_msg'        => $warning_msg,
		)
	);

	$settings_div_allowed = array(
		'div' => array(
			'id'                      => true,
			'data-settings'           => true,
			'data-enable_text_widget' => true,
		),
	);

	?>
	<form action="options.php" method="post">
		<?php
		settings_fields( LEAD_CONNECTOR_OPTION_NAME );
		do_settings_sections( 'lead_connector_plugin' );
		echo wp_kses(
			'<div id="lead-connecter-settings-holder" data-settings="' . esc_attr( $ui_setting ) . '"></div>',
			$settings_div_allowed
		);
		?>
	</form>
	<?php
	echo wp_kses(
		'<div id="app" data-enable_text_widget="' . esc_attr( (string) $enabled_text_widget ) . '"></div>',
		$settings_div_allowed
	);
}

/**
 * Output the text for the first settings section.
 *
 * @return void
 */
function leadconnector_settings_section_text() {
	// No section intro text needed.
}

/**
 * Output the text for the secondary settings section.
 *
 * @return void
 */
function leadconnector_settings_section_text1() {
	echo wp_kses( '', array() );
}
