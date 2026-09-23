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

namespace lead_connector_constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Silence is golden. Loaded only from WordPress; `namespace` must stay first among declarations in this file.


const LEADCONNECTOR_OPTIONS_API_KEY                     = 'api_key';
const LEADCONNECTOR_OPTIONS_LOCATION_ID                 = 'location_id';
const LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR           = 'text_widget_error';
const LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS   = 'text_widget_error_details';
const LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET          = 'enable_text_widget';
const LEADCONNECTOR_OPTIONS_TEXT_WIDGET_HEADING         = 'text_widget_heading';
const LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SUB_HEADING     = 'text_widget_sub_heading';
const LEADCONNECTOR_OPTIONS_TEXT_WIDGET_USE_EMAIL_FILED = 'text_widget_use_email_field';
const LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SETTINGS        = 'chat_widget_settings';
const LEADCONNECTOR_OPTIONS_TEXT_WIDGET_WARNING_TEXT    = 'chat_widget_warnings_text';
const LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL    = 'location_white_label_url';
const LEADCONNECTOR_OPTIONS_OAUTH_AUTHORIZATION_CODE    = 'oauth_authorization_code';
const LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN          = 'oauth_access_token';
const LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN         = 'oauth_refresh_token';
const LEADCONNECTOR_OPTIONS_LAST_CRON_CLEAR_STARTED_AT  = 'last_cron_clear_started_at';

const LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED      = 'leadconnector_email_smtp_enabled';
const LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL        = 'leadconnector_email_smtp_email';
const LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PORT         = 'leadconnector_email_smtp_port';
const LEADCONNECTOR_OPTIONS_EMAIL_SMTP_SERVER       = 'leadconnector_email_smtp_server';
const LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD     = 'leadconnector_email_smtp_password';
const LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PROVIDER     = 'leadconnector_email_smtp_provider';
const LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID = 'selected_chat_widget_id';
const LEADCONNECTOR_OPTIONS_NATIVE_MODE_ALLOWED     = 'leadconnector_native_mode_allowed';




const LEADCONNECTOR_REST_API_ENDPOINT_PARAM        = 'endpoint';
const LEADCONNECTOR_REST_API_DIRECT_ENDPOINT_PARAM = 'direct_endpoint';

const LEADCONNECTOR_REST_API_DATA_PARAM = 'data';

const LEADCONNECTOR_CUSTOM_POST_TYPE = 'leadconn_funnels';

const LEADCONNECTOR_FUNNEL_SLUG_META_KEY     = 'leadconnector_slug';
const LEADCONNECTOR_FUNNEL_SLUG_INDEX_OPTION = 'leadconnector_funnel_slug_index';

const LEADCONNECTOR_BASE_URL                 = 'https://app.leadconnectorhq.com/v2';
const LEADCONNECTOR_FIELD_ID_VALUE_KEY_BASE  = 'leadconnector_field_id_value_';
const LEADCONNECTOR_GET_NEW_VALUES_CACHE_KEY = 'leadconnector_get_new_values_cache_key';

const LEADCONNECTOR_DELETE_DATA_ON_UNINSTALL_OPTION = 'leadconnector_delete_data_on_uninstall';

/**
 * AI Pages edit-contract version (WP post meta).
 * Missing / 1 = legacy highlight targets; >= 2 = Elementor data-id editing.
 * Stamped by ghl-revex-backend on AI page create.
 */
const LEADCONNECTOR_AI_PAGE_VERSION_META_KEY = '_leadconnector_ai_page_version';
/** Current contract version written by backend on new AI pages. */
const LEADCONNECTOR_AI_PAGE_VERSION_CURRENT = 2;
