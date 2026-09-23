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
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.leadconnectorhq.com
 * @since      1.0.0
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/admin
 */
class LeadConnector_Admin {


	/**
	 * Ensures script_loader_tag filter for the Vue bundle is registered only once.
	 *
	 * @var bool
	 */
	private static $leadconnector_vue_module_script_filter_added = false;

	/**
	 * Tracks whether register_hooks() has already wired the admin-side WordPress
	 * hooks for this PHP request. The `LeadConnector_Admin` class is constructed
	 * from several non-canonical call sites (cron callback, CustomValues OAuth
	 * helper, native template handler). Only the canonical bootstrap path
	 * (`LeadConnector::define_admin_hooks()`) should attach the admin-notice,
	 * admin-head and dashicons hooks; this flag guarantees that even a
	 * misbehaving caller can call `register_hooks()` on a fresh instance
	 * without double-binding the callbacks.
	 *
	 * @var bool
	 */
	private static $leadconnector_admin_hooks_registered = false;

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * The constructor is intentionally side-effect-free with respect to
	 * WordPress hook registration. Hooks are wired by `register_hooks()`,
	 * which is invoked exactly once from the canonical bootstrap path
	 * (`LeadConnector::define_admin_hooks()`). Secondary call sites that
	 * instantiate `LeadConnector_Admin` purely to reuse helper methods
	 * (`LeadConnector_CustomValues`, the cron OAuth refresh closure, the
	 * native template handler) therefore no longer accidentally re-register
	 * the admin-notice / admin-head / dashicons callbacks N times per
	 * request. See C1 in the 3.0.32 WP.org review report.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-leadconnector-constants.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/partials/class-leadconnector-admin-display.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-leadconnector-menu-handler.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/Logger/class-leadconnector-logger.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/CustomValues/class-leadconnector-customvalues.php';

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Vue admin bundle is now IIFE format; type="module" filter is no longer needed.
	}

	/**
	 * Register WordPress hooks owned by `LeadConnector_Admin`.
	 *
	 * Must be called exactly once per request, from the canonical bootstrap
	 * (`LeadConnector::define_admin_hooks()`). Repeated calls are a no-op:
	 * the static `$leadconnector_admin_hooks_registered` flag short-circuits
	 * subsequent invocations so that even if a non-canonical caller (for
	 * example a future cron callback) forwards through here on a different
	 * instance, each `add_action()` runs at most once for the lifetime of
	 * the PHP request.
	 *
	 * @since 3.0.32
	 * @return void
	 */
	public function register_hooks() {
		if ( self::$leadconnector_admin_hooks_registered ) {
			return;
		}
		self::$leadconnector_admin_hooks_registered = true;

		// Replace admin bar notification with admin notice banner.
		add_action( 'admin_notices', array( $this, 'display_payment_failed_banner' ) );
		add_action( 'admin_notices', array( $this, 'display_native_mode_trust_notice' ) );

		add_action( 'admin_head', array( $this, 'add_payment_notification_styles' ) );

		// Ensure dashicons are loaded in frontend for non-admin users.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_dashicons' ) );

		// WP-Cron handler for the deferred custom-values DB write. Registered
		// from the canonical bootstrap so the listener is present in every
		// request context (admin, front-end, REST, and the `wp-cron.php`
		// loopback that actually fires the event). Replaces the prior
		// loopback `wp_remote_post()` pattern that copied WordPress auth
		// cookies into a plugin-generated HTTP request.
		add_action( 'leadconnector_save_custom_values_event', array( $this, 'leadconnector_handle_save_custom_values_event' ), 10, 1 );
		add_action( 'leadconnector_sync_ai_page_wp_deleted_event', array( $this, 'leadconnector_handle_sync_ai_page_wp_deleted_event' ), 10, 1 );
		add_action( 'leadconnector_sync_ai_page_wp_status_event', array( $this, 'leadconnector_handle_sync_ai_page_wp_status_event' ), 10, 2 );
	}



	/**
	 * Display a payment failed banner at the top of all admin pages
	 *
	 * @since    1.0.0
	 */
	public function display_payment_failed_banner() {
		// Only display the banner if LEADCONNECTOR_PAYMENT_FAILED is defined and true.
		if ( ! defined( 'LEADCONNECTOR_PAYMENT_FAILED' ) || LEADCONNECTOR_PAYMENT_FAILED !== true ) {
			return;
		}

		// Get domain and location ID from constants, or use defaults.
		$company_domain = defined( 'LEADCONNECTOR_COMPANY_DOMAIN' ) ? LEADCONNECTOR_COMPANY_DOMAIN : 'app.leadconnectorhq.com';
		$location_id    = defined( 'LEADCONNECTOR_LOCATION_ID' ) ? LEADCONNECTOR_LOCATION_ID : '';

		// Build the payment URL.
		$payment_url = $company_domain . '/v2/location/' . $location_id . '/wordpress/dashboard';

		// Add https:// if not included in the domain.
		if ( strpos( $payment_url, 'http' ) !== 0 ) {
			$payment_url = 'https://' . $payment_url;
		}

		$banner_allowed = array(
			'div'    => array( 'class' => true ),
			'img'    => array(
				'src' => true,
				'alt' => true,
			),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'class'  => true,
			),
			'button' => array(
				'type'  => true,
				'class' => true,
			),
			'span'   => array( 'class' => true ),
		);
		$banner_html    = sprintf(
			'<div class="leadconnector-payment-notice-wrapper">'
			. '<div class="leadconnector-payment-notice">'
			. '<div class="leadconnector-payment-notice-icon">'
			. '<img src="%1$s" alt="%2$s" />'
			. '</div>'
			. '<div class="leadconnector-payment-notice-message">%3$s</div>'
			. '<div class="leadconnector-payment-notice-actions">'
			. '<a href="%4$s" target="_blank" class="leadconnector-update-button">%5$s</a>'
			. '<button type="button" class="leadconnector-dismiss-button">'
			. '<span class="dashicons dashicons-no-alt"></span>'
			. '</button>'
			. '</div>'
			. '</div>'
			. '</div>',
			esc_url( plugin_dir_url( __FILE__ ) . 'images/leadconnector-warning-icon.svg' ),
			esc_attr__( 'Warning', 'leadconnector' ),
			esc_html__( 'Your recent payment has failed. Please update your billing information.', 'leadconnector' ),
			esc_url( $payment_url ),
			esc_html__( 'Update Payment Information', 'leadconnector' )
		);
		echo wp_kses( $banner_html, $banner_allowed );
	}

	/**
	 * Ensure dashicons are loaded for non-admin users
	 */
	/**
	 * Force type="module" for the Vue app script (required below WordPress 6.5).
	 *
	 * @param string $tag    Full script tag HTML.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function filter_vue_leadconnector_module_script_tag( $tag, $handle ) {
		if ( 'leadconnector-vue-js' !== $handle ) {
			return $tag;
		}
		if ( false !== strpos( $tag, 'type="module"' ) || false !== strpos( $tag, "type='module'" ) ) {
			return $tag;
		}
		return str_replace( '<script ', '<script type="module" ', $tag );
	}

	/**
	 * Enqueue dashicons for logged-in admin users on the frontend.
	 *
	 * @return void
	 */
	public function enqueue_dashicons() {
		// C16: dashicons is only used by the front-end LeadConnector payment-failed
		// notice and the LeadConnector native funnel template. Restrict the
		// enqueue to LeadConnector-owned screens (a funnel CPT singular view, a
		// post that carries a leadconnector_step_url meta, or a request the
		// LEADCONNECTOR_PAYMENT_FAILED constant has flagged) and an admin-level
		// user. The previous unconditional enqueue added ~30 KB of CSS to every
		// front-end render whenever a logged-in admin browsed the public site.
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$leadconnector_should_load = false;

		if ( defined( 'LEADCONNECTOR_PAYMENT_FAILED' ) && true === LEADCONNECTOR_PAYMENT_FAILED ) {
			$leadconnector_should_load = true;
		}

		if ( ! $leadconnector_should_load && is_singular( lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE ) ) {
			$leadconnector_should_load = true;
		}

		if ( ! $leadconnector_should_load && is_singular() ) {
			$leadconnector_post_id = get_the_ID();
			if ( $leadconnector_post_id && '' !== (string) get_post_meta( $leadconnector_post_id, 'leadconnector_step_url', true ) ) {
				$leadconnector_should_load = true;
			}
		}

		if ( ! $leadconnector_should_load ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
	}

	/**
	 * Add styles for the payment notification
	 *
	 * @since    1.0.0
	 */
	public function add_payment_notification_styles() {
		// Only enqueue assets if the payment-failed banner will actually appear.
		if ( ! defined( 'LEADCONNECTOR_PAYMENT_FAILED' ) || LEADCONNECTOR_PAYMENT_FAILED !== true ) {
			return;
		}

		wp_enqueue_style(
			'leadconnector-payment-notice',
			plugin_dir_url( __FILE__ ) . 'css/leadconnector-payment-notice.css',
			array( 'dashicons' ),
			$this->version
		);

		wp_enqueue_script(
			'leadconnector-payment-notice',
			plugin_dir_url( __FILE__ ) . 'js/leadconnector-payment-notice.js',
			array(),
			$this->version,
			true
		);
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * Invoked by WordPress when the plugin's options form is submitted via
	 * options.php. The method strictly accepts a single argument so it matches
	 * the sanitize_callback contract; internal callers that need to force a
	 * chat-widget refresh use sanitize_settings_with_widget_refresh() instead.
	 *
	 * @since 1.0.0
	 * @param array $input Raw option values submitted by the settings form.
	 * @return array Sanitized option values.
	 */
	public function leadconnector_setting_validate( $input ) {
		return $this->sanitize_settings_with_widget_refresh( $input, false );
	}

	/**
	 * Sanitize plugin option values, optionally forcing a chat-widget refresh.
	 *
	 * Shared implementation for both the WordPress sanitize_callback and the
	 * internal REST/admin-side save paths. The $force_widget_refresh flag
	 * triggers a remote chat-widget lookup even when the input does not toggle
	 * the widget on, so internal callers that already know the user wants a
	 * fresh widget snapshot can request one explicitly.
	 *
	 * @since 1.0.0
	 * @param array $input                Raw option values to sanitize.
	 * @param bool  $force_widget_refresh When true, fetch the remote chat
	 *                                    widget regardless of the enable flag.
	 * @return array Sanitized option values.
	 */
	private function sanitize_settings_with_widget_refresh( $input, $force_widget_refresh = false ) {
		if ( is_array( $input ) ) {
			$allowed_keys = array(
				lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_HEADING,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SUB_HEADING,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_USE_EMAIL_FILED,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SETTINGS,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_WARNING_TEXT,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_AUTHORIZATION_CODE,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_LAST_CRON_CLEAR_STARTED_AT,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PORT,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_SERVER,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PROVIDER,
				lead_connector_constants\LEADCONNECTOR_OPTIONS_NATIVE_MODE_ALLOWED,
			);
			$input        = array_intersect_key( $input, array_flip( $allowed_keys ) );

			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] = absint(
					$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ]
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL ] = esc_url_raw(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_HEADING ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_HEADING ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_HEADING ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SUB_HEADING ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SUB_HEADING ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SUB_HEADING ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_USE_EMAIL_FILED ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_USE_EMAIL_FILED ] = absint(
					$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_USE_EMAIL_FILED ]
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SETTINGS ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SETTINGS ] = $this->leadconnector_sanitize_text_widget_settings_option(
					$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SETTINGS ]
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] = absint(
					$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ]
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_AUTHORIZATION_CODE ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_AUTHORIZATION_CODE ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_AUTHORIZATION_CODE ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LAST_CRON_CLEAR_STARTED_AT ] ) ) {
				// Stored as a Unix timestamp (output of time()); absint.
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LAST_CRON_CLEAR_STARTED_AT ] = absint(
					$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LAST_CRON_CLEAR_STARTED_AT ]
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED ] ) ) {
				$smtp_enabled = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED ] )
				);
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED ] = ( 'true' === $smtp_enabled ) ? 'true' : 'false';
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PROVIDER ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PROVIDER ] = sanitize_key(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PROVIDER ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD ] ) ) {
				$smtp_password_plaintext = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD ] )
				);
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD ] = leadconnector_encrypt_smtp_value( $smtp_password_plaintext );
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL ] = sanitize_email(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_SERVER ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_SERVER ] = sanitize_text_field(
					wp_unslash( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_SERVER ] )
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PORT ] ) ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PORT ] = absint(
					$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PORT ]
				);
			}
			if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_NATIVE_MODE_ALLOWED ] ) ) {
				// 3.0.32: store the native-mode opt-in toggle as a canonical
				// '0' / '1' string. Same shape the React admin UI sends and
				// the same shape that other boolean toggles in this option
				// blob use, so the JS form binding logic does not need
				// special casing.
				$native_mode_raw = $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_NATIVE_MODE_ALLOWED ];
				if ( is_string( $native_mode_raw ) ) {
					$native_mode_raw = strtolower( trim( wp_unslash( $native_mode_raw ) ) );
					$is_truthy       = in_array( $native_mode_raw, array( '1', 'true', 'yes', 'on' ), true );
				} else {
					$is_truthy = (bool) $native_mode_raw;
				}
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_NATIVE_MODE_ALLOWED ] = $is_truthy ? '1' : '0';
			}
		}

		$options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		$api_key = isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] ) ? $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] : null;

		if ( ! $api_key ) {
			$api_key = isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] ) ? $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] : null;
		}

		$old_api_key           = '';
		$old_location_id       = '';
		$old_text_widget_error = '0';

		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] ) ) {
			$old_api_key = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] );
		}

		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ) {
			$old_location_id = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] );
		}

		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] ) ) {
			$old_text_widget_error = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] );
		}

		$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_WARNING_TEXT ] = '';
		$api_key = trim( (string) $api_key );

		$enabled_text_widget = 0;
		if ( isset( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] ) ) {
			$enabled_text_widget = absint( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] );
		}

		if ( 1 === (int) $enabled_text_widget || $force_widget_refresh ) {
			$selected_chat_widget_id = '';
			if ( ! empty( $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] ) ) {
				$selected_chat_widget_id = $input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ];
			}

			// Change Here.
			$leadconnector_chat_response = $this->leadconnector_wp_get( 'get_chat_widget', array( 'selectedChatWidgetId' => $selected_chat_widget_id ) );

			if ( is_array( $leadconnector_chat_response ) && isset( $leadconnector_chat_response['error'] ) && true === $leadconnector_chat_response['error'] ) {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ]         = '1';
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] = isset( $leadconnector_chat_response['message'] )
					? sanitize_text_field( (string) $leadconnector_chat_response['message'] )
					: 'API error';
				return $input;
			}
			if ( is_array( $leadconnector_chat_response ) && isset( $leadconnector_chat_response['body'] ) ) {
				$leadconnector_chat_response = $leadconnector_chat_response['body'];
			}

			if ( is_object( $leadconnector_chat_response ) && empty( $leadconnector_chat_response->error ) ) {

				$obj = $leadconnector_chat_response;

				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ]             = sanitize_text_field( (string) $obj->id );
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ]       = 0;
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] = sanitize_text_field(
					(string) leadconnector_api_prop( $obj, 'chatWidgetId', '' )
				);
			} else {
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] = '1';
				$error_msg = ( is_object( $leadconnector_chat_response ) && isset( $leadconnector_chat_response->error ) )
					? sanitize_text_field( (string) $leadconnector_chat_response->error )
					: 'Chat widget API error';
				$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] = $error_msg;
			}
		} else {
			$input[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] = $old_location_id;
		}

		return $input;
	}

	/**
	 * Sanitize chat widget settings stored as JSON in plugin options.
	 *
	 * @param mixed $settings Raw settings payload from the settings form or API.
	 * @return string JSON-encoded sanitized settings.
	 */
	private function leadconnector_sanitize_text_widget_settings_option( $settings ) {
		if ( is_string( $settings ) ) {
			$settings = json_decode( wp_unslash( $settings ), true );
		}

		if ( ! is_array( $settings ) ) {
			return '';
		}

		return wp_json_encode( $this->leadconnector_sanitize_settings_array( $settings ) );
	}

	/**
	 * Recursively sanitize scalar values in a settings array.
	 *
	 * @param array $settings Settings array.
	 * @return array
	 */
	private function leadconnector_sanitize_settings_array( array $settings ) {
		$sanitized = array();

		foreach ( $settings as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->leadconnector_sanitize_settings_array( $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $key ] = $value;
			} else {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Filter Post information
	 * This will get on each post to get the fields required at front-end
	 *
	 * @since    1.0.0
	 * @param      string $template_id   Post ID.
	 * @param      string $location_id  Location ID.
	 */
	public function get_item( $template_id, $location_id ) {
		$post                                = get_post( $template_id );
		$user                                = get_user_by( 'id', $post->post_author );
		$date                                = strtotime( $post->post_date );
		$funnel_step_url                     = get_post_meta( $post->ID, 'leadconnector_step_url', true );
		$slug                                = get_post_meta( $post->ID, 'leadconnector_slug', true );
		$leadconnector_funnel_name           = get_post_meta( $post->ID, 'leadconnector_funnel_name', true );
		$leadconnector_funnel_id             = get_post_meta( $post->ID, 'leadconnector_funnel_id', true );
		$leadconnector_step_id               = get_post_meta( $post->ID, 'leadconnector_step_id', true );
		$leadconnector_step_name             = get_post_meta( $post->ID, 'leadconnector_step_name', true );
		$leadconnector_display_method        = get_post_meta( $post->ID, 'leadconnector_display_method', true );
		$leadconnector_include_tracking_code = get_post_meta( $post->ID, 'leadconnector_include_tracking_code', true );
		$leadconnector_use_site_favicon      = get_post_meta( $post->ID, 'leadconnector_use_site_favicon', true );
		$leadconnector_include_wp_headers_and_footers = get_post_meta( $post->ID, 'leadconnector_include_wp_headers_and_footers', true );

		$base_url = get_home_url() . '/';

		$data = array(
			'template_id'                                  => $post->ID,
			'title'                                        => $post->post_title,
			'thumbnail'                                    => get_the_post_thumbnail_url( $post ),
			'date'                                         => $date,
			'human_date'                                   => date_i18n( get_option( 'date_format' ), $date ),
			'human_modified_date'                          => date_i18n( get_option( 'date_format' ), strtotime( $post->post_modified ) ),
			'author'                                       => $user ? $user->display_name : 'undefined',
			'status'                                       => $post->post_status,
			'url'                                          => $base_url . $slug,
			'slug'                                         => $slug,
			'funnel_step_url'                              => $funnel_step_url,
			'leadconnector_funnel_name'                    => $leadconnector_funnel_name,
			'leadconnector_funnel_id'                      => $leadconnector_funnel_id,
			'leadconnector_step_id'                        => $leadconnector_step_id,
			'leadconnector_step_name'                      => $leadconnector_step_name,
			'location_id'                                  => $location_id,
			'leadconnector_display_method'                 => $leadconnector_display_method,
			'leadconnector_include_tracking_code'          => $leadconnector_include_tracking_code,
			'leadconnector_use_site_favicon'               => $leadconnector_use_site_favicon,
			'leadconnector_include_wp_headers_and_footers' => $leadconnector_include_wp_headers_and_footers,
		);

		return $data;
	}

	/**
	 * Disconnect the site from LeadConnector by clearing OAuth tokens.
	 *
	 * @return array
	 */
	public function leadconnector_disconnect() {
		$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ]  = '';
		$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] = '';
		$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ]         = 'leadconnector_disconnect';

		$option_saved = update_option( LEAD_CONNECTOR_OPTION_NAME, $new_options );

		// Clear all caches to ensure widget is removed from cached pages.
		$this->clear_all_caches();

		return array(
			'-success'      => true,
			'options_saved' => $option_saved,
		);
	}

	/**
	 * Public API handler.
	 * REST API call from front-end will end up here, where endpoint query param will be the action or remote API path.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request REST request object.
	 */
	public function leadconnector_public_api_proxy( $request ) {

		$options         = get_option( LEAD_CONNECTOR_OPTION_NAME );
		$params          = $request->get_query_params();
		$endpoint        = isset( $params[ lead_connector_constants\LEADCONNECTOR_REST_API_ENDPOINT_PARAM ] )
			? $params[ lead_connector_constants\LEADCONNECTOR_REST_API_ENDPOINT_PARAM ]
			: '';
		$direct_endpoint = isset( $params[ lead_connector_constants\LEADCONNECTOR_REST_API_DIRECT_ENDPOINT_PARAM ] )
			? $params[ lead_connector_constants\LEADCONNECTOR_REST_API_DIRECT_ENDPOINT_PARAM ]
			: '';

		// SECURITY (#A4): the GET branch of /leadconnector_api/v1/proxy is
		// registered with a read-only permission callback (capability check
		// only, no nonce verification). For state-changing `endpoint` values
		// we still want the WordPress REST nonce to be present, otherwise a
		// drive-by CSRF (image preload, browser extension, third-party admin
		// plugin) could trigger a side-effecting branch using nothing but the
		// site cookie. Promote the mutating-permission check inline here so
		// the gate covers the GET path without changing the route signature
		// (which the bundled admin Vue app relies on - it issues these calls
		// as GETs but always attaches X-WP-Nonce via wpFetch()).
		$leadconnector_mutating_proxy_endpoints = array(
			'wp_disconnect',
			'wp_validate_oauth',
			'wp_regenerate_token',
			'wp_enable_email',
			'wp_disable_email',
			'wp_save_options',
			'wp_insert_post',
			'wp_delete_post',
			'clear_cached_custom_values',
			'wp_purge_all_domains_cache',
		);
		if ( 'GET' === $request->get_method() && in_array( $endpoint, $leadconnector_mutating_proxy_endpoints, true ) ) {
			$leadconnector_rest_nonce = $request->get_header( 'X-WP-Nonce' );
			if ( empty( $leadconnector_rest_nonce ) ) {
				$leadconnector_rest_nonce = $request->get_param( '_wpnonce' );
			}
			if ( empty( $leadconnector_rest_nonce ) || ! wp_verify_nonce( $leadconnector_rest_nonce, 'wp_rest' ) ) {
				return new WP_Error(
					'leadconnector_rest_missing_nonce',
					__( 'This endpoint is state-changing and requires a valid X-WP-Nonce header.', 'leadconnector' ),
					array( 'status' => 403 )
				);
			}
		}

		$leadconnector_access_token = '';
		$api_key                    = '';
		$authorized_location_id     = '';

		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ) {
			$authorized_location_id = $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ];
			$authorized_location_id = trim( $authorized_location_id );
		}

		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] ) ) {
			$api_key = $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ];
			$api_key = trim( $api_key );
		}

		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ] ) ) {
			$leadconnector_access_token = $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ];
			$leadconnector_access_token = $this->leadconnector_decrypt_string( $leadconnector_access_token );
		}

		if ( 'wp_regenerate_token' === $endpoint ) {
			return $this->leadconnector_oauth_regenerate_token();
		}

		if ( 'wp_validate_auth_state' === $endpoint ) {

			// This should for both api key or oauth.
			$has_connected_auth = false;
			$connection_method  = '';

			$funnel_fetch_response = array( 'error' => true );

			if ( '' !== $leadconnector_access_token || '' !== $api_key ) {
				$has_connected_auth = true;
			}

			if ( $has_connected_auth ) {
				if ( '' !== $leadconnector_access_token ) {
					$connection_method = 'oauth';
				} else {
					$connection_method = 'api_key';
				}

				$funnel_fetch_response = $this->leadconnector_wp_get( 'funnels_get_list' );
			}
			$funnel_fetch_response = (array) $funnel_fetch_response;

			$is_connection_status_active = false;
			if ( ! array_key_exists( 'error', $funnel_fetch_response ) ) {
				$is_connection_status_active = true;
			}

			// SECURITY (#A1): never return decrypted OAuth access tokens, refresh
			// tokens, raw API keys, or the full options blob to the browser.
			// The admin UI only needs presence/status flags - the bearer is
			// attached server-side by leadconnector_public_api_proxy() when the
			// admin proxies a real LeadConnector call.
			return array(
				'is_connection_status_active' => $is_connection_status_active,
				'has_connected_auth'          => $has_connected_auth,
				'connection_method'           => $connection_method,
				'authorized_location_id'      => $authorized_location_id,
				'has_api_key'                 => '' !== $api_key,
				'has_access_token'            => '' !== $leadconnector_access_token,
				'funnelFetchResponse'         => $funnel_fetch_response,
			);
		}

		if ( 'wp_disconnect' === $endpoint ) {
			// Reset All Options.
			$new_options = array();

			$option_saved = update_option( LEAD_CONNECTOR_OPTION_NAME, $new_options );

			// Clear all caches to ensure widget is removed from cached pages.
			$this->clear_all_caches();

			return array(
				'success'       => true,
				'options_saved' => $option_saved,
			);
		}

		if ( 'wp_validate_oauth' === $endpoint ) {

			$body = LeadConnector_Input_Validator::proxy_query_as_object( $request, $params );
			if ( is_wp_error( $body ) ) {
				return array(
					'error'   => true,
					'message' => $body->get_error_message(),
				);
			}
			// #M9: `code` is the OAuth authorization code returned by the
			// LeadConnector consent screen — required. Surface a clear
			// WP_Error-shaped response instead of triggering an "Undefined
			// property: stdClass::$code" PHP warning when the admin UI
			// posts an incomplete graph.
			if ( ! isset( $body->code ) || '' === (string) $body->code ) {
				return array(
					'error'   => true,
					'message' => __( 'Missing required field: code', 'leadconnector' ),
					'field'   => 'code',
				);
			}
			$token_response   = $this->leadconnector_perform_oauth_request( 'validate', $body->code );
			$previous_options = get_option( LEAD_CONNECTOR_OPTION_NAME );
			if ( ! is_array( $previous_options ) ) {
				$previous_options = array();
			}

			$option_saved = false;

			if ( ! property_exists( $token_response, 'error' ) ) {
				if ( 'Location' === leadconnector_api_prop( $token_response, 'userType' ) ) {

					$previous_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ]  = $this->leadconnector_encrypt_string( $token_response->access_token );
					$previous_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] = $this->leadconnector_encrypt_string( $token_response->refresh_token );
					$previous_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ]         = leadconnector_api_prop( $token_response, 'locationId' );
				}

				$option_saved = update_option( LEAD_CONNECTOR_OPTION_NAME, $previous_options );
			}

			return array(
				'options_saved' => $option_saved,
				'success'       => ! property_exists( $token_response, 'error' ),
				'location_id'   => leadconnector_api_prop( $token_response, 'locationId' ),
			);
		}

		if ( 'POST' === $request->get_method() ) {

			if ( 'wp_save_options' === $endpoint ) {
				$body = LeadConnector_Input_Validator::proxy_post_as_object( $request );
				if ( is_wp_error( $body ) ) {
					return array(
						'error'   => true,
						'message' => $body->get_error_message(),
					);
				}

				if ( isset( $body->enable_text_widget ) ) {
					$options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] = $body->enable_text_widget;
				}
				if ( isset( $body->api_key ) ) {
					$options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] = $body->api_key;
				}
				if ( isset( $body->selected_chat_widget ) ) {
					$options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] = $body->selected_chat_widget;
				}

				$new_options             = $this->sanitize_settings_with_widget_refresh( $options, true );
				$text_widget_error       = '0';
				$error_details           = '';
				$warning_msg             = '';
				$white_label_url         = '';
				$enable_text_widget      = '';
				$api_key                 = '';
				$location_id             = '';
				$selected_chat_widget_id = '';

				if ( isset( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] ) ) {
					$text_widget_error = sanitize_text_field( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] );
				}
				if ( isset( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] ) ) {
					$error_details = sanitize_text_field( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] );
				}
				if ( isset( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_WARNING_TEXT ] ) ) {
					$warning_msg = sanitize_text_field( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_WARNING_TEXT ] );
				}
				if ( isset( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL ] ) ) {
					$white_label_url = esc_url_raw( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL ] );
				}
				if ( isset( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] ) ) {
					$enable_text_widget = absint( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] );
				}
				if ( isset( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] ) ) {
					$api_key = sanitize_text_field( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] );
				}
				if ( isset( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ) {
					$location_id = sanitize_text_field( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] );
				}
				if ( isset( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] ) ) {
					$selected_chat_widget_id = sanitize_text_field( $new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] );
				}

				$option_saved = update_option( LEAD_CONNECTOR_OPTION_NAME, $new_options );

				// Clear all caches when widget settings are updated.
				$this->clear_all_caches();

				if ( '1' === $text_widget_error ) {
					return ( array(
						'error'   => true,
						'message' => $error_details,
					) );
				}
				return ( array(
					'success'                 => true,
					'warning_msg'             => $warning_msg,
					'api_key'                 => $api_key,
					'enable_text_widget'      => $enable_text_widget,
					'selected_chat_widget_id' => $selected_chat_widget_id,
					'home_url'                => get_home_url(),
					'white_label_url'         => $white_label_url,
					'location_id'             => $location_id,
				) );
			}

			if ( 'wp_insert_post' === $endpoint ) {
				$body = LeadConnector_Input_Validator::proxy_post_as_object( $request );
				if ( is_wp_error( $body ) ) {
					return array(
						'error'   => true,
						'message' => $body->get_error_message(),
					);
				}

				// #M9: enforce required fields up front so missing keys
				// surface a clean WP_Error envelope to the admin UI instead
				// of producing `Undefined property` PHP warnings and
				// half-populated post meta on partial payloads.
				$required_fields = array(
					'leadconnector_slug',
					'leadconnector_step_id',
					'leadconnector_step_name',
					'leadconnector_funnel_id',
					'leadconnector_funnel_name',
					'leadconnector_step_url',
					'template_id',
					'leadconnector_display_method',
				);
				foreach ( $required_fields as $required_field ) {
					if ( ! isset( $body->{$required_field} ) ) {
						return array(
							'error'   => true,
							'message' => sprintf(
								/* translators: %s: missing field name */
								__( 'Missing required field: %s', 'leadconnector' ),
								$required_field
							),
							'field'   => $required_field,
						);
					}
				}

				$leadconnector_slug                 = $body->leadconnector_slug;
				$leadconnector_step_id              = $body->leadconnector_step_id;
				$leadconnector_step_name            = $body->leadconnector_step_name;
				$leadconnector_funnel_id            = $body->leadconnector_funnel_id;
				$leadconnector_funnel_name          = $body->leadconnector_funnel_name;
				$leadconnector_step_url             = $body->leadconnector_step_url;
				$leadconnector_step_page_id         = isset( $body->leadconnector_step_page_id ) ? $body->leadconnector_step_page_id : null;
				$template_id                        = $body->template_id;
				$leadconnector_display_method       = $body->leadconnector_display_method;
				$leadconnector_step_meta            = null;
				$leadconnector_step_tracking_code   = null;
				$leadconnector_funnel_tracking_code = null;

				// The Vue editor sends these flags as JSON booleans, while older
				// callers (and the legacy admin form) sent them as the string
				// "1"/"0". wp_validate_boolean() accepts every shape the input
				// validator produces (PHP bool, "1"/"0", "true"/"false", "yes"/"on",
				// 1/0) so the saved post meta correctly reflects the checkbox
				// state regardless of caller. A previous strict === "1" check
				// silently rejected booleans, causing every save to store false.
				$leadconnector_include_tracking_code          = isset( $body->leadconnector_include_tracking_code )
					? wp_validate_boolean( $body->leadconnector_include_tracking_code )
					: false;
				$leadconnector_use_site_favicon               = isset( $body->leadconnector_use_site_favicon )
					? wp_validate_boolean( $body->leadconnector_use_site_favicon )
					: false;
				$leadconnector_include_wp_headers_and_footers = isset( $body->leadconnector_include_wp_headers_and_footers )
					? wp_validate_boolean( $body->leadconnector_include_wp_headers_and_footers )
					: false;
				// Old Method provided meta in the body itself - Now we have to fetch it from the backend.
				$include_meta_tags = true;
				if ( $include_meta_tags ) {
					$leadconnector_step_meta_response = $this->leadconnector_oauth_wp_remote_v2(
						'get',
						sprintf( 'funnels/page/data?pageId=%s', $leadconnector_step_page_id ),
						array(),
						null,
						LEAD_CONNECTOR_MARKETPLACE_BACKEND_URL,
						true
					);

					$leadconnector_step_meta = $leadconnector_step_meta_response['body']->data->pageMeta ?? null;
				}

				if ( isset( $body->leadconnector_funnel_tracking_code ) && $leadconnector_include_tracking_code ) {
					$leadconnector_funnel_tracking_code = wp_json_encode( $body->leadconnector_funnel_tracking_code, JSON_UNESCAPED_UNICODE );
				}

				if ( isset( $body->leadconnector_step_page_download_url ) && $leadconnector_include_tracking_code ) {
					$leadconnector_dl_parsed  = wp_parse_url( $body->leadconnector_step_page_download_url );
					$leadconnector_dl_allowed = array( 'rest.leadconnectorhq.com', 'services.leadconnectorhq.com', 'api.leadconnectorhq.com', 'app.leadconnectorhq.com' );
					if ( empty( $leadconnector_dl_parsed['host'] ) || ! in_array( $leadconnector_dl_parsed['host'], $leadconnector_dl_allowed, true ) ) {
						return array(
							'error'   => true,
							'message' => 'Download URL host not allowed.',
						);
					}

					$args               = array(
						'timeout' => 60,
					);
					$response_code      = wp_remote_get( esc_url_raw( $body->leadconnector_step_page_download_url ), $args );
					$http_tracking_code = wp_remote_retrieve_response_code( $response_code );
					if ( 200 === $http_tracking_code ) {
						$body_tracking_code = wp_remote_retrieve_body( $response_code );

						$tracking_res = json_decode( $body_tracking_code, false );

						if ( isset( $tracking_res ) ) {
							$tracking_code = leadconnector_api_prop( $tracking_res, 'trackingCode' );
							if ( null !== $tracking_code ) {
								$leadconnector_step_tracking_code = wp_json_encode( $tracking_code, JSON_UNESCAPED_UNICODE );
							}
						}
					}
				}

				// Normalize before every comparison below. `template_id`
				// arrives from a JSON graph, so it may legitimately be the
				// string "-1" rather than the integer -1. Both guards in this
				// branch are strict comparisons against an int, so an
				// un-normalized string value would simultaneously skip the
				// duplicate-slug check and take the update path with a
				// non-integer post ID.
				$template_id = is_numeric( $template_id ) ? (int) $template_id : -1;

				$existing_funnel_post_id = leadconnector_get_funnel_post_id_by_slug( $leadconnector_slug );
				if ( $existing_funnel_post_id > 0 && -1 === $template_id ) {
					return ( array(
						'error'   => true,
						'message' => "slug '" . $leadconnector_slug . "' already exist",
						'code'    => 1009,
					) );
				}

				$user           = wp_get_current_user();
				$user_id        = get_current_user_id();
				$user_logged_in = is_user_logged_in();

				$post_data                 = array();
				$post_data['post_title']   = 'LeadConnector_funnel-' . $leadconnector_funnel_name . '-step-' . $leadconnector_step_name;
				$post_data['post_content'] = '';
				$post_data['post_type']    = lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE;
				$post_data['post_status']  = 'publish';
				if ( -1 !== $template_id ) {
					// Object-level authorization. Without this, any caller past
					// the route's permission callback could pass an arbitrary
					// post ID and wp_insert_post() would *update* that post:
					// blanking post_content, retyping it to the funnel CPT and
					// forcing it to publish. Mirror the checks the sibling
					// `wp_delete_post` branch already performs on its target.
					$leadconnector_target_post = get_post( $template_id );
					if ( ! $leadconnector_target_post instanceof WP_Post
						|| lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE !== $leadconnector_target_post->post_type
					) {
						return array(
							'error'   => true,
							'message' => 'Invalid template_id: not a LeadConnector funnel post.',
						);
					}

					if ( ! current_user_can( 'edit_post', $template_id ) ) {
						return array(
							'error'   => true,
							'message' => 'You are not allowed to edit this funnel post.',
						);
					}

					$post_data['ID'] = $template_id;
				}
				$post_id = wp_insert_post( $post_data );
				if ( is_wp_error( $post_id ) || 0 === $post_id ) {
					return ( array(
						'error'   => true,
						'message' => 'fail to save the post',
					) );
				}
				if ( isset( $leadconnector_slug ) ) {
					update_post_meta( $post_id, 'leadconnector_slug', $leadconnector_slug );
					leadconnector_register_funnel_slug( $post_id, $leadconnector_slug );
				}
				if ( isset( $leadconnector_step_id ) ) {
					update_post_meta( $post_id, 'leadconnector_step_id', $leadconnector_step_id );
				}
				if ( isset( $leadconnector_step_name ) ) {
					update_post_meta( $post_id, 'leadconnector_step_name', $leadconnector_step_name );
				}
				if ( isset( $leadconnector_funnel_id ) ) {
					update_post_meta( $post_id, 'leadconnector_funnel_id', $leadconnector_funnel_id );
				}
				if ( isset( $leadconnector_funnel_name ) ) {
					update_post_meta( $post_id, 'leadconnector_funnel_name', $leadconnector_funnel_name );
				}
				if ( isset( $leadconnector_step_url ) ) {
					update_post_meta( $post_id, 'leadconnector_step_url', $leadconnector_step_url );
				}
				if ( isset( $leadconnector_display_method ) ) {
					update_post_meta( $post_id, 'leadconnector_display_method', $leadconnector_display_method );
				}
				if ( isset( $leadconnector_step_meta ) ) {
					update_post_meta( $post_id, 'leadconnector_step_meta', wp_json_encode( $leadconnector_step_meta, JSON_UNESCAPED_UNICODE ) );
				}
				if ( isset( $leadconnector_step_tracking_code ) ) {
					update_post_meta( $post_id, 'leadconnector_step_trackingCode', $leadconnector_step_tracking_code );
				}
				if ( isset( $leadconnector_include_tracking_code ) ) {
					update_post_meta( $post_id, 'leadconnector_include_tracking_code', $leadconnector_include_tracking_code );
				}
				if ( isset( $leadconnector_use_site_favicon ) ) {
					update_post_meta( $post_id, 'leadconnector_use_site_favicon', $leadconnector_use_site_favicon );
				}
				if ( isset( $leadconnector_funnel_tracking_code ) ) {
					update_post_meta( $post_id, 'leadconnector_funnel_tracking_code', $leadconnector_funnel_tracking_code );
				}
				if ( isset( $leadconnector_include_wp_headers_and_footers ) ) {
					update_post_meta( $post_id, 'leadconnector_include_wp_headers_and_footers', $leadconnector_include_wp_headers_and_footers );
				}

				return ( array(
					'success' => true,
				) );
			}
			return;
		}

		if ( 'wp_get_all_posts' === $endpoint ) {

			$body = LeadConnector_Input_Validator::proxy_query_as_object( $request, $params );
			if ( is_wp_error( $body ) ) {
				return array(
					'error'   => true,
					'message' => $body->get_error_message(),
				);
			}
			$per_page = $body->per_page;
			$page     = $body->page;

			$query_args = array(
				'post_type'      => lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE,
				'post_status'    => 'any',
				'compare'        => '=',
				'posts_per_page' => $per_page,
				'paged'          => $page,
			);

			$the_posts = new WP_Query( $query_args );

			$templates   = array();
			$location_id = '';
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ) {
				$location_id = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] );
			}

			if ( $the_posts->have_posts() ) :
				while ( $the_posts->have_posts() ) :
					$the_posts->the_post();
					$templates[] = $this->get_item( get_the_ID(), $location_id );
				endwhile;
			endif;

			$final_output = array(
				'funnels'    => $templates,
				'pagination' => array(
					'total'       => count( $templates ),
					'page'        => $page,
					'per_page'    => $per_page,
					'total_pages' => $the_posts->max_num_pages,
				),
			);

			return $final_output;
		}

		if ( 'wp_get_lc_options' === $endpoint ) {

			$enabled_text_widget     = 0;
			$text_widget_error       = '0';
			$error_details           = '';
			$warning_msg             = '';
			$white_label_url         = '';
			$location_id             = '';
			$selected_chat_widget_id = null;

			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] ) ) {
				$text_widget_error = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] );
			}
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] ) ) {
				$error_details = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR_DETAILS ] );
			}
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_WARNING_TEXT ] ) ) {
				$warning_msg = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_WARNING_TEXT ] );
			}
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] ) ) {
				$enabled_text_widget = absint( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] );
			}
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL ] ) ) {
				$white_label_url = esc_url_raw( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL ] );
			}
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ) {
				$location_id = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] );
			}
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] ) ) {
				$selected_chat_widget_id = sanitize_text_field( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] );
			}

			// SECURITY (#A1): never return decrypted OAuth access tokens, raw
			// API keys, or the entire options array to the browser. The admin
			// UI only needs the connection-status snapshot. Use has_* booleans
			// instead of the raw secrets so the UI can still tell if the user
			// has connected.
			$data = array(
				'has_api_key'             => '' !== $api_key,
				'has_oauth_access_token'  => isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ] )
					&& '' !== $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ],
				'enable_text_widget'      => $enabled_text_widget,
				'text_widget_error'       => '1' === $text_widget_error,
				'error_details'           => $error_details,
				'warning_msg'             => $warning_msg,
				'selected_chat_widget_id' => $selected_chat_widget_id,
				'home_url'                => get_home_url(),
				'white_label_url'         => $white_label_url,
				'location_id'             => $location_id,
				// Base URL for static assets in this plugin admin images directory (used by the bundled admin UI).
				'plugin_directory_url'    => plugin_dir_url( __FILE__ ),
			);

			return $data;
		}

		if ( 'wp_delete_post' === $endpoint ) {
			$body = LeadConnector_Input_Validator::proxy_query_as_object( $request, $params );
			if ( is_wp_error( $body ) ) {
				return array(
					'error'   => true,
					'message' => $body->get_error_message(),
				);
			}

			// #M9: harden against partial payloads — both `post_id` and
			// `force_delete` are required and must coerce cleanly to int /
			// bool. Without these guards a missing field triggers an
			// "Undefined property" PHP warning and wp_delete_post() is
			// invoked with null arguments, deleting nothing but emitting a
			// confusing warning chain.
			if ( ! isset( $body->post_id ) ) {
				return array(
					'error'   => true,
					'message' => __( 'Missing required field: post_id', 'leadconnector' ),
					'field'   => 'post_id',
				);
			}
			$post_id      = (int) $body->post_id;
			$force_delete = isset( $body->force_delete ) ? (bool) $body->force_delete : false;

			if ( $post_id <= 0 ) {
				return array(
					'error'   => true,
					'message' => __( 'Invalid post ID.', 'leadconnector' ),
					'field'   => 'post_id',
				);
			}

			$target_post = get_post( $post_id );
			if ( ! $target_post || lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE !== $target_post->post_type ) {
				return array(
					'error'   => true,
					'message' => __( 'Post not found or not a LeadConnector funnel.', 'leadconnector' ),
					'field'   => 'post_id',
				);
			}

			$post_info = wp_delete_post( $post_id, $force_delete );
			if ( ! $post_info ) {
				return ( array(
					'error'   => true,
					'message' => 'fail to delete the post',
				) );
			}
			return $post_info;
		}
		if ( 'true' === $direct_endpoint ) {
			$allowed_hosts = array(
				'rest.leadconnectorhq.com',
				'services.leadconnectorhq.com',
				'api.leadconnectorhq.com',
				'backend.leadconnectorhq.com',
				'app.leadconnectorhq.com',
			);
			$parsed        = wp_parse_url( $endpoint );
			if ( empty( $parsed['host'] ) || ! in_array( $parsed['host'], $allowed_hosts, true ) ) {
				return array(
					'error'   => true,
					'message' => 'Endpoint host not allowed.',
				);
			}
			$args      = array(
				'timeout' => 60,
			);
			$response  = wp_remote_get( esc_url_raw( $endpoint ), $args );
			$http_code = wp_remote_retrieve_response_code( $response );
			if ( 200 === $http_code ) {
				$body = wp_remote_retrieve_body( $response );
				$obj  = json_decode( $body, false );
				return $obj;
			} else {
				return ( array(
					'error' => true,
				) );
			}
		}

		if ( 'funnels_get_list' === $endpoint ) {
			return $this->leadconnector_wp_get( $endpoint );
		}

		// "Purge everything on all domains": proxied through leadconnector_wp_get (OAuth to LeadConnector services).
		if ( 'wp_purge_all_domains_cache' === $endpoint ) {
			return $this->leadconnector_wp_get( $endpoint );
		}

		if ( 'funnels_get_details' === $endpoint ) {
			$body = LeadConnector_Input_Validator::proxy_query_as_object( $request, $params );
			if ( is_wp_error( $body ) ) {
				return array(
					'error'   => true,
					'message' => $body->get_error_message(),
				);
			}
			return $this->leadconnector_wp_get( $endpoint, $body );
		}

		if ( 'wp_enable_email' === $endpoint ) {
			$body = LeadConnector_Input_Validator::proxy_query_as_object( $request, $params );
			if ( is_wp_error( $body ) ) {
				return array(
					'error'   => true,
					'message' => $body->get_error_message(),
				);
			}
			$response     = $this->leadconnector_wp_get( $endpoint, $body );
			$option_saved = false;
			if ( is_object( $response ) && empty( $response->error ) ) {

				$new_options            = get_option( LEAD_CONNECTOR_OPTION_NAME );
				$smtp_password_from_api = leadconnector_api_prop( $response, 'smtpPassword' );

				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED ]  = 'true';
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL ]    = leadconnector_api_prop( $response, 'smtpEmail' );
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD ] = is_string( $smtp_password_from_api )
					? leadconnector_encrypt_smtp_value( $smtp_password_from_api )
					: $smtp_password_from_api;
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PORT ]     = leadconnector_api_prop( $response, 'smtpPort' );
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_SERVER ]   = leadconnector_api_prop( $response, 'smtpServer' );
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PROVIDER ] = leadconnector_api_prop( $response, 'smtpProviderName' );

				$option_saved = update_option( LEAD_CONNECTOR_OPTION_NAME, $new_options );
				$option_saved = true;
			} elseif ( 400 === (int) $response->http_code && $response->error ) {
					$error_body = json_decode( $response->body );
				if ( $error_body->message ) {
					return array(
						'success' => false,
						'message' => $error_body->message,
					);
				}
			}

			return array(
				'success' => $option_saved,
			);
		}

		if ( 'wp_email_eligibility_check' === $endpoint ) {
			$email_enabled   = isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED ] )
				&& 'true' === $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED ];
			$active_email_id = isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL ] )
				? $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL ]
				: '';

			$response = $this->leadconnector_wp_get( $endpoint );

			return array(
				'emailEnabled'   => $email_enabled,
				'enabledEmailId' => $active_email_id,
				'response'       => $response,
			);
		}

		if ( 'wp_disable_email' === $endpoint ) {
			$body = LeadConnector_Input_Validator::proxy_query_as_object( $request, $params );
			if ( is_wp_error( $body ) ) {
				return array(
					'error'   => true,
					'message' => $body->get_error_message(),
				);
			}
			$response     = $this->leadconnector_wp_get( $endpoint, $body );
			$option_saved = false;
			if ( ( is_object( $response ) && empty( $response->error ) ) || ( is_object( $response ) && isset( $response->http_code ) && 500 === (int) $response->http_code ) ) {
				$new_options = get_option( LEAD_CONNECTOR_OPTION_NAME );

				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_ENABLED ]  = 'false';
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL ]    = null;
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD ] = null;
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PORT ]     = null;
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_SERVER ]   = null;
				$new_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PROVIDER ] = null;

				$option_saved = update_option( LEAD_CONNECTOR_OPTION_NAME, $new_options );
			}

			return array(
				'success'  => true,
				'response' => $response,
			);
		}

		if ( 'check_smtp_plugin_conflict' === $endpoint ) {

			return $this->check_smtp_plugin_conflict();
		}

		if ( isset( $params['data'] ) ) {
			$resolved = LeadConnector_Input_Validator::proxy_query_as_object( $request, $params );
			if ( is_wp_error( $resolved ) ) {
				return array(
					'error'   => true,
					'message' => $resolved->get_error_message(),
				);
			}
			$params = $resolved;
		}

		return $this->leadconnector_wp_get( $endpoint, $params );
	}

	/**
	 * Regenerate the OAuth access token using the stored refresh token.
	 *
	 * @return array
	 */
	private function leadconnector_oauth_regenerate_token() {

		$leadconnector_options = get_option( LEAD_CONNECTOR_OPTION_NAME );

		if ( ! is_array( $leadconnector_options ) ) {
			return array(
				'success' => false,
			);
		}

		// D1: was `&& '' ===` which short-circuited to true (and produced a
		// PHP notice on the unset offset). The intent was "missing OR empty",
		// which requires `||`.
		if (
			! isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] )
			|| '' === $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ]
		) {
			return array(
				'success' => false,
			);
		}

		$leadconnector_refresh_token = $this->leadconnector_decrypt_string( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] );
		$token_response              = $this->leadconnector_perform_oauth_request( 'refresh', $leadconnector_refresh_token, $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] );
		if ( ! isset( $token_response->response->error ) ) {
			if ( 'Location' === leadconnector_api_prop( $token_response, 'userType' ) ) {
				$leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ]         = leadconnector_api_prop( $token_response, 'locationId' );
				$leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ]  = $this->leadconnector_encrypt_string( $token_response->access_token );
				$leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_REFRESH_TOKEN ] = $this->leadconnector_encrypt_string( $token_response->refresh_token );
				update_option( LEAD_CONNECTOR_OPTION_NAME, $leadconnector_options );
			}
		}

		return array(
			'success' => true,
		);
	}


	/**
	 * Defer the custom-values database write to the next WP-Cron tick.
	 *
	 * The previous implementation issued a loopback `wp_remote_post()` to the
	 * plugin's own REST endpoint and forwarded WordPress authentication
	 * cookies (`LOGGED_IN_COOKIE`, `SECURE_AUTH_COOKIE`, `AUTH_COOKIE`) so the
	 * REST handler would run as the logged-in administrator. The WordPress.org
	 * plugin review team flagged that pattern as improper handling of
	 * authentication cookies: reading those cookies from `$_COOKIE` and
	 * transmitting them inside a plugin-generated HTTP request violates the
	 * "do not copy or transmit core auth cookies / keys / salts" guideline,
	 * regardless of whether the target is the same site.
	 *
	 * Switching to `wp_schedule_single_event()` removes the auth-cookie hand-off
	 * entirely, keeps the database write off the critical path of the original
	 * admin request (the same asynchronous goal), and also sidesteps the
	 * loopback's known failure modes (WAFs that strip the `Cookie` header from
	 * internal calls, sites where `home_url()` differs from `site_url()`).
	 *
	 * @since 3.0.32
	 * @param array $response API response array with `body->data`.
	 * @return void
	 */
	private function leadconnector_save_custom_values( $response ) {
		if ( ! is_array( $response ) || ! empty( $response['error'] ) ) {
			return;
		}
		if ( ! isset( $response['body']->data ) || ! is_array( $response['body']->data ) ) {
			return;
		}

		$custom_values = $this->normalize_custom_values_payload( $response['body']->data );
		if ( empty( $custom_values ) ) {
			return;
		}

		// WP-Cron stores the args array serialized in wp_options. The payload
		// has already been normalized to plain associative arrays of scalars
		// above so it serializes cleanly and the per-args signature used by
		// wp_next_scheduled() / wp_schedule_single_event() is stable.
		$args = array( $custom_values );
		if ( false === wp_next_scheduled( 'leadconnector_save_custom_values_event', $args ) ) {
			wp_schedule_single_event( time(), 'leadconnector_save_custom_values_event', $args );
		}
	}

	/**
	 * Normalize the LeadConnector custom-values API payload into the
	 * plain-array shape expected by LeadConnector_CustomValues::store_custom_values().
	 *
	 * The API returns `stdClass` instances after `json_decode()`; WP-Cron
	 * stores event arguments serialized in `wp_options`, so we flatten to
	 * arrays of scalars up front to keep the cron payload portable and to
	 * avoid persisting object instances in the options table.
	 *
	 * @since 3.0.32
	 * @param array $data Raw `body->data` rows from the LeadConnector custom-values endpoint.
	 * @return array<int, array{fieldKey:string,id:string}> Normalized rows safe to schedule.
	 */
	private function normalize_custom_values_payload( $data ) {
		$normalized = array();
		foreach ( $data as $row ) {
			if ( is_object( $row ) ) {
				$row = (array) $row;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}

			$field_key = '';
			if ( isset( $row['fieldKey'] ) ) {
				$field_key = (string) $row['fieldKey'];
			} elseif ( isset( $row['field_key'] ) ) {
				$field_key = (string) $row['field_key'];
			}

			$field_id = isset( $row['id'] ) ? (string) $row['id'] : '';

			if ( '' === $field_key || '' === $field_id ) {
				continue;
			}

			$normalized[] = array(
				'fieldKey' => $field_key,
				'id'       => $field_id,
			);
		}

		return $normalized;
	}

	/**
	 * WP-Cron callback that persists a previously-scheduled custom-values batch.
	 *
	 * Runs in cron context (no current user). Only writes to the plugin's own
	 * custom-values table via `LeadConnector_CustomValues::store_custom_values()`,
	 * which performs no capability checks because it is an internal,
	 * site-scoped data-cache write triggered by the admin who originally
	 * scheduled the event.
	 *
	 * @since 3.0.32
	 * @param array $custom_values Pre-normalized array of `{fieldKey, id}` rows.
	 * @return void
	 */
	public function leadconnector_handle_save_custom_values_event( $custom_values ) {
		if ( ! is_array( $custom_values ) || empty( $custom_values ) ) {
			return;
		}
		if ( ! class_exists( 'LeadConnector_CustomValues' ) ) {
			return;
		}

		$manager = new LeadConnector_CustomValues();
		$manager->store_custom_values( $custom_values );
	}

	/**
	 * Returns the WordPress site identifier used for remote cache purge requests.
	 *
	 * This value must match the site identifier associated with the connected location
	 * in LeadConnector. Define `CDN_WP_ID` in wp-config.php when available; otherwise
	 * `CDN_SITE_ID` is read for backward compatibility.
	 *
	 * @return string Site identifier, or empty string if neither constant is defined.
	 */
	private function get_cdn_wp_id() {
		if ( defined( 'CDN_WP_ID' ) && '' !== (string) CDN_WP_ID ) {
			return (string) CDN_WP_ID;
		}
		if ( defined( 'CDN_SITE_ID' ) ) {
			return (string) CDN_SITE_ID;
		}
		return '';
	}

	/**
	 * Eligibility for leadconnector_wp_get( 'wp_purge_all_domains_cache' ) (OAuth token, location, CDN id).
	 *
	 * Same boolean as leadconnector_cdn_config.wpPurgeAllDomainsCacheAuthorized, leadconnector_rest_wp_purge_all_domains_cache_authorization_status, and toolbar row.
	 *
	 * @return bool
	 */
	private function is_wp_purge_all_domains_cache_authorized() {
		if ( '' === $this->get_cdn_wp_id() ) {
			return false;
		}
		$leadconnector_options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		if ( empty( $leadconnector_options ) || ! is_array( $leadconnector_options ) ) {
			return false;
		}
		$leadconnector_location_id = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] )
			? trim( (string) $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] )
			: '';
		if ( '' === $leadconnector_location_id || 'leadconnector_disconnect' === $leadconnector_location_id ) {
			return false;
		}
		$leadconnector_access_token = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ] )
			? $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ]
			: '';
		$leadconnector_access_token = $this->leadconnector_decrypt_string( $leadconnector_access_token );
		return '' !== $leadconnector_access_token;
	}

	/**
	 * Route a named endpoint action to the appropriate API call.
	 *
	 * @param string $endpoint Action slug or API path.
	 * @param array  $params   Optional query parameters.
	 * @return mixed
	 */
	private function leadconnector_wp_get( $endpoint, $params = array() ) {

		$params                = (object) $params;
		$leadconnector_options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		$use_v2_request        = false;
		$use_v2_post_request   = false;

		if ( 'wp_get_cron_count' === $endpoint ) {
			$has_cron = wp_next_scheduled( 'leadconnector_twicedaily_refresh_req' );

			$maintenance_mode = false;
			$is_allowed       = false;
			if ( file_exists( ABSPATH . '.maintenance' ) ) {
				$maintenance_mode = true;
			}

			if ( $has_cron ) {
				$can_do_response = $this->leadconnector_wp_get( 'utils_can_do', array( 'action' => 'mass_clear_cron' ) );
				$is_allowed      = $can_do_response->allowed;
			}
			return array(
				'has_cron'         => $has_cron,
				'maintenance_mode' => $maintenance_mode,
				'is_allowed'       => $is_allowed,
			);
		}

		$api_key                    = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] ) ? $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] : null;
		$leadconnector_access_token = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ] ) ? $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ] : null;
		$leadconnector_access_token = $this->leadconnector_decrypt_string( $leadconnector_access_token );
		$leadconnector_location_id  = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ? $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] : null;

		$selected_chat_widget_id = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] ) ? $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] : null;
		if ( null !== leadconnector_api_prop( $params, 'selectedChatWidgetId' ) ) {
			$selected_chat_widget_id = leadconnector_api_prop( $params, 'selectedChatWidgetId' );
		}

		$final_endpoint = '';
		$auth_method    = 'api_key';

		if ( '' !== $leadconnector_access_token ) {
			$auth_method = 'oauth';
		}

		$base_host = LEAD_CONNECTOR_BASE_URL;

		if ( 'api_key' === $auth_method ) {

			if ( 'funnels_get_list' === $endpoint ) {
				$final_endpoint = 'v1/funnels/?includeDomainId=true&includeTrackingCode=true';
			}

			if ( 'get_chat_widget' === $endpoint ) {
				$final_endpoint = 'v1/locations/me?includeWhiteLabelUrl=true';
			}

			if ( 'funnels_get_details' === $endpoint ) {
				$final_endpoint = 'v1/funnels/' . leadconnector_api_prop( $params, 'funnelId' ) . '/pages/?includeMeta=true&includePageDataDownloadURL=true';
			}
		} else {
			$base_host = LEAD_CONNECTOR_SERVICES_BASE_URL;

			if ( 'funnels_get_list' === $endpoint ) {
				$final_endpoint = 'funnels/funnel/list?locationId=' . $leadconnector_location_id;
			}

			if ( 'get_chat_widget' === $endpoint ) {
				if ( $selected_chat_widget_id ) {
					$final_endpoint = 'wordpress/lc-plugin/chat-widget/data/' . $leadconnector_location_id . '/' . $selected_chat_widget_id;
				} else {
					$final_endpoint = 'wordpress/lc-plugin/chat-widget/' . $leadconnector_location_id . '/';
				}
			}

			if ( 'wp_email_eligibility_check' === $endpoint ) {
				$final_endpoint = 'wordpress/lc-plugin/emails/eligibility/' . $leadconnector_location_id . '/';
			}

			if ( 'wp_enable_email' === $endpoint ) {
				$final_endpoint = 'wordpress/lc-plugin/emails/generate-smtp/' . $leadconnector_location_id . '/?domain=' . $params->domain . '&emailPrefix=' . leadconnector_api_prop( $params, 'emailPrefix' );
			}
			if ( 'wp_disable_email' === $endpoint ) {
				$final_endpoint = 'wordpress/lc-plugin/emails/delete-smtp/' . $leadconnector_location_id . '/?emailId=' . $params->email;
			}

			if ( 'forms_get_list' === $endpoint ) {
				$final_endpoint = 'wordpress/lc-plugin/forms/' . $leadconnector_location_id . '/?page=' . $params->page . '&perPage=' . $params->per_page;
			}

			if ( 'phone_numbers_get_list' === $endpoint ) {
				$final_endpoint = 'wordpress/lc-plugin/phone-system/pools/' . $leadconnector_location_id;
			}

			if ( 'utils_can_do' === $endpoint ) {
				$final_endpoint = "wordpress/lc-plugin/utils/can-do/$leadconnector_location_id/?action=" . $params->action . '&domain=' . get_home_url();
			}

			if ( 'get_chat_widgets' === $endpoint ) {
				$final_endpoint = "wordpress/lc-plugin/chat-widget/list/{$leadconnector_location_id}";
			}

			if ( 'get_custom_values' === $endpoint ) {
				$final_endpoint = "wordpress/lc-plugin/custom-values/{$leadconnector_location_id}/values?skip={$params->skip}&limit={$params->limit}&query=" . $params->query;
				$use_v2_request = true;
			}

			if ( 'get_custom_value_folders' === $endpoint ) {
				$final_endpoint = "wordpress/lc-plugin/custom-values/{$leadconnector_location_id}/values?getFolders=true&skip={$params->skip}&limit={$params->limit}";
				$use_v2_request = true;
			}
			if ( 'get_surveys' === $endpoint ) {
				$final_endpoint = "wordpress/lc-plugin/surveys/{$leadconnector_location_id}?skip={$params->skip}&limit={$params->limit}&query=" . $params->query;
				$use_v2_request = true;
			}
			if ( 'get_quizzes' === $endpoint ) {
				$final_endpoint = "wordpress/lc-plugin/quizzes/{$leadconnector_location_id}?skip={$params->skip}&limit={$params->limit}&query=" . $params->query;
				$use_v2_request = true;
			}

			if ( 'clear_cached_custom_values' === $endpoint ) {
				$custom_values = new LeadConnector_CustomValues();
				$custom_values->remove_all_cached_custom_values_transients();
				return array(
					'success' => true,
					'message' => 'Cached custom values cleared successfully',
				);
			}

			if ( 'get_reviews_widgets' === $endpoint ) {
				$page_number    = leadconnector_api_prop( $params, 'pageNumber', 1 );
				$page_size      = leadconnector_api_prop( $params, 'pageSize', 10 );
				$final_endpoint = "wordpress/lc-plugin/reviews-widget/{$leadconnector_location_id}?pageNumber={$page_number}&pageSize={$page_size}";
				$use_v2_request = true;
			}

			if ( 'get_calendars' === $endpoint ) {
				$final_endpoint = "wordpress/lc-plugin/calendars/{$leadconnector_location_id}";
				$use_v2_request = true;
			}

			if ( 'get_calendar_groups' === $endpoint ) {
				$final_endpoint = "wordpress/lc-plugin/calendars/{$leadconnector_location_id}/groups";
				$use_v2_request = true;
			}

			/**
			 * "Purge everything on all domains": OAuth POST to LeadConnector services.
			 *
			 * Target path: `wordpress/lc-plugin/site/{locationId}/{wpId}/clear-cache`, where
			 * `{locationId}` is the connected sub-account ID and `{wpId}` is the site
			 * identifier for this install (`get_cdn_wp_id()`).
			 */
			if ( 'wp_purge_all_domains_cache' === $endpoint ) {
				$wp_id = $this->get_cdn_wp_id();
				if ( '' === $wp_id ) {
					return array(
						'error'   => true,
						'message' => __( 'Define CDN_WP_ID or CDN_SITE_ID in wp-config.php with the WordPress site identifier that matches your LeadConnector location configuration.', 'leadconnector' ),
					);
				}
				if ( '' === (string) $leadconnector_location_id ) {
					return array(
						'error'   => true,
						'message' => __( 'Connect LeadConnector and pick a location, then try again.', 'leadconnector' ),
					);
				}
				$final_endpoint      = sprintf(
					'wordpress/lc-plugin/site/%s/%s/clear-cache',
					rawurlencode( (string) $leadconnector_location_id ),
					rawurlencode( $wp_id )
				);
				$use_v2_post_request = true;
			}
		}

		if ( 'wp_purge_all_domains_cache' === $endpoint && 'oauth' !== $auth_method ) {
			return array(
				'error'   => true,
				'message' => __( 'OAuth connection required for CDN purge.', 'leadconnector' ),
			);
		}

		if ( '' === $final_endpoint ) {
			return array(
				'error'         => true,
				'message'       => 'Invalid Endpoint received',
				'endpoint'      => $endpoint,
				'finalEndpoint' => $final_endpoint,
				'auth'          => $auth_method,
			);
		}

		if ( 'oauth' === $auth_method && $use_v2_post_request ) {
				return $this->leadconnector_oauth_wp_remote_v2( 'post', $final_endpoint, array() );
		}

		if ( 'oauth' === $auth_method && ! $use_v2_request && ! $use_v2_post_request ) {
			return $this->leadconnector_oauth_wp_remote_get( $final_endpoint, $leadconnector_access_token, $base_host );
		}

		if ( 'oauth' === $auth_method && $use_v2_request ) {
			$response = $this->leadconnector_oauth_wp_remote_v2( 'get', $final_endpoint );

			// SECURITY (#C7): do not log the raw response. The body contains
			// CRM data (custom values, surveys, calendar entries) and, on
			// error paths, the decoded LeadConnector error envelope. Log a
			// structured summary instead so debug-enabled sites do not
			// accidentally write PII or secrets to wp-content log files.
			LeadConnector_Logger::get_instance()->info(
				'leadconnector_wp_get.v2',
				array(
					'endpoint'   => $endpoint,
					'http_code'  => is_array( $response ) && isset( $response['http_code'] ) ? (int) $response['http_code'] : null,
					'is_error'   => is_array( $response ) && ! empty( $response['error'] ),
					'item_count' => ( is_array( $response ) && isset( $response['body'] ) && is_object( $response['body'] ) && isset( $response['body']->data ) && is_array( $response['body']->data ) )
						? count( $response['body']->data )
						: null,
				)
			);

			if ( 'get_custom_values' === $endpoint ) {
				$this->leadconnector_save_custom_values( $response );
			}
			return $response;
		}

			return $this->leadconnector_wp_remote_get( $final_endpoint, $api_key, $base_host );
	}

	/**
	 * Encrypts a string using WordPress salt and key.
	 *
	 * Halts the request via wp_die() if the encryption layer is not ready
	 * (OpenSSL extension missing, or LOGGED_IN_KEY/LOGGED_IN_SALT not defined
	 * in wp-config.php). Refusing to silently fall back to plaintext was a
	 * 3.0.31 hardening (#A3): the previous implementation returned the
	 * unencrypted value when the OpenSSL extension was absent, which would
	 * have persisted plaintext OAuth tokens in wp_options.
	 *
	 * @since    1.0.0
	 * @param    string|object $input_string    String or object to encrypt.
	 * @return   string    Encrypted string.
	 */
	private function leadconnector_encrypt_string( $input_string ) {
		// Convert objects to JSON string before encryption.
		if ( is_object( $input_string ) || is_array( $input_string ) ) {
			$input_string = wp_json_encode( $input_string );
		}

		$encryption = new LeadConnector_Data_Encryption();
		if ( ! $encryption->is_ready() ) {
			$this->leadconnector_halt_on_missing_encryption_key();
		}

		$ciphertext = $encryption->encrypt( (string) $input_string );
		if ( false === $ciphertext ) {
			$this->leadconnector_halt_on_missing_encryption_key();
		}

		return $ciphertext;
	}

	/**
	 * Decrypts a string using WordPress salt and key.
	 *
	 * Returns an empty string when the input is empty or decryption fails so
	 * existing call sites that compare against '' continue to work. If the
	 * stored value is in the legacy AES-256-CTR format the decrypt call still
	 * succeeds (back-compat path inside LeadConnector_Data_Encryption).
	 *
	 * @since    1.0.0
	 * @param    string $encrypted    String to decrypt.
	 * @return   string    Decrypted string, or empty string if decryption is impossible.
	 */
	private function leadconnector_decrypt_string( $encrypted ) {
		if ( ! is_string( $encrypted ) || '' === $encrypted ) {
			return '';
		}

		$encryption = new LeadConnector_Data_Encryption();
		if ( ! $encryption->is_ready() ) {
			$this->leadconnector_halt_on_missing_encryption_key();
		}

		$plaintext = $encryption->decrypt( $encrypted );
		return false === $plaintext ? '' : $plaintext;
	}

	/**
	 * Abort the current request when the encryption layer cannot produce or
	 * verify ciphertext. Surfaces a translated, escaped admin-side error and
	 * never falls back to writing plaintext credentials (#A3).
	 *
	 * @return void
	 */
	private function leadconnector_halt_on_missing_encryption_key() {
		wp_die(
			esc_html__( 'LeadConnector: cannot encrypt or decrypt credentials. The OpenSSL PHP extension and the LOGGED_IN_KEY / LOGGED_IN_SALT constants in wp-config.php are required for the LeadConnector OAuth and SMTP features. Refusing to continue without authenticated encryption.', 'leadconnector' ),
			esc_html__( 'LeadConnector: encryption unavailable', 'leadconnector' ),
			array( 'response' => 500 )
		);
	}



	/**
	 * Perform an OAuth validate or refresh request against the LC services API.
	 *
	 * @param string      $endpoint   OAuth action: 'validate' or 'refresh'.
	 * @param string|null $code       Auth code or refresh token.
	 * @param string|null $location_id Location ID for refresh requests.
	 * @return object|array
	 */
	private function leadconnector_perform_oauth_request( $endpoint = 'validate', $code = null, $location_id = null ) {

		if ( null === $code || '' === $code ) {
			return array(
				'error'   => true,
				'message' => 'Invalid Refresh Token or Auth Code Not Received',
			);
		}

		$final_endpoint = 'wordpress/lc-plugin/oauth/' . $endpoint . '?lcVersion=' . LEAD_CONNECTOR_VERSION;

		$body = array();

		if ( 'validate' === $endpoint ) {
			$body['code'] = $code;
		} else {
			$body['refresh_token'] = $code;
		}

		if ( null !== $location_id && 'refresh' === $endpoint ) {
			$final_endpoint = 'wordpress/lc-plugin/oauth/location/:locationId/refresh?locationId=' . $location_id . '&lcVersion=' . LEAD_CONNECTOR_VERSION;
		}

		$args = array(
			'timeout' => 60,
			'body'    => $body,
			'headers' => array(
				'X-LC-Version' => LEAD_CONNECTOR_VERSION,
			),
		);

		// LEAD_CONNECTOR_SERVICES_BASE_URL.
		$response  = wp_remote_post( LEAD_CONNECTOR_SERVICES_BASE_URL . $final_endpoint, $args );
		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		if ( 200 === $http_code || 201 === $http_code ) {
			$obj = json_decode( $body, false );
			return $obj;
		} else {
			return (object) ( array(
				'error'     => true,
				'http_code' => $http_code,
				'body'      => $body,
			) );
		}
	}



	/**
	 * Perform an authenticated GET request to the LC API with automatic token refresh.
	 *
	 * @param string $endpoint                  API endpoint path.
	 * @param string $leadconnector_access_token OAuth access token.
	 * @param string $base_host                   Base URL for the services API.
	 * @return mixed
	 */
	private function leadconnector_oauth_wp_remote_get( $endpoint, $leadconnector_access_token, $base_host = LEAD_CONNECTOR_SERVICES_BASE_URL ) {
		$attempt                    = 1;
		$leadconnector_options      = get_option( LEAD_CONNECTOR_OPTION_NAME );
		$leadconnector_access_token = $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ];
		$leadconnector_access_token = $this->leadconnector_decrypt_string( $leadconnector_access_token );
		$http_code                  = 100;
		$token_regeneration_status  = null;
		$all_attempts               = array();
		while ( $attempt <= 2 ) {

			$args = array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $leadconnector_access_token,
					'Accept'        => 'application/json',
					'version'       => '2021-04-15',
					'X-LC-Version'  => LEAD_CONNECTOR_VERSION,
				),
			);

			if ( defined( 'LEADCONNECTOR_DEVELOPER_VERSION' ) && LEADCONNECTOR_DEVELOPER_VERSION ) {
				$args['headers']['developer_version'] = LEADCONNECTOR_DEVELOPER_VERSION;
			}

			$final_url = $base_host . $endpoint;
			if ( strpos( $final_url, '?' ) !== false ) {
				$final_url .= '&lcVersion=' . LEAD_CONNECTOR_VERSION;
			} else {
				$final_url .= '?lcVersion=' . LEAD_CONNECTOR_VERSION;
			}
			$response  = wp_remote_get( $base_host . $endpoint, $args );
			$http_code = wp_remote_retrieve_response_code( $response );
			$body      = wp_remote_retrieve_body( $response );

			array_push(
				$all_attempts,
				array(
					'http_code' => $http_code,
					'body'      => $body,
				)
			);

			if ( 200 === $http_code || 201 === $http_code ) {
				$obj = json_decode( $body, false );

				return $obj;
			} elseif ( 401 === (int) $http_code ) {
				$token_regeneration_status = $this->leadconnector_oauth_regenerate_token();
			} else {
				return (object) ( array(
					'error'     => true,
					'http_code' => $http_code,
					'body'      => $body,
					'from'      => 'oauth_wp_remote_get',
				) );
			}

			++$attempt;
		}

		return array(
			'error'                => true,
			'http_code'            => $http_code,
			'token_refresh_status' => $token_regeneration_status,
			'has_access_token'     => '' !== $leadconnector_access_token,
			'attempt'              => $attempt,
			'all_attempts'         => $all_attempts,
		);
	}

	/**
	 * Performs the actual HTTP request.
	 *
	 * @param string $method HTTP method (get or post).
	 * @param string $url    Full request URL.
	 * @param array  $args   wp_remote_* arguments.
	 * @return array|WP_Error
	 * @throws InvalidArgumentException If the method is unsupported.
	 */
	private function perform_request( string $method, string $url, array $args ) {
		switch ( $method ) {
			case 'get':
				return wp_remote_get( $url, $args );
			case 'post':
				return wp_remote_post( $url, $args );
			default:
				throw new InvalidArgumentException( 'Unsupported HTTP method' );
		}
	}

	/**
	 * Checks if response code indicates success.
	 *
	 * @param mixed $code HTTP response code.
	 * @return bool
	 */
	private function is_success_response( $code ): bool {
		if ( is_int( $code ) ) {
			return $code >= 200 && $code < 300;
		}
		return false;
	}

	/**
	 * Handles successful API response.
	 *
	 * @param array|WP_Error $response wp_remote response.
	 * @return object|null
	 */
	private function handle_success_response( $response ) {
		try {
			$body = wp_remote_retrieve_body( $response );
			return json_decode( $body, false );
		} catch ( Exception $e ) {
			return null;
		}
	}


	/**
	 * Perform an authenticated API request (v2) with automatic token refresh.
	 *
	 * @param string      $method      HTTP method (get or post).
	 * @param string      $endpoint    API endpoint path.
	 * @param array       $body        Request body for POST requests.
	 * @param string|null $access_token Optional explicit access token.
	 * @param string      $base_host    Base URL for the services API.
	 * @param bool        $use_auth     Whether to include authorization header.
	 * @return array
	 * @throws InvalidArgumentException If the HTTP method is unsupported.
	 */
	public function leadconnector_oauth_wp_remote_v2(
		string $method,
		string $endpoint,
		array $body = array(),
		?string $access_token = null,
		string $base_host = LEAD_CONNECTOR_SERVICES_BASE_URL,
		bool $use_auth = true
	) {

		if ( 'get' !== $method && 'post' !== $method ) {
			throw new InvalidArgumentException( 'Unsupported HTTP method' );
		}

		$max_attempts = 2;
		$attempt      = 1;
		$url          = $base_host . $endpoint;

		$url = add_query_arg( 'lcVersion', LEAD_CONNECTOR_VERSION, $url );

		// Get Lead Connector options.
		$lead_connector_options = get_option( LEAD_CONNECTOR_OPTION_NAME );

		// Validate options.
		if ( empty( $lead_connector_options ) ) {
			return array(
				'error'                => true,
				'http_code'            => 500,
				'token_refresh_status' => false,
				'attempt'              => $attempt,
				'message'              => 'Invalid Lead Connector options',
			);
		}

		$access_token = $access_token ? $access_token : $lead_connector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ];
		$access_token = $this->leadconnector_decrypt_string( $access_token ); // Decrypt the access token
		// Prepare request arguments.
		$args = array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => $use_auth ? 'Bearer ' . $access_token : null,
				'Accept'        => 'application/json',
				'version'       => '2021-04-15',
				'X-LC-Version'  => LEAD_CONNECTOR_VERSION,
				'channel'       => 'OAUTH',
				'content-type'  => 'application/json',
			),
		);

		if ( defined( 'LEADCONNECTOR_DEVELOPER_VERSION' ) && LEADCONNECTOR_DEVELOPER_VERSION ) {
			$args['headers']['developer_version'] = LEADCONNECTOR_DEVELOPER_VERSION;
		}

		// Add body for POST requests.
		if ( 'post' === $method ) {
			$args = array_merge( $args, array( 'body' => wp_json_encode( $body ) ) );
		}
		while ( $attempt <= $max_attempts ) {
			$response      = $this->perform_request( $method, $url, $args );
			$response_code = wp_remote_retrieve_response_code( $response );
			// Handle response based on status code.
			if ( $this->is_success_response( $response_code ) ) {
				$response = $this->handle_success_response( $response );
				return array(
					'error'     => false,
					'http_code' => $response_code,
					'body'      => $response,
				);
			}

			if ( 401 === $response_code ) {
				$this->leadconnector_oauth_regenerate_token();
				++$attempt;
				continue;
			}
			return array(
				'error'     => true,
				'http_code' => ! empty( $response_code ) ? $response_code : 500,
				'body'      => $response,
			);
		}

		return array(
			'error'     => true,
			'http_code' => $response_code ?? 500,
			'message'   => 'Max attempts reached',
		);
	}

	/**
	 * Perform a GET request to the LC base API using an API key.
	 *
	 * @param string $endpoint API endpoint path.
	 * @param string $api_key  Bearer API key.
	 * @param string $base_host Base URL.
	 * @return object|array
	 */
	private function leadconnector_wp_remote_get( $endpoint, $api_key, $base_host = LEAD_CONNECTOR_BASE_URL ) {

		// else call Remote API here.
		$args = array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
			),
		);

		if ( defined( 'LEADCONNECTOR_DEVELOPER_VERSION' ) && LEADCONNECTOR_DEVELOPER_VERSION ) {
			$args['headers']['developer_version'] = LEADCONNECTOR_DEVELOPER_VERSION;
		}

		$response = wp_remote_get( $base_host . $endpoint, $args );

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $http_code ) {
			$body = wp_remote_retrieve_body( $response );
			$obj  = json_decode( $body, false );
			return $obj;
		} else {
			return ( array(
				'error'     => true,
				'http_code' => $http_code,
				'from'      => 'wp_remote_get_api',
			) );
		}
	}


	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string $hook Current admin page hook suffix.
	 */
	public function enqueue_styles( $hook ) {
		if ( 'toplevel_page_leadconnector-plugin' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'LeadConnector', plugin_dir_url( __FILE__ ) . 'css/leadconnector-admin.css', array(), $this->version, 'all' );

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined LeadConnector_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The LeadConnector_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		$css_to_load        = plugin_dir_url( __FILE__ ) . 'app.css';
		$vendor_css_to_load = plugin_dir_url( __FILE__ ) . 'app2.css';

		wp_enqueue_style( 'leadconnector-vue-app', $css_to_load, array(), $this->version, 'all', false );
	}

	/**
	 * Enqueue leadconnector-admin.js and localized leadconnector_cdn_config (purge toolbar + REST/proxy URLs).
	 *
	 * Related: GET leadconnector_api/v1/wp-purge-all-domains-cache/authorization-status; is_wp_purge_all_domains_cache_authorized();
	 * leadconnector-admin.js refresh after wp_validate_oauth and event leadconnector:purge-toolbar-refresh.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_purge_everything_on_all_domains_script() {
		// Use the script file's modification time as the version so browsers load updates even when the plugin version string is unchanged.
		$leadconnector_admin_path = plugin_dir_path( __FILE__ ) . 'js/leadconnector-admin.js';
		$leadconnector_admin_ver  = ( file_exists( $leadconnector_admin_path ) ) ? (string) filemtime( $leadconnector_admin_path ) : $this->version;

		// Load on every admin screen so the CDN menu observer runs wherever the host injects the toolbar.
		wp_enqueue_script( 'leadconnector-admin', plugin_dir_url( __FILE__ ) . 'js/leadconnector-admin.js', array(), $leadconnector_admin_ver, false );

		wp_localize_script(
			'leadconnector-admin',
			'leadconnector_cdn_config',
			array(
				'siteId'                           => $this->get_cdn_wp_id(),
				'wpPurgeAllDomainsCacheAuthorized' => $this->is_wp_purge_all_domains_cache_authorized(),
				'wpPurgeAllDomainsCacheStatusUrl'  => wp_make_link_relative( get_rest_url( null, 'leadconnector_api/v1/wp-purge-all-domains-cache/authorization-status' ) ),
				'proxyUrl'                         => wp_make_link_relative( get_rest_url( null, 'leadconnector_api/v1/proxy' ) ),
				'restNonce'                        => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string $hook Current admin page hook suffix.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load the admin script if user is logged in to WordPress admin.
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// "Purge everything on all domains" toolbar: leadconnector-admin.js + localized leadconnector_cdn_config (proxy, nonce, authorization + status REST URL).
		$this->enqueue_purge_everything_on_all_domains_script();

		if ( 'toplevel_page_leadconnector-plugin' !== $hook ) {
			return;
		}

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in LeadConnector_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The LeadConnector_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		$js_to_load        = plugin_dir_url( __FILE__ ) . 'app.js';
		$vendor_js_to_load = plugin_dir_url( __FILE__ ) . 'chunk-vendors.js';

		$options             = get_option( LEAD_CONNECTOR_OPTION_NAME );
		$enabled_text_widget = 0;
		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] ) ) {
			$enabled_text_widget = absint( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] );
		}

		wp_localize_script(
			'leadconnector-admin',
			'leadconnector_admin_settings',
			array(
				'enable_text-widget' => $enabled_text_widget,
				'proxy_url'          => wp_make_link_relative( get_rest_url( null, 'leadconnector_api/v1/proxy' ) ),
				'nonce'              => wp_create_nonce( 'wp_rest' ),
			)
		);
		wp_localize_script(
			'leadconnector-admin',
			'leadconnector_config',
			array(
				'LEAD_CONNECTOR_VERSION'                => LEAD_CONNECTOR_VERSION,
				'LEAD_CONNECTOR_PLUGIN_NAME'            => LEAD_CONNECTOR_PLUGIN_NAME,
				'LEAD_CONNECTOR_SERVICES_BASE_URL'      => LEAD_CONNECTOR_SERVICES_BASE_URL,
				'LEAD_CONNECTOR_OPTION_NAME'            => LEAD_CONNECTOR_OPTION_NAME,
				'LEAD_CONNECTOR_CDN_BASE_URL'           => LEAD_CONNECTOR_CDN_BASE_URL,
				'LEAD_CONNECTOR_OAUTH_CLIENT_ID'        => LEAD_CONNECTOR_OAUTH_CLIENT_ID,
				'LEAD_CONNECTOR_BASE_URL'               => LEAD_CONNECTOR_BASE_URL,
				'LEAD_CONNECTOR_DISPLAY_NAME'           => LEAD_CONNECTOR_DISPLAY_NAME,
				'LEAD_CONNECTOR_ROOT_DOMAIN'            => LEADCONNECTOR_ROOT_DOMAIN,
				'LEAD_CONNECTOR_APP_BASE_URL'           => LEADCONNECTOR_APP_BASE_URL,
				'LEAD_CONNECTOR_SURVEY_WIDGET_BASE_URL' => LEADCONNECTOR_SURVEY_WIDGET_BASE_URL,
				'LEAD_CONNECTOR_QUIZ_WIDGET_BASE_URL'   => LEADCONNECTOR_QUIZ_WIDGET_BASE_URL,
				'LEAD_CONNECTOR_FORM_EMBED_SCRIPT_URL'  => LEADCONNECTOR_FORM_EMBED_SCRIPT_URL,
				'LEAD_CONNECTOR_REPUTATION_WIDGET_SCRIPT_URL' => LEADCONNECTOR_REPUTATION_WIDGET_SCRIPT_URL,
				'LEAD_CONNECTOR_REPUTATION_WIDGET_BASE_URL' => LEADCONNECTOR_REPUTATION_WIDGET_BASE_URL,
				'LEAD_CONNECTOR_OAUTH_CALLBACK_URL'     => LEAD_CONNECTOR_OAUTH_CALLBACK_URL,
			)
		);

		wp_enqueue_script( 'leadconnector-vue-js', $js_to_load, array(), $this->version, true );
	}

	/**
	 * Enqueue leadconnector-admin.js when front-end admin bar is visible.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_cdn_script_frontend() {
		// Only load on frontend when user is logged in and has admin bar visible.
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if admin bar is showing (WordPress shows admin bar on frontend for logged-in admins)
		// The CDN menu only appears when admin bar is visible.
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		// Same purge toolbar script as admin when CDN menu can appear on the front-end admin bar.
		$this->enqueue_purge_everything_on_all_domains_script();
	}

	/**
	 * Top level menu callback function
	 */

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function wporg_options_page() {
		// Add Menu Handler In Place of old handler.
		new LeadConnector_Menu_Handler();
	}
	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		register_setting(
			LEAD_CONNECTOR_OPTION_NAME,
			LEAD_CONNECTOR_OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'leadconnector_setting_validate' ),
			)
		);
	}

	/**
	 * REST permission for read-only LeadConnector admin routes.
	 *
	 * @return bool
	 */
	public function leadconnector_rest_read_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST permission for state-changing LeadConnector admin routes (CSRF nonce required).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 */
	public function leadconnector_rest_mutating_permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		$verified = wp_verify_nonce( $nonce, 'wp_rest' );

		return (bool) $verified;
	}

	/**
	 * Register all LeadConnector REST API routes.
	 *
	 * @return void
	 */
	public function admin_rest_api_init() {
		$leadconnector_rest_read_permission     = array( $this, 'leadconnector_rest_read_permission' );
		$leadconnector_rest_mutating_permission = array( $this, 'leadconnector_rest_mutating_permission' );

		$leadconnector_rest_proxy_base = array(
			'callback' => array( $this, 'leadconnector_public_api_proxy' ),
			'args'     => LeadConnector_REST_Input_Guard::proxy_query_args(),
		);

		register_rest_route(
			'leadconnector_api/v1',
			'/proxy',
			array_merge(
				$leadconnector_rest_proxy_base,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => $leadconnector_rest_read_permission,
				)
			)
		);
		register_rest_route(
			'leadconnector_api/v1',
			'proxy',
			array_merge(
				$leadconnector_rest_proxy_base,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $leadconnector_rest_mutating_permission,
				)
			)
		);
		// Toolbar auth snapshot { wpPurgeAllDomainsCacheAuthorized } for leadconnector-admin.js post-OAuth (no full reload).
		register_rest_route(
			'leadconnector_api/v1',
			'/wp-purge-all-domains-cache/authorization-status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'leadconnector_rest_wp_purge_all_domains_cache_authorization_status' ),
				'permission_callback' => $leadconnector_rest_read_permission,
			)
		);
		// The previous `/leadconnector_internal_api/v1/save_custom_values`
		// route was removed in 3.0.32. It existed solely as the target of a
		// plugin-internal `wp_remote_post()` loopback that forwarded
		// WordPress authentication cookies so the route ran as the
		// originating administrator. The WordPress.org review team flagged
		// that pattern under the "improper handling of authentication
		// cookies" guideline, so the loopback has been replaced with a
		// `wp_schedule_single_event()` hand-off to a same-process WP-Cron
		// handler (`leadconnector_handle_save_custom_values_event`) and the
		// public REST surface is no longer needed.
	}

	/**
	 * Public wrapper to trigger OAuth token regeneration.
	 *
	 * @return array
	 */
	public function refresh_oauth_token() {
		return $this->leadconnector_oauth_regenerate_token();
	}
	/**
	 * Register the LeadConnector funnel custom post type.
	 *
	 * @return void
	 */
	public function register_custom_post() {
		$labels = array(
			'name'          => _x( 'Funnels', 'lead connector funnels', 'leadconnector' ),
			'singular_name' => _x( 'Funnel', 'post type singular name', 'leadconnector' ),
			'add_new'       => _x( 'Add New', 'Funnel', 'leadconnector' ),
		);

		$args = array(
			'labels'          => $labels,
			'description'     => __( 'LeadConnector funnel pages imported into WordPress.', 'leadconnector' ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => false,
			'show_in_rest'    => false,
			'has_archive'     => false,
			'supports'        => array( 'title' ),
			'rewrite'         => array(
				'slug'       => 'funnels',
				'with_front' => false,
			),
			'capability_type' => 'page',
		);

		register_post_type( lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE, $args );

		add_filter( 'post_row_actions', array( $this, 'leadconnector_filter_funnel_row_actions' ), 10, 2 );
	}

	/**
	 * Hide the "Trash" row action on LeadConnector funnel posts in the admin list table.
	 *
	 * Replaces the previous unsupported `'hide_post_row_actions'` argument that was passed
	 * to register_post_type() and silently ignored. The proper extension point is the
	 * post_row_actions filter, so the row-action removal is now actually applied.
	 *
	 * @param array   $actions Existing row actions for the post.
	 * @param WP_Post $post    The post object.
	 * @return array
	 */
	public function leadconnector_filter_funnel_row_actions( $actions, $post ) {
		if ( isset( $post->post_type ) && lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE === $post->post_type ) {
			unset( $actions['trash'] );
		}
		return $actions;
	}

	/**
	 * Global HTML attributes that should be allowed on (almost) every tag
	 * in funnel content.
	 *
	 * Includes the standard global attributes plus the most common WAI-ARIA
	 * states/properties and the `data-*` namespace conventions that funnel
	 * builders (LeadConnector, page builders, analytics SDKs)
	 * emit. Both `data-*` and `aria-*` are inert by HTML spec, so allowing
	 * them is safe.
	 *
	 * @return array<string,bool>
	 */
	private function leadconnector_html_global_attributes() {
		return array(
			// Core global attributes.
			'class'                 => true,
			'id'                    => true,
			'style'                 => true,
			'title'                 => true,
			'role'                  => true,
			'lang'                  => true,
			'dir'                   => true,
			'tabindex'              => true,
			'hidden'                => true,
			'draggable'             => true,
			'contenteditable'       => true,
			'spellcheck'            => true,
			'translate'             => true,
			'is'                    => true,
			'slot'                  => true,
			'part'                  => true,

			// Common WAI-ARIA attributes (inert).
			'aria-label'            => true,
			'aria-labelledby'       => true,
			'aria-describedby'      => true,
			'aria-hidden'           => true,
			'aria-expanded'         => true,
			'aria-controls'         => true,
			'aria-haspopup'         => true,
			'aria-disabled'         => true,
			'aria-pressed'          => true,
			'aria-checked'          => true,
			'aria-selected'         => true,
			'aria-current'          => true,
			'aria-live'             => true,
			'aria-atomic'           => true,
			'aria-busy'             => true,
			'aria-relevant'         => true,
			'aria-required'         => true,
			'aria-invalid'          => true,
			'aria-readonly'         => true,
			'aria-multiline'        => true,
			'aria-multiselectable'  => true,
			'aria-orientation'      => true,
			'aria-valuemin'         => true,
			'aria-valuemax'         => true,
			'aria-valuenow'         => true,
			'aria-valuetext'        => true,
			'aria-keyshortcuts'     => true,
			'aria-roledescription'  => true,
			'aria-modal'            => true,
			'aria-placeholder'      => true,
			'aria-autocomplete'     => true,
			'aria-activedescendant' => true,
			'aria-owns'             => true,
			'aria-flowto'           => true,
			'aria-level'            => true,
			'aria-posinset'         => true,
			'aria-setsize'          => true,
			'aria-sort'             => true,

			// Common data-* attributes used by funnel builders and analytics.
			'data-id'               => true,
			'data-name'             => true,
			'data-type'             => true,
			'data-value'            => true,
			'data-target'           => true,
			'data-toggle'           => true,
			'data-action'           => true,
			'data-step'             => true,
			'data-form'             => true,
			'data-page'             => true,
			'data-product'          => true,
			'data-element'          => true,
			'data-content'          => true,
			'data-key'              => true,
			'data-src'              => true,
			'data-url'              => true,
			'data-href'             => true,
			'data-title'            => true,
			'data-label'            => true,
			'data-index'            => true,
			'data-position'         => true,
			'data-style'            => true,
			'data-class'            => true,
			'data-role'             => true,
			'data-state'            => true,
			'data-status'           => true,
			'data-track'            => true,
			'data-tracking'         => true,
			'data-event'            => true,
			'data-category'         => true,
			'data-funnel'           => true,
			'data-funnel-id'        => true,
			'data-step-id'          => true,
			'data-location-id'      => true,
			'data-component'        => true,
			'data-section'          => true,
			'data-block'            => true,
			'data-widget'           => true,
			'data-template'         => true,
			'data-variant'          => true,
			'data-aos'              => true,
			'data-aos-delay'        => true,
			'data-aos-duration'     => true,
			'data-bs-toggle'        => true,
			'data-bs-target'        => true,
			'data-bs-dismiss'       => true,
			'data-bs-slide'         => true,
			'data-bs-slide-to'      => true,
			'data-bs-ride'          => true,
			'data-controller'       => true,
			'data-bind'             => true,
			'data-ng-app'           => true,
			'data-ng-controller'    => true,
			'data-ng-model'         => true,
			'data-ng-click'         => true,
			'data-v-app'            => true,
			'data-vue-app'          => true,
			'data-react-root'       => true,
			'data-reactroot'        => true,
			'data-testid'           => true,
		);
	}

	/**
	 * Merge the global attribute map into every entry of an allowed-tag map.
	 *
	 * Existing per-tag attributes win over globals when the same key appears
	 * (so callers can still override with `false` to forbid a global on a
	 * specific tag).
	 *
	 * @param array<string,array<string,bool>> $allowed Per-tag allowed-attribute map.
	 * @return array<string,array<string,bool>>
	 */
	private function leadconnector_merge_global_html_attributes( $allowed ) {
		$globals = $this->leadconnector_html_global_attributes();
		foreach ( $allowed as $tag => $attrs ) {
			if ( ! is_array( $attrs ) ) {
				$attrs = array();
			}
			$allowed[ $tag ] = array_merge( $globals, $attrs );
		}
		return $allowed;
	}

	/**
	 * Extra HTML5 structural / interactive / embedded tags commonly used by
	 * funnel pages but missing from wp_kses_allowed_html('post').
	 *
	 * Tag-specific attributes only — global attributes are merged in
	 * separately by leadconnector_merge_global_html_attributes().
	 *
	 * @return array<string,array<string,bool>>
	 */
	private function leadconnector_html5_extra_tags() {
		return array(
			// Structural / sectioning.
			'header'     => array(),
			'footer'     => array(),
			'nav'        => array(),
			'main'       => array(),
			'section'    => array(),
			'article'    => array(),
			'aside'      => array(),
			'figure'     => array(),
			'figcaption' => array(),
			'address'    => array(),
			'hgroup'     => array(),

			// Text-level semantics.
			'time'       => array( 'datetime' => true ),
			'mark'       => array(),
			'ruby'       => array(),
			'rt'         => array(),
			'rp'         => array(),
			'bdi'        => array(),
			'bdo'        => array(),
			'wbr'        => array(),
			'data'       => array( 'value' => true ),
			'abbr'       => array(),
			'cite'       => array(),
			'kbd'        => array(),
			'samp'       => array(),
			'var'        => array(),
			'q'          => array( 'cite' => true ),
			'small'      => array(),
			'sub'        => array(),
			'sup'        => array(),
			'ins'        => array(
				'cite'     => true,
				'datetime' => true,
			),
			'del'        => array(
				'cite'     => true,
				'datetime' => true,
			),
			'u'          => array(),
			's'          => array(),
			'b'          => array(),
			'i'          => array(),

			// Interactive.
			'details'    => array( 'open' => true ),
			'summary'    => array(),
			'dialog'     => array( 'open' => true ),
			'menu'       => array(),

			// Tables (most are in 'post' but ensure full coverage).
			'col'        => array(
				'span'  => true,
				'width' => true,
			),
			'colgroup'   => array( 'span' => true ),
			'caption'    => array(),

			// Buttons / form interactivity (not always in 'post').
			'button'     => array(
				'type'           => true,
				'name'           => true,
				'value'          => true,
				'disabled'       => true,
				'autofocus'      => true,
				'form'           => true,
				'formaction'     => true,
				'formenctype'    => true,
				'formmethod'     => true,
				'formnovalidate' => true,
				'formtarget'     => true,
			),
			'label'      => array(
				'for'  => true,
				'form' => true,
			),
			'fieldset'   => array(
				'name'     => true,
				'form'     => true,
				'disabled' => true,
			),
			'legend'     => array(),
			'datalist'   => array(),
			'output'     => array(
				'name' => true,
				'for'  => true,
				'form' => true,
			),
			'progress'   => array(
				'value' => true,
				'max'   => true,
			),
			'meter'      => array(
				'value'   => true,
				'min'     => true,
				'max'     => true,
				'low'     => true,
				'high'    => true,
				'optimum' => true,
				'form'    => true,
			),

			// Embedded media.
			'canvas'     => array(
				'width'  => true,
				'height' => true,
			),
			'track'      => array(
				'kind'    => true,
				'src'     => true,
				'srclang' => true,
				'label'   => true,
				'default' => true,
			),
			'map'        => array( 'name' => true ),
			'area'       => array(
				'alt'      => true,
				'coords'   => true,
				'shape'    => true,
				'href'     => true,
				'target'   => true,
				'rel'      => true,
				'download' => true,
			),

			// Anchor — extend 'post' defaults to include modern attributes
			// commonly used by funnel CTAs.
			'a'          => array(
				'href'           => true,
				'target'         => true,
				'rel'            => true,
				'download'       => true,
				'hreflang'       => true,
				'type'           => true,
				'referrerpolicy' => true,
				'name'           => true,
				'ping'           => true,
			),

			// Image with full HTML5 attribute set.
			'img'        => array(
				'src'            => true,
				'srcset'         => true,
				'sizes'          => true,
				'alt'            => true,
				'width'          => true,
				'height'         => true,
				'loading'        => true,
				'decoding'       => true,
				'referrerpolicy' => true,
				'crossorigin'    => true,
				'usemap'         => true,
				'ismap'          => true,
				'fetchpriority'  => true,
			),
		);
	}

	/**
	 * Allowed-HTML map for funnel SEO/meta head content.
	 *
	 * Exposed publicly so callers can pass the array directly into wp_kses()
	 * at the echo site (the WordPress.org-recommended escaping pattern).
	 *
	 * @return array<string,array<string,bool>>
	 */
	public function funnel_html_allowed_tags() {
		$allowed = wp_kses_allowed_html( 'post' );
		// H5: <script> is intentionally NOT in this allowlist. Remote/admin-
		// saved HTML (tracking code, funnel head/body content, SEO meta) is
		// always run through extract_scripts_from_html() before reaching
		// wp_kses(); the extracted scripts are re-emitted via the
		// WordPress script API (wp_enqueue_script / wp_print_script_tag /
		// wp_get_script_tag), which is the WordPress.org-recommended
		// pattern for safely re-injecting JS that the plugin discovered in
		// remote HTML. Keeping <script> out of this allowlist ensures any
		// future caller that forgets to extract first cannot accidentally
		// pass remote scripts through kses verbatim.
		$extra = array(
			'meta'     => array(
				'name'       => true,
				'http-equiv' => true,
				'content'    => true,
				'property'   => true,
				'charset'    => true,
				'itemprop'   => true,
			),
			'link'     => array(
				'href'        => true,
				'rel'         => true,
				'type'        => true,
				'media'       => true,
				'as'          => true,
				'crossorigin' => true,
				'integrity'   => true,
			),
			'style'    => array(
				'type'  => true,
				'media' => true,
			),
			'title'    => array(),
			'noscript' => array(),
		);

		foreach ( $extra as $tag => $attrs ) {
			$allowed[ $tag ] = isset( $allowed[ $tag ] ) ? array_merge( $allowed[ $tag ], $attrs ) : $attrs;
		}

		// Merge HTML5 structural / interactive / embedded tags missing from 'post'.
		foreach ( $this->leadconnector_html5_extra_tags() as $tag => $attrs ) {
			$allowed[ $tag ] = isset( $allowed[ $tag ] ) ? array_merge( $allowed[ $tag ], $attrs ) : $attrs;
		}

		// Apply the global attribute set (class/id/style/role/lang/data-*/aria-*) to every tag.
		return $this->leadconnector_merge_global_html_attributes( $allowed );
	}


	/**
	 * Build HTML meta tags from funnel step meta data.
	 *
	 * @param string $leadconnector_post_meta JSON-encoded post meta.
	 * @return string
	 */
	public function get_meta_fields( $leadconnector_post_meta ) {
		$default_meta_fields = '
        <meta  charset="utf-8" />
        <meta
            name="viewport"
            content="width=device-width, user-scalable=no"
        />
        <meta
            name="description"
            content="description"
        />
        <meta  property="og:type" content="website" />
        <meta
            property="twitter:type"
            content="website"
        />
        <meta property="robots" content="noindex" />
        ';
		try {

			if ( isset( $leadconnector_post_meta ) ) {

				$leadconnector_post_meta = json_decode( $leadconnector_post_meta );
				if ( isset( $leadconnector_post_meta->title ) && '' !== $leadconnector_post_meta->title ) {
					$default_meta_fields .= '<title>' . esc_html( $leadconnector_post_meta->title ) . '</title>'
						. '<meta property="og:title" content="' . esc_attr( $leadconnector_post_meta->title ) . '" />';
				}
				if ( isset( $leadconnector_post_meta->description ) && '' !== $leadconnector_post_meta->description ) {
					$default_meta_fields .= '<meta property="description" content="' . esc_attr( $leadconnector_post_meta->description ) . '"/>'
						. '<meta property="og:description" content="' . esc_attr( $leadconnector_post_meta->description ) . '" />';
				}

				if ( isset( $leadconnector_post_meta->author ) && '' !== $leadconnector_post_meta->author ) {
					$default_meta_fields .= '<meta property="author" content="' . esc_attr( $leadconnector_post_meta->author ) . '"/>'
						. '<meta property="og:author" content="' . esc_attr( $leadconnector_post_meta->author ) . '" />';
				}

				$image_url = leadconnector_api_prop( $leadconnector_post_meta, 'imageUrl' );
				if ( null !== $image_url && '' !== $image_url ) {
					$default_meta_fields .= '<meta property="image" content="' . esc_url( $image_url ) . '"/>'
						. '<meta property="og:image" content="' . esc_url( $image_url ) . '" />';
				}
				if ( isset( $leadconnector_post_meta->keywords ) && '' !== $leadconnector_post_meta->keywords ) {
					$default_meta_fields .= '<meta property="keywords" content="' . esc_attr( $leadconnector_post_meta->keywords ) . '"/>'
						. '<meta property="og:keywords" content="' . esc_attr( $leadconnector_post_meta->keywords ) . '" />';
				}
				$custom_meta_list = leadconnector_api_prop( $leadconnector_post_meta, 'customMeta', array() );
				if ( is_array( $custom_meta_list ) && count( $custom_meta_list ) > 0 ) {
					foreach ( $custom_meta_list as $custom_meta ) {
						if ( isset( $custom_meta ) &&
							isset( $custom_meta->name ) && '' !== $custom_meta->name &&
							isset( $custom_meta->content ) && '' !== $custom_meta->content
						) {
							$default_meta_fields .= '<meta property="' . esc_attr( $custom_meta->name ) . '" content="' . esc_attr( $custom_meta->content ) . '"/>';
						}
					}
				}
			}
		} catch ( Exception $e ) {
			LeadConnector_Logger::get_instance()->warning( 'Meta field parsing failed: ' . $e->getMessage() );
		}
		return wp_kses( $default_meta_fields, $this->funnel_html_allowed_tags() );
	}

	/**
	 * Extract header or footer tracking code from encoded tracking data.
	 *
	 * @param string $leadconnector_post_tracking_code Encoded tracking code data.
	 * @param bool   $is_header                        Whether to return header code.
	 * @param bool   $is_funnel                        Whether this is funnel-level tracking code.
	 * @return string
	 */
	public function get_tracking_code( $leadconnector_post_tracking_code, $is_header, $is_funnel = false ) {
		$default_tracking_code = ' ';
		try {
			if ( isset( $leadconnector_post_tracking_code ) ) {
				if ( $is_funnel ) {
					$leadconnector_post_tracking_code = json_decode( $leadconnector_post_tracking_code );
				} else {
					$leadconnector_post_tracking_code = leadconnector_decode_stored_json( $leadconnector_post_tracking_code );
				}

				$header_code = leadconnector_api_prop( $leadconnector_post_tracking_code, 'headerCode' );
				if ( $is_header && null !== $header_code && '' !== $header_code ) {
					$default_tracking_code = $header_code;
				}
				$footer_code = leadconnector_api_prop( $leadconnector_post_tracking_code, 'footerCode' );
				if ( ! $is_header && null !== $footer_code && '' !== $footer_code ) {
					$default_tracking_code = $footer_code;
				}
				if ( $is_funnel ) {
					$default_tracking_code = leadconnector_decode_api_base64_payload( $default_tracking_code );
				}
			}
		} catch ( Exception $e ) {
			LeadConnector_Logger::get_instance()->warning( 'Tracking code decoding failed: ' . $e->getMessage() );
		}
		return $default_tracking_code;
	}

	/**
	 * Generate full iframe page HTML for a funnel step.
	 *
	 * @param string $funnel_step_url                     Funnel step URL to embed.
	 * @param string $leadconnector_post_meta              JSON-encoded post meta.
	 * @param string $leadconnector_step_tracking_code     Step-level tracking code.
	 * @param string $leadconnector_funnel_tracking_code   Funnel-level tracking code.
	 * @param bool   $leadconnector_use_site_favicon       Whether to include site favicon.
	 * @return string
	 */
	public function get_page_iframe( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon ) {
		$iframe_page_allowed = $this->funnel_html_allowed_tags();

		$post_meta_fields = wp_kses( $this->get_meta_fields( $leadconnector_post_meta ), $iframe_page_allowed );

		// Build header tracking HTML: extract <script> tags from the raw
		// tracking source, kses-escape the surrounding HTML (no <script> in
		// the allowlist per H5), then append script tags rendered via the
		// WordPress core helper wp_get_script_tag()/wp_get_inline_script_tag()
		// — which apply attribute escaping and route through the
		// wp_inline_script_attributes filter without ever passing the JS
		// content through wp_kses().
		$head_tracking_raw   = $this->get_tracking_code( $leadconnector_funnel_tracking_code, true, true );
		$head_tracking_raw  .= $this->get_tracking_code( $leadconnector_step_tracking_code, true );
		$head_tracking_parts = $this->extract_scripts_from_html( $head_tracking_raw );
		$head_tracking_code  = wp_kses( $head_tracking_parts['html'], $iframe_page_allowed )
			. $this->render_extracted_scripts_html( $head_tracking_parts['scripts'] );

		$footer_tracking_raw   = $this->get_tracking_code( $leadconnector_funnel_tracking_code, false, true );
		$footer_tracking_raw  .= $this->get_tracking_code( $leadconnector_step_tracking_code, false );
		$footer_tracking_parts = $this->extract_scripts_from_html( $footer_tracking_raw );
		$footer_tracking_code  = wp_kses( $footer_tracking_parts['html'], $iframe_page_allowed )
			. $this->render_extracted_scripts_html( $footer_tracking_parts['scripts'] );

		$favicon_code = '';
		if ( $leadconnector_use_site_favicon ) {
			$favicon_url  = get_site_icon_url();
			$link_allowed = array(
				'link' => array(
					'rel'  => true,
					'type' => true,
					'href' => true,
				),
			);
			$favicon_code = wp_kses( '<link rel="icon" type="image/x-icon" href="' . esc_url( $favicon_url ) . '">', $link_allowed );
		}

		// External stylesheet — avoids inline <style> in the iframe document so
		// the styles can be cached and reviewed by the browser independently.
		$iframe_style_handle  = 'leadconnector-funnel-iframe';
		$iframe_style_url     = plugin_dir_url( __DIR__ ) . 'public/css/leadconnector-funnel-iframe.css';
		$iframe_style_version = defined( 'LEAD_CONNECTOR_VERSION' ) ? LEAD_CONNECTOR_VERSION : $this->version;

		if ( ! wp_style_is( $iframe_style_handle, 'registered' ) ) {
			wp_register_style( $iframe_style_handle, $iframe_style_url, array(), $iframe_style_version );
		}

		ob_start();
		wp_styles()->do_item( $iframe_style_handle );
		$iframe_styles = trim( (string) ob_get_clean() );

		$iframe_html = '<!DOCTYPE html>
		<head>
            ' . $favicon_code . '
            ' . $post_meta_fields . '
            ' . $head_tracking_code . '
            ' . $iframe_styles . '
                <meta name="viewport" content="width=device-width, initial-scale=1">
		</head>
		<body>
                <iframe width="100%" height="100%" src="' . esc_url( $funnel_step_url ) . '" frameborder="0" allowfullscreen></iframe>
                ' . $footer_tracking_code . '
		</body>
        </html>';

		// Escape late: pass the fully-assembled HTML document through
		// wp_kses() one final time so the function itself returns safe output
		// (escape at the boundary). Every interpolated value above is already
		// individually escaped/kses'd, but a final pass guarantees safety even
		// if a future caller forgets to wrap the return value.
		return wp_kses( $iframe_html, $this->iframe_page_allowed_html() );
	}

	/**
	 * Allowed-HTML map for the standalone iframe page returned by
	 * get_page_iframe().
	 *
	 * SECURITY NOTE — TRUST BOUNDARY
	 * ------------------------------
	 * This allowlist intentionally permits <script> tags. Unlike
	 * funnel_html_allowed_tags() (which is the content-level allowlist used
	 * for raw remote/admin-saved HTML and which therefore strips scripts per
	 * H5 of the WP.org review), this allowlist is applied only as the final
	 * "escape-late" wp_kses() pass over a fully-assembled HTML document
	 * built inside get_page_iframe(). Every <script> tag that reaches this
	 * pass has already been produced by one of two trusted sources:
	 *
	 *   1. WordPress core helpers wp_get_script_tag() / wp_get_inline_script_tag()
	 *      called from render_extracted_scripts_html(), which apply
	 *      attribute escaping (esc_url for src, esc_attr for the rest) and
	 *      route through the wp_inline_script_attributes filter the same way
	 *      wp_print_script_tag() does. No raw remote HTML reaches that
	 *      helper — the input has been pre-extracted from an admin-saved
	 *      tracking-code string.
	 *   2. Tracking-code HTML that has been individually pre-kses'd through
	 *      funnel_html_allowed_tags() *before* concatenation, which by H5
	 *      definition cannot pass <script> through.
	 *
	 * In other words, this allowlist is a document-shape definition, not a
	 * sanitizer for remote HTML. Removing <script> here would only cause the
	 * final boundary kses to strip the WP-core-rendered script HTML we just
	 * built, with no security gain.
	 *
	 * Mirrors the tag/attribute set used by the upstream caller so that
	 * escaping the document at the function boundary is equivalent to (and
	 * safely re-escapable by) the caller's wp_kses() wrap.
	 *
	 * @return array
	 */
	private function iframe_page_allowed_html() {
		return array(
			'head'     => array(),
			'body'     => array(),
			'html'     => array(),
			'title'    => array(),
			'script'   => array(
				'src'   => true,
				'type'  => true,
				'async' => true,
				'defer' => true,
			),
			'noscript' => array(),
			'iframe'   => array(
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'frameborder'     => true,
				'allowfullscreen' => true,
			),
			'meta'     => array(
				'charset'  => true,
				'property' => true,
				'content'  => true,
				'name'     => true,
			),
			'link'     => array(
				'rel'   => true,
				'type'  => true,
				'href'  => true,
				'id'    => true,
				'media' => true,
			),
		);
	}

	/**
	 * Fetch and return native HTML content for a funnel step page.
	 *
	 * @param string $funnel_step_url Funnel step URL to fetch.
	 * @param string $unused_post_meta Reserved for API compatibility.
	 * @param string $unused_step_tracking_code Reserved for API compatibility.
	 * @param string $unused_funnel_tracking_code Reserved for API compatibility.
	 * @param bool   $unused_use_site_favicon Reserved for API compatibility.
	 * @return string
	 */
	public function get_page_iframe_native( $funnel_step_url, $unused_post_meta = '', $unused_step_tracking_code = '', $unused_funnel_tracking_code = '', $unused_use_site_favicon = false ) {
		unset( $unused_post_meta, $unused_step_tracking_code, $unused_funnel_tracking_code, $unused_use_site_favicon );

		// Resolve the canonical LeadConnector app host from configuration.
		$canonical_host = '';
		if ( defined( 'LEADCONNECTOR_APP_BASE_URL' ) ) {
			$canonical_host = wp_parse_url( LEADCONNECTOR_APP_BASE_URL, PHP_URL_HOST );
		}
		if ( ! is_string( $canonical_host ) || '' === $canonical_host ) {
			$canonical_host = 'app.leadconnectorhq.com';
		}

		$funnel_host = wp_parse_url( $funnel_step_url, PHP_URL_HOST );

		// Treat any host other than the canonical app host as a white-label /
		// custom domain and rewrite the request to the canonical host so the
		// HTTP fetch reaches an origin that actually serves funnel previews.
		// The funnel slug in the path is preserved by the str_replace().
		if ( is_string( $funnel_host ) && '' !== $funnel_host && $funnel_host !== $canonical_host ) {
			$funnel_step_url = str_replace( $funnel_host, $canonical_host, $funnel_step_url );
			$funnel_host     = $canonical_host;
		}

		// Final allowlist check: refuse to fetch any URL whose host does not
		// match the canonical LeadConnector app host. This bounds the
		// downstream wp_remote_get() to an origin the admin already configured
		// and prevents arbitrary URL fetches even if the stored meta were
		// tampered with.
		if ( $funnel_host !== $canonical_host ) {
			return sprintf(
				'Error: Invalid funnel step URL domain. Expected %s but got %s',
				esc_html( $canonical_host ),
				esc_html( (string) $funnel_host )
			);
		}

		// Make request with increased timeout.
		$response       = wp_remote_get(
			$funnel_step_url,
			array(
				'timeout'   => 30,
				'sslverify' => true,
			)
		);
		$iframe_content = '';

		if ( is_wp_error( $response ) ) {
			$logger = LeadConnector_Logger::get_instance();
			$logger->error( 'Native Page Error: ' . $response->get_error_message(), array( 'url' => $funnel_step_url ) );
			return sprintf(
				'<!-- Error fetching content: %s from URL: %s -->',
				esc_html( $response->get_error_message() ),
				esc_html( $funnel_step_url )
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$logger = LeadConnector_Logger::get_instance();
			$logger->error( 'Native Page Error: HTTP ' . $response_code, array( 'url' => $funnel_step_url ) );
			return sprintf(
				'<!-- Error: Received HTTP %d from URL: %s -->',
				intval( $response_code ),
				esc_html( $funnel_step_url )
			);
		}

		$iframe_content = wp_remote_retrieve_body( $response );

		if ( empty( $iframe_content ) ) {
			$logger = LeadConnector_Logger::get_instance();
			$logger->error( 'Native Page Error: Empty response body', array( 'url' => $funnel_step_url ) );
			return '<!-- Error: Empty response received from funnel URL -->';
		}

		return $iframe_content;
	}

	/**
	 * Option key holding the list of post IDs whose display_method is "native".
	 *
	 * Maintained incrementally by sync_native_funnel_index_on_save() and
	 * sync_native_funnel_index_on_delete() so that
	 * site_has_native_display_funnels() can answer in O(1) without issuing a
	 * meta_query (which Plugin Check / WordPress.DB.SlowDBQuery flags as a
	 * potential slow query at scale).
	 */
	private const LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION = 'leadconnector_native_funnel_index';

	/**
	 * Whether this site has at least one funnel published in native HTML mode.
	 *
	 * Reads a small bookkeeping option that is kept in sync by save_post and
	 * before_delete_post hooks (see sync_native_funnel_index_on_save() and
	 * sync_native_funnel_index_on_delete()). The option stores an array of
	 * post IDs whose `leadconnector_display_method` post-meta is "native";
	 * checking emptiness of that list answers the question without ever
	 * touching post_meta in a query.
	 *
	 * On a freshly upgraded site the option may not yet exist (the index has
	 * not been populated). In that case we bootstrap the index once from post
	 * meta so the answer is accurate without showing a false-positive notice.
	 *
	 * @return bool
	 */
	private function site_has_native_display_funnels() {
		static $leadconnector_has_native_funnels = null;

		if ( null !== $leadconnector_has_native_funnels ) {
			return $leadconnector_has_native_funnels;
		}

		$index = get_option( self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION, null );

		if ( null === $index ) {
			$index = $this->bootstrap_native_funnel_index();
		}

		// 3.0.32: also re-bootstrap when the stored index is empty.
		// `sync_native_funnel_index_on_save` runs at `save_post` priority
		// 20, but the LeadConnector editor's REST handler updates the
		// `leadconnector_display_method` post-meta AFTER `wp_insert_post`
		// has already fired `save_post`, so the sync hook sees an empty
		// display method and never writes anything to the index for
		// freshly imported native funnels. An empty index option is
		// therefore an ambiguous signal — it could legitimately mean
		// "no native funnels", or it could mean "the index was created
		// in a save_post pass that ran before the meta was written".
		// We disambiguate by re-running the postmeta JOIN once per
		// 5-minute window via a transient guard; if there really are no
		// native funnels the query is essentially free.
		if ( is_array( $index ) && empty( $index ) ) {
			$throttle_key = self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION . '_recently_bootstrapped';
			if ( false === get_transient( $throttle_key ) ) {
				$index = $this->bootstrap_native_funnel_index();
				set_transient( $throttle_key, 1, 5 * MINUTE_IN_SECONDS );
			}
		}

		// 3.0.32: when the index is non-empty, prune any IDs that no
		// longer refer to currently-published native funnel posts.
		// Required because `wp_trash_post()` does not fire
		// `save_post` or `before_delete_post`, and a publish→draft
		// status transition handled purely by core would otherwise
		// leave stale IDs in the index — both cases would cause the
		// native-mode trust notice to fire on sites that have no
		// live native funnels (matching what was reported in 3.0.32
		// QA: notice visible with an empty Funnels list).
		if ( is_array( $index ) && ! empty( $index ) ) {
			$index = $this->revalidate_native_funnel_index( $index );
		}

		$leadconnector_has_native_funnels = is_array( $index ) && ! empty( $index );
		return $leadconnector_has_native_funnels;
	}

	/**
	 * Re-sync the native-funnel index when a funnel post's
	 * `leadconnector_display_method` post-meta changes.
	 *
	 * The LeadConnector editor REST handler writes the display-method
	 * post-meta AFTER `wp_insert_post` / `wp_update_post` has already
	 * fired the `save_post` action, so `sync_native_funnel_index_on_save()`
	 * always sees the previous (or empty) display method on a brand-new
	 * funnel. Listening on `added_post_meta` / `updated_post_meta` /
	 * `deleted_post_meta` for the specific meta key picks up the change
	 * at the moment it lands on disk.
	 *
	 * @since 3.0.32
	 * @param int    $meta_id    Meta ID (unused).
	 * @param int    $post_id    Post ID whose meta is changing.
	 * @param string $meta_key   Meta key being changed.
	 * @param mixed  $meta_value New meta value.
	 * @return void
	 */
	public function sync_native_funnel_index_on_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id );

		if ( 'leadconnector_display_method' !== $meta_key ) {
			return;
		}

		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post
			|| lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE !== $post->post_type
		) {
			return;
		}

		$post_id = (int) $post->ID;
		// 3.0.32: only index posts that are currently published. A native
		// display method on a draft / private / trash funnel does not
		// represent a live trust extension on the WP origin, so it should
		// not flip the admin notice on.
		$is_native = ( 'native' === $meta_value ) && ( 'publish' === $post->post_status );
		$this->upsert_native_funnel_index_entry( $post_id, $is_native );
	}

	/**
	 * Insert or remove a single ID in the native-funnel index.
	 *
	 * Shared by all three sync callbacks (save_post, post-meta change,
	 * status transition) so they cannot drift in subtle ways.
	 *
	 * @since 3.0.32
	 * @param int  $post_id   Post ID.
	 * @param bool $is_native TRUE to ensure the ID is in the index, FALSE to ensure it is not.
	 * @return void
	 */
	private function upsert_native_funnel_index_entry( $post_id, $is_native ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		$index = get_option( self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		$is_indexed = in_array( $post_id, $index, true );

		if ( $is_native && ! $is_indexed ) {
			$index[] = $post_id;
			update_option( self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION, $index, false );
			return;
		}

		if ( ! $is_native && $is_indexed ) {
			$index = array_values(
				array_filter(
					$index,
					static function ( $candidate ) use ( $post_id ) {
						return (int) $candidate !== $post_id;
					}
				)
			);
			update_option( self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION, $index, false );
		}
	}

	/**
	 * Re-sync the native-funnel index when a funnel post's publish
	 * status transitions.
	 *
	 * Needed because:
	 *
	 *  - `wp_trash_post()` transitions a post to status `trash` without
	 *    firing `save_post` or `before_delete_post`, so the index would
	 *    otherwise keep stale IDs after a trash op.
	 *  - A publish → draft / private / pending transition performed
	 *    directly via `wp_update_post` does fire `save_post`, but a
	 *    Quick Edit "status" change does not always rewrite the
	 *    display-method post-meta, so the `_on_meta_change` callback
	 *    doesn't run either.
	 *
	 * Listening on `transition_post_status` catches both cases.
	 *
	 * @since 3.0.32
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public function sync_native_funnel_index_on_status_change( $new_status, $old_status, $post ) {
		unset( $old_status );

		if ( ! $post instanceof WP_Post
			|| lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE !== $post->post_type
		) {
			return;
		}

		$display_method = get_post_meta( (int) $post->ID, 'leadconnector_display_method', true );
		$is_native      = ( 'native' === $display_method ) && ( 'publish' === $new_status );
		$this->upsert_native_funnel_index_entry( (int) $post->ID, $is_native );
	}

	/**
	 * Build the native-funnel index option on first read (cold cache / pre-3.0.30 sites).
	 *
	 * Uses a single prepared SQL JOIN against $wpdb->posts and $wpdb->postmeta
	 * instead of get_posts() with a meta_query argument so the call does not
	 * trigger WordPress.DB.SlowDBQuery.slow_db_query_meta_query in Plugin
	 * Check. This is a one-time bootstrap on legacy installs; subsequent
	 * accuracy is maintained by sync_native_funnel_index_on_save() and
	 * sync_native_funnel_index_on_delete(), so this query runs at most once
	 * per site over the lifetime of the option (the result is persisted to
	 * the leadconnector_native_funnel_index option immediately below).
	 *
	 * @return array<int,int>
	 */
	private function bootstrap_native_funnel_index() {
		global $wpdb;

		// 3.0.32: restrict to `publish` status only. The native-mode trust
		// notice describes a live, customer-facing rendering surface, so
		// drafts / private / trashed funnel posts should not flip it on.
		// This also keeps the index aligned with what the LC funnel-pages
		// admin table shows (publish-only).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$native_funnel_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value = %s",
				lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE,
				'leadconnector_display_method',
				'native'
			)
		);

		$index = is_array( $native_funnel_ids ) ? array_map( 'intval', $native_funnel_ids ) : array();
		update_option( self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION, $index, false );

		return $index;
	}

	/**
	 * Prune stale IDs from the native-funnel index.
	 *
	 * Given a non-empty index, issues one prepared SELECT to find which
	 * IDs still refer to currently-published LC funnel posts whose
	 * `leadconnector_display_method` post-meta is "native", and rewrites
	 * the index option to that pruned set. Throttled by a 5-minute
	 * transient so the query runs at most once per window per request
	 * thread, regardless of how many admin pages get loaded.
	 *
	 * Without this scrub an index entry can survive a `wp_trash_post()`
	 * (which transitions the post status to `trash` without firing
	 * `before_delete_post`) or a publish→draft transition that the
	 * editor performs without rewriting the meta. The
	 * `transition_post_status` hook handles the going-forward case;
	 * this method handles existing drift on already-installed sites.
	 *
	 * @since 3.0.32
	 * @param array<int,int> $index Current cached index.
	 * @return array<int,int> Possibly-pruned index.
	 */
	private function revalidate_native_funnel_index( array $index ) {
		if ( empty( $index ) ) {
			return $index;
		}

		$throttle_key = self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION . '_recently_revalidated';
		if ( false !== get_transient( $throttle_key ) ) {
			return $index;
		}

		global $wpdb;

		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $index ),
					static function ( $id ) {
						return $id > 0;
					}
				)
			)
		);

		if ( empty( $ids ) ) {
			update_option( self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION, array(), false );
			set_transient( $throttle_key, 1, 5 * MINUTE_IN_SECONDS );
			return array();
		}

		// Build the IN-clause by inline-casting every ID through absint()
		// rather than passing them as %d placeholders. There is no
		// untrusted input path into $ids — they originate from a
		// non-autoloaded option this class itself wrote, are re-cast
		// through array_map( 'intval' ) + array_filter( id > 0 )
		// immediately above, and are absint()-cast again at the
		// interpolation site below.
		$id_list = implode( ',', array_map( 'absint', $ids ) );

		// The phpcs:disable/enable pair below is required because the
		// WordPress.DB.PreparedSQL.InterpolatedNotPrepared sniff reports
		// on the interior heredoc line that contains {$id_list}, so a
		// single phpcs:ignore comment on the wrapping $wpdb->get_col()
		// statement is not sufficient. The three string args still use
		// real %s placeholders and are passed through $wpdb->prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$valid_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.ID IN ( {$id_list} )
				AND p.post_type = %s
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value = %s",
				lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE,
				'leadconnector_display_method',
				'native'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$pruned = is_array( $valid_ids ) ? array_values( array_map( 'intval', $valid_ids ) ) : array();

		if ( $pruned !== $ids ) {
			update_option( self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION, $pruned, false );
		}

		set_transient( $throttle_key, 1, 5 * MINUTE_IN_SECONDS );

		return $pruned;
	}

	/**
	 * Whether the site has an active LeadConnector connection (API key or OAuth).
	 *
	 * @return bool
	 */
	private function is_leadconnector_site_connected() {
		$leadconnector_options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		if ( empty( $leadconnector_options ) || ! is_array( $leadconnector_options ) ) {
			return false;
		}

		$leadconnector_location_id = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] )
			? trim( (string) $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] )
			: '';
		if ( '' === $leadconnector_location_id || 'leadconnector_disconnect' === $leadconnector_location_id ) {
			return false;
		}

		$api_key = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] )
			? trim( (string) $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] )
			: '';
		if ( '' !== $api_key ) {
			return true;
		}

		$leadconnector_access_token = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ] )
			? $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_OAUTH_ACCESS_TOKEN ]
			: '';
		$leadconnector_access_token = $this->leadconnector_decrypt_string( $leadconnector_access_token );

		return '' !== $leadconnector_access_token;
	}

	/**
	 * Whether this site has opted in to rendering funnel posts on the
	 * WordPress origin ("native" mode).
	 *
	 * Backed by the `leadconnector_native_mode_allowed` key inside the
	 * plugin's main options blob, which is exposed in the admin UI as a
	 * toggle and documented in the README's "Native funnel rendering —
	 * trust boundary" section. Defaults to TRUE when the key is unset so
	 * that an upgrade from <= 3.0.31 does not silently break funnels
	 * that take payments (Stripe / PayPal / Apple Pay refuse to run
	 * inside an `<iframe>`, so the native path is the only viable
	 * display path for them).
	 *
	 * Site owners who do not run payment funnels can flip the toggle to
	 * disable native rendering site-wide — `process_page_request()`
	 * will then transparently downgrade every `'native'` funnel post to
	 * the iframe path so no remote LeadConnector JavaScript ever
	 * executes on the WP origin.
	 *
	 * The result is also filterable so an mu-plugin / theme can
	 * force-disable native mode without flipping the admin option (e.g.
	 * to enforce a security baseline across an agency network of sites).
	 *
	 * @since 3.0.32
	 * @return bool TRUE when native mode is allowed for the current request.
	 */
	public function is_native_funnel_rendering_allowed() {
		$leadconnector_options = get_option( LEAD_CONNECTOR_OPTION_NAME );

		// Default-on: the key is unset on installs upgrading from <= 3.0.31,
		// and flipping the default to OFF would break any site that already
		// has a payment funnel relying on native mode. Sites that want the
		// safer default can either flip the toggle or short-circuit via the
		// filter below.
		$allowed = true;

		if ( is_array( $leadconnector_options )
			&& isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_NATIVE_MODE_ALLOWED ] )
		) {
			$raw = $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_NATIVE_MODE_ALLOWED ];
			if ( is_string( $raw ) ) {
				$raw     = strtolower( trim( $raw ) );
				$allowed = in_array( $raw, array( '1', 'true', 'yes', 'on' ), true );
			} else {
				$allowed = (bool) $raw;
			}
		}

		/**
		 * Filters whether native funnel rendering is allowed on this site.
		 *
		 * Return FALSE to force every funnel post that has its
		 * `leadconnector_display_method` post-meta set to `native` to be
		 * silently downgraded to iframe rendering. This is the recommended
		 * lever for agency / multisite operators who want to enforce a
		 * security baseline across many child sites without flipping the
		 * admin option on each one.
		 *
		 * @since 3.0.32
		 * @param bool $allowed Current allow value (default TRUE).
		 */
		return (bool) apply_filters( 'leadconnector_native_mode_allowed', $allowed );
	}

	/**
	 * Maintain the native-funnel index on save_post for the LeadConnector CPT.
	 *
	 * Examines the saved post's `leadconnector_display_method` post-meta and
	 * adds/removes the post ID from the
	 * leadconnector_native_funnel_index option as appropriate. The option is
	 * stored non-autoloaded because most page loads never need it.
	 *
	 * Hooked from LeadConnector_Loader at save_post priority 20 (after the
	 * post-meta from the LC editor REST handler has been written).
	 *
	 * @since 3.0.30
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function sync_native_funnel_index_on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post || lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE !== $post->post_type ) {
			return;
		}

		$display_method = get_post_meta( (int) $post_id, 'leadconnector_display_method', true );

		// 3.0.32: only index posts that are currently published — the
		// trust-extension notice is about live customer-facing rendering,
		// not draft / pending / trashed funnels.
		$is_native = ( 'native' === $display_method ) && ( 'publish' === $post->post_status );
		$this->upsert_native_funnel_index_entry( (int) $post_id, $is_native );
	}

	/**
	 * Drop a deleted funnel post from the native-funnel index.
	 *
	 * Hooked from LeadConnector_Loader at before_delete_post.
	 *
	 * @since 3.0.30
	 * @param int $post_id Post ID being deleted.
	 * @return void
	 */
	public function sync_native_funnel_index_on_delete( $post_id ) {
		$index = get_option( self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION, null );
		if ( ! is_array( $index ) || empty( $index ) ) {
			return;
		}

		$post_id = (int) $post_id;
		if ( ! in_array( $post_id, $index, true ) ) {
			return;
		}

		$index = array_values(
			array_filter(
				$index,
				static function ( $candidate ) use ( $post_id ) {
					return (int) $candidate !== $post_id;
				}
			)
		);
		update_option( self::LEADCONNECTOR_NATIVE_FUNNEL_INDEX_OPTION, $index, false );
	}

	/**
	 * Schedule a deferred sync when a page post is trashed or deleted in WP Admin.
	 *
	 * @since 3.0.35
	 * @param int $post_id Post ID being removed.
	 * @return void
	 */
	public function schedule_sync_ai_page_deleted_from_wp( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( 'page' !== get_post_type( $post_id ) ) {
			return;
		}

		$args = array( $post_id );
		if ( false === wp_next_scheduled( 'leadconnector_sync_ai_page_wp_deleted_event', $args ) ) {
			wp_schedule_single_event( time(), 'leadconnector_sync_ai_page_wp_deleted_event', $args );
		}
	}

	/**
	 * WP-Cron: notify LeadConnector services that a WP page was deleted in WP Admin.
	 *
	 * @since 3.0.35
	 * @param int $wp_page_id WordPress page post ID.
	 * @return void
	 */
	public function leadconnector_handle_sync_ai_page_wp_deleted_event( $wp_page_id ) {
		$wp_page_id = (int) $wp_page_id;
		if ( $wp_page_id <= 0 ) {
			return;
		}

		$leadconnector_options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		if ( empty( $leadconnector_options ) || ! is_array( $leadconnector_options ) ) {
			return;
		}

		$location_id = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] )
			? trim( (string) $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] )
			: '';
		if ( '' === $location_id || 'leadconnector_disconnect' === $location_id ) {
			return;
		}

		$wp_id = $this->get_cdn_wp_id();
		if ( '' === $wp_id ) {
			return;
		}

		$endpoint = sprintf(
			'wordpress/ai-pages/%s/site/%s/sync-wp-delete',
			rawurlencode( $location_id ),
			rawurlencode( $wp_id )
		);

		$response = $this->leadconnector_oauth_wp_remote_v2(
			'post',
			$endpoint,
			array(
				'wpPageId' => (string) $wp_page_id,
			)
		);

		if ( ! empty( $response['error'] ) ) {
			LeadConnector_Logger::get_instance()->warning(
				'leadconnector_sync_ai_page_wp_deleted.failed',
				array(
					'wp_page_id'  => $wp_page_id,
					'http_code'   => isset( $response['http_code'] ) ? (int) $response['http_code'] : null,
					'location_id' => $location_id,
					'wp_id'       => $wp_id,
				)
			);
		}
	}

	/**
	 * Schedule a deferred sync when a page post_status changes in WP Admin.
	 *
	 * Fires on `transition_post_status`. Only acts on `page` post types and
	 * only on publish↔draft transitions. Trash/delete transitions are handled
	 * separately by `schedule_sync_ai_page_deleted_from_wp`.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public function schedule_sync_ai_page_status_from_wp( $new_status, $old_status, $post ) {
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
			return;
		}

		if ( $new_status === $old_status ) {
			return;
		}

		if ( 'trash' === $new_status || 'trash' === $old_status ) {
			return;
		}

		$is_publish_change = ( 'publish' === $new_status || 'publish' === $old_status );
		if ( ! $is_publish_change ) {
			return;
		}

		$post_id = (int) $post->ID;
		if ( $post_id <= 0 ) {
			return;
		}

		$mapped_status = ( 'publish' === $new_status ) ? 'publish' : 'draft';
		$args          = array( $post_id, $mapped_status );
		if ( false === wp_next_scheduled( 'leadconnector_sync_ai_page_wp_status_event', $args ) ) {
			wp_schedule_single_event( time(), 'leadconnector_sync_ai_page_wp_status_event', $args );
		}
	}

	/**
	 * WP-Cron: notify LeadConnector services that a WP page post_status changed.
	 *
	 * @since 3.0.37
	 * @param int    $wp_page_id  WordPress page post ID.
	 * @param string $post_status New WordPress post status.
	 * @return void
	 */
	public function leadconnector_handle_sync_ai_page_wp_status_event( $wp_page_id, $post_status ) {
		$wp_page_id = (int) $wp_page_id;
		if ( $wp_page_id <= 0 ) {
			return;
		}

		$leadconnector_options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		if ( empty( $leadconnector_options ) || ! is_array( $leadconnector_options ) ) {
			return;
		}

		$location_id = isset( $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] )
			? trim( (string) $leadconnector_options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] )
			: '';
		if ( '' === $location_id || 'leadconnector_disconnect' === $location_id ) {
			return;
		}

		$wp_id = $this->get_cdn_wp_id();
		if ( '' === $wp_id ) {
			return;
		}

		$endpoint = sprintf(
			'wordpress/ai-pages/%s/site/%s/sync-wp-status',
			rawurlencode( $location_id ),
			rawurlencode( $wp_id )
		);

		$response = $this->leadconnector_oauth_wp_remote_v2(
			'post',
			$endpoint,
			array(
				'wpPageId'   => (string) $wp_page_id,
				'postStatus' => (string) $post_status,
			)
		);

		if ( ! empty( $response['error'] ) ) {
			LeadConnector_Logger::get_instance()->warning(
				'leadconnector_sync_ai_page_wp_status.failed',
				array(
					'wp_page_id'  => $wp_page_id,
					'post_status' => $post_status,
					'http_code'   => isset( $response['http_code'] ) ? (int) $response['http_code'] : null,
					'location_id' => $location_id,
					'wp_id'       => $wp_id,
				)
			);
		}
	}

	/**
	 * Admin notice when native funnel mode renders remote LeadConnector HTML.
	 *
	 * @return void
	 */
	public function display_native_mode_trust_notice() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->is_leadconnector_site_connected() ) {
			return;
		}

		if ( ! $this->site_has_native_display_funnels() ) {
			return;
		}

		// 3.0.32: scope the notice to LeadConnector admin pages. Prefer
		// the `?page=…` request parameter over `get_current_screen()->id`
		// because the LeadConnector submenu uses non-standard menu slugs
		// of the form `leadconnector-plugin&tab=funnels`, and the
		// computed screen ID for those submenus is not always normalized
		// to contain the substring "leadconnector". Fall back to the
		// screen ID check as a safety net.
		$is_leadconnector_admin_page = false;

		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen scoping check.
			$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen scoping check.
			if ( 0 === strpos( $page, 'leadconnector' ) || 0 === strpos( $page, 'lc-' ) ) {
				$is_leadconnector_admin_page = true;
			}
		}

		if ( ! $is_leadconnector_admin_page && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && false !== strpos( (string) $screen->id, 'leadconnector' ) ) {
				$is_leadconnector_admin_page = true;
			}
		}

		if ( ! $is_leadconnector_admin_page ) {
			return;
		}

		// 3.0.32: branch the notice copy on whether the admin has flipped
		// the native-mode opt-in toggle off. When native mode is disabled
		// every `'native'` funnel post is silently downgraded to iframe
		// rendering by `process_page_request()`, so the warning copy is
		// inverted to confirm the safer posture rather than warn about a
		// live trust boundary that no longer exists.
		$native_allowed = $this->is_native_funnel_rendering_allowed();

		$notice_allowed = array(
			'div'    => array( 'class' => true ),
			'p'      => array(),
			'strong' => array(),
		);

		if ( $native_allowed ) {
			$notice_html = sprintf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__(
					'Native Funnel Display is enabled. Your funnel pages load directly on your website instead of inside an iframe, which helps payment processors and other on-page tools work properly. To do this, LeadConnector loads funnel content, styles, and scripts from its servers and approved partner services onto your site. Only use this mode for LeadConnector accounts you trust. You can switch to iframe mode anytime in funnel settings.',
					'leadconnector'
				)
			);
		} else {
			$notice_html = sprintf(
				'<div class="notice notice-info"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Native funnel rendering is disabled.', 'leadconnector' ),
				esc_html__(
					'Funnel posts marked "native" are being shown via the iframe display path instead, so no LeadConnector JavaScript runs on your WordPress origin. Re-enable native mode from the LeadConnector settings if you need to embed payment processors or other in-page integrations.',
					'leadconnector'
				)
			);
		}

		echo wp_kses( $notice_html, $notice_allowed );
	}

	/**
	 * Issue a 301 redirect to the admin-saved funnel step URL.
	 *
	 * The funnel step URL is post meta written by an admin user when the
	 * funnel is imported from LeadConnector. Its host varies per environment
	 * (`app.leadconnectorhq.com`, `services.leadconnectorhq.com`, white-label
	 * agency domains saved in plugin options).
	 *
	 * SECURITY (#H10): the previous implementation temporarily added the
	 * destination host to `allowed_redirect_hosts` inside a closure, called
	 * `wp_safe_redirect`, then removed the filter. That pattern voids the
	 * `wp_safe_redirect` security guarantee — the safety check is a no-op
	 * because the destination is explicitly whitelisted right before the
	 * call. A future privilege-escalation bug, or a compromised admin
	 * account, could turn this into an open redirect.
	 *
	 * The fix is a positive allowlist: the destination host must match
	 * (a) a canonical LeadConnector host, (b) the site's own home host, or
	 * (c) an admin-configured white-label URL saved in plugin options.
	 * Hosts outside the allowlist surface a `wp_die()` error instead of
	 * bouncing the visitor offsite.
	 *
	 * @param string $funnel_step_url Absolute URL stored in post meta.
	 * @return void
	 */
	private function redirect_to_funnel_step_url( $funnel_step_url ) {
		$funnel_step_url = is_string( $funnel_step_url ) ? trim( $funnel_step_url ) : '';

		if ( '' === $funnel_step_url ) {
			wp_die(
				esc_html__( 'This LeadConnector funnel page has no destination URL configured.', 'leadconnector' ),
				esc_html__( 'LeadConnector', 'leadconnector' ),
				array( 'response' => 500 )
			);
		}

		$redirect_host = wp_parse_url( $funnel_step_url, PHP_URL_HOST );

		if ( ! is_string( $redirect_host ) || '' === $redirect_host ) {
			wp_die(
				esc_html__( 'This LeadConnector funnel page has an invalid redirect URL configured.', 'leadconnector' ),
				esc_html__( 'LeadConnector', 'leadconnector' ),
				array( 'response' => 500 )
			);
		}

		if ( ! $this->is_allowed_funnel_redirect_host( $redirect_host ) ) {
			wp_die(
				esc_html__( 'This LeadConnector funnel page is configured to redirect to a host outside the allowed list. Reconnect the funnel from your LeadConnector dashboard or contact your site administrator.', 'leadconnector' ),
				esc_html__( 'LeadConnector', 'leadconnector' ),
				array( 'response' => 500 )
			);
		}
		$allowed_one_shot = static function ( $hosts ) use ( $redirect_host ) {
			$hosts[] = $redirect_host;
			return $hosts;
		};
		add_filter( 'allowed_redirect_hosts', $allowed_one_shot );
		wp_safe_redirect( esc_url_raw( $funnel_step_url ), 301 );
		remove_filter( 'allowed_redirect_hosts', $allowed_one_shot );
		exit;
	}

	/**
	 * Whether a host is permitted as a funnel redirect destination.
	 *
	 * Matches case-insensitively against:
	 *
	 *   - The hardcoded canonical LeadConnector host set (production app /
	 *     REST / services / API / backend / msgsndr / reputationhub
	 *     widgets / widgets.leadconnectorhq.com / marketplace).
	 *   - The site's own canonical home_url() host (so a funnel can redirect
	 *     to another page on the same WordPress install if the customer
	 *     configures it that way).
	 *   - The admin-saved `LOCATION_WHITE_LABEL_URL` host from plugin
	 *     options (agencies using a white-labelled LC frontend).
	 *
	 * Returns true only on an exact host match. Subdomains of an allowed
	 * host are NOT auto-included — the allowlist is positive, not wildcard.
	 *
	 * @param string $host Host portion of the candidate redirect URL.
	 * @return bool
	 */
	private function is_allowed_funnel_redirect_host( $host ) {
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$normalized = strtolower( $host );

		$allowlist = array(
			'app.leadconnectorhq.com',
			'rest.leadconnectorhq.com',
			'services.leadconnectorhq.com',
			'api.leadconnectorhq.com',
			'backend.leadconnectorhq.com',
			'marketplace.leadconnectorhq.com',
			'widgets.leadconnectorhq.com',
			'link.msgsndr.com',
			'reputationhub.site',
		);

		// The site's own canonical home host.
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( is_string( $site_host ) && '' !== $site_host ) {
			$allowlist[] = strtolower( $site_host );
		}

		// Admin-saved white-label location URL (option), if present.
		$options = get_option( LEAD_CONNECTOR_OPTION_NAME );
		if ( is_array( $options ) && ! empty( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL ] ) ) {
			$wl_host = wp_parse_url( (string) $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_WHITE_LABEL_URL ], PHP_URL_HOST );
			if ( is_string( $wl_host ) && '' !== $wl_host ) {
				$allowlist[] = strtolower( $wl_host );
			}
		}

		/**
		 * Filter the funnel redirect host allowlist.
		 *
		 * Returning an additional host here lets site administrators register
		 * extra white-label / vanity hosts without editing the plugin.
		 *
		 * @since 3.0.32
		 * @param string[] $allowlist Lowercased host allowlist.
		 * @param string   $host      Lowercased candidate host being checked.
		 */
		$allowlist = (array) apply_filters( 'leadconnector_allowed_funnel_redirect_hosts', $allowlist, $normalized );
		$allowlist = array_filter( array_map( 'strtolower', array_map( 'strval', $allowlist ) ) );

		return in_array( $normalized, $allowlist, true );
	}

	/**
	 * Normalize a host string to its canonical ASCII (punycode) form,
	 * lowercased and stripped of any trailing dot or port.
	 *
	 * Used by the public funnel routing path (`process_page_request()`) to
	 * make Host-header comparisons robust against Unicode IDN encoding
	 * (e.g. `münchen.example` vs `xn--mnchen-3ya.example`) and against
	 * casing differences (RFC 3986 §3.2.2 — host names are
	 * case-insensitive).
	 *
	 * Falls back to a lowercased copy of the input when the PHP `intl`
	 * extension's `idn_to_ascii()` is unavailable, which is the same
	 * behaviour `wp_safe_redirect()` defaults to.
	 *
	 * @since 3.0.32
	 * @param string $host Raw host (may include a port or trailing dot).
	 * @return string Normalized lowercase ASCII host, or '' on bad input.
	 */
	private function leadconnector_normalize_host( $host ) {
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		// Strip a trailing port if present (Host header sometimes carries one).
		$colon_pos = strpos( $host, ':' );
		if ( false !== $colon_pos ) {
			$host = substr( $host, 0, $colon_pos );
		}
		$host = rtrim( $host, '.' );
		$host = strtolower( $host );

		if ( function_exists( 'idn_to_ascii' ) ) {
			$variant = defined( 'INTL_IDNA_VARIANT_UTS46' ) ? INTL_IDNA_VARIANT_UTS46 : 0;
			// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.idn_to_asciiFound, WordPress.PHP.NoSilencedErrors -- idn_to_ascii may warn on invalid labels; failure is handled below.
			$ascii = @idn_to_ascii( $host, IDNA_DEFAULT, $variant );
			if ( is_string( $ascii ) && '' !== $ascii ) {
				return strtolower( $ascii );
			}
		}

		return $host;
	}

	/**
	 * Match the current request URL to a LeadConnector funnel page and render it.
	 *
	 * @return void
	 */
	public function process_page_request() {

		// H8 / H9: Sanitize superglobals used in URL construction.
		$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// C13 / M11: Refuse to do funnel-routing on a Host header that does
		// not match the site's canonical home_url() host. The Host header is
		// attacker-controllable on misconfigured fronting proxies and would
		// otherwise let a hostile request mint a slug that points outside
		// the canonical site, which is then handed to WP_Query.
		//
		// Both values are punycode-normalized via leadconnector_normalize_host()
		// before the compare so a Unicode IDN incoming Host header (e.g.
		// `münchen.example`) cannot bypass an ASCII-encoded `expected_host`
		// (`xn--mnchen-3ya.example`) — and vice versa.
		$expected_host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$expected_host  = is_string( $expected_host ) ? $this->leadconnector_normalize_host( $expected_host ) : '';
		$http_host_norm = $this->leadconnector_normalize_host( $http_host );
		if ( '' === $http_host_norm || '' === $expected_host || $http_host_norm !== $expected_host ) {
			return;
		}

		$full_request_url  = ( is_ssl() ? 'https://' : 'http://' ) . $http_host . $request_uri;
		$request_url_parts = explode( '?', $full_request_url );
		$request_url       = $request_url_parts[0];
		$base_url          = get_home_url() . '/';
		$slug              = str_replace( $base_url, '', $request_url );
		$slug              = rtrim( $slug, '/' );
		if ( '' !== $slug ) {
			$leadconnector_page = leadconnector_get_funnel_post_by_slug( $slug );

			if ( $leadconnector_page ) {
				status_header( 200 );
				$post_id                                      = $leadconnector_page->ID;
				$funnel_step_url                              = get_post_meta( $post_id, 'leadconnector_step_url', true );
				$leadconnector_display_method                 = get_post_meta( $post_id, 'leadconnector_display_method', true );
				$leadconnector_post_meta                      = get_post_meta( $post_id, 'leadconnector_step_meta', true );
				$leadconnector_step_tracking_code             = get_post_meta( $post_id, 'leadconnector_step_trackingCode', true );
				$leadconnector_funnel_tracking_code           = get_post_meta( $post_id, 'leadconnector_funnel_tracking_code', true );
				$leadconnector_include_tracking_code          = get_post_meta( $post_id, 'leadconnector_include_tracking_code', true );
				$leadconnector_use_site_favicon               = get_post_meta( $post_id, 'leadconnector_use_site_favicon', true );
				$leadconnector_include_wp_headers_and_footers = get_post_meta( $post_id, 'leadconnector_include_wp_headers_and_footers', true );

				if ( ! $leadconnector_include_tracking_code ) {
					$leadconnector_step_tracking_code   = null;
					$leadconnector_funnel_tracking_code = null;
				}

				// 3.0.32: enforce the native-mode admin opt-in. When the
				// site administrator has flipped native rendering off (or
				// an mu-plugin returned FALSE from the
				// `leadconnector_native_mode_allowed` filter), every funnel
				// post whose `leadconnector_display_method` is `native` is
				// silently downgraded to iframe rendering. This keeps the
				// funnel viewable but prevents remote LC JavaScript from
				// executing on the WordPress origin. Documented under
				// "Native funnel rendering — trust boundary" in README.txt.
				if ( 'native' === $leadconnector_display_method
					&& ! $this->is_native_funnel_rendering_allowed()
				) {
					$leadconnector_display_method = 'iframe';
				}

				if ( 'iframe' === $leadconnector_display_method || 'native' === $leadconnector_display_method ) {

					// Check if we should use WordPress headers and footers (for both iframe and native).
					if ( $leadconnector_include_wp_headers_and_footers && 'native' === $leadconnector_display_method ) {
						$this->setup_native_display_with_wp_headers(
							$leadconnector_page,
							$funnel_step_url,
							$leadconnector_post_meta,
							$leadconnector_step_tracking_code,
							$leadconnector_funnel_tracking_code,
							$leadconnector_use_site_favicon
						);
						return;
					}

					// Standard rendering without WP headers/footers.
					if ( 'native' === $leadconnector_display_method ) {
						$content = $this->get_page_iframe_native( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon );
					} else {
						$content = $this->get_page_iframe( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon );
					}

					$allowed_html = $this->iframe_page_allowed_html();

					if ( 'native' === $leadconnector_display_method ) {
						// Separate scripts and stylesheets out of the raw
						// upstream HTML so they can be re-emitted via the
						// WordPress style/script APIs at output time.
						// wp_kses() text-escapes the body of <script> and
						// <style> tags (turning CSS combinators like `.c-row
						// > .inner` into `.c-row &gt; .inner` and stripping
						// inline JS), so both must be carried around
						// out-of-band and printed via
						// wp_enqueue_style() / wp_add_inline_style() and
						// wp_enqueue_script() / wp_print_*_tag().
						$leadconnector_script_parts = $this->extract_scripts_from_html( $content );
						$leadconnector_style_parts  = $this->extract_styles_from_html( $leadconnector_script_parts['html'] );
						$leadconnector_raw_html     = $leadconnector_style_parts['html'];

						// Split the *raw* HTML on </head> and </body> so the
						// extracted styles and scripts can be re-inserted at
						// their canonical document positions. Each chunk is
						// still escaped at the moment of output via
						// wp_kses() below (escape-late principle), so the
						// pre-split string never leaves untrusted markup in
						// the final response.
						$leadconnector_head_split = explode( '</head>', $leadconnector_raw_html, 2 );
						if ( 2 === count( $leadconnector_head_split ) ) {
							$leadconnector_pre_head_close = $leadconnector_head_split[0];
							$leadconnector_after_head     = $leadconnector_head_split[1];
							$leadconnector_has_head_close = true;
						} else {
							$leadconnector_pre_head_close = $leadconnector_raw_html;
							$leadconnector_after_head     = '';
							$leadconnector_has_head_close = false;
						}

						$leadconnector_body_split = explode( '</body>', $leadconnector_after_head, 2 );
						if ( 2 === count( $leadconnector_body_split ) ) {
							$leadconnector_pre_body_close  = $leadconnector_body_split[0];
							$leadconnector_post_body_close = $leadconnector_body_split[1];
							$leadconnector_has_body_close  = true;
						} else {
							$leadconnector_pre_body_close  = $leadconnector_after_head;
							$leadconnector_post_body_close = '';
							$leadconnector_has_body_close  = false;
						}

						$leadconnector_native_allowed = $this->native_full_page_allowed_tags();

						// 3.0.32: splice the native-mode Content-Security-Policy
						// `<meta>` tag in immediately after `<head>` opens so
						// that the browser applies the policy to every
						// subsequent fetch the funnel content tries
						// (including any `<link rel=preload>` / canonical /
						// icon links that survived stylesheet extraction).
						// In this standalone path there is no `wp_head`
						// action to hook into, so the CSP is spliced into
						// the raw HTML before `wp_kses()` (the `meta`
						// allowlist in $leadconnector_native_allowed
						// preserves the http-equiv attribute on emission).
						$leadconnector_pre_head_close = $this->inject_native_mode_csp_into_head_html( $leadconnector_pre_head_close );

						// Re-prepend the HTML5 doctype so the browser renders
						// in standards mode. wp_kses() strips doctype
						// declarations from its input (they are not tags).
						echo wp_kses( '<!DOCTYPE html>' . "\n", $leadconnector_native_allowed );

						// Escape-late: each chunk of raw upstream markup is
						// passed through wp_kses() at the exact moment of
						// output, never stored pre-sanitized.
						echo wp_kses( $leadconnector_pre_head_close, $leadconnector_native_allowed );

						// Emit extracted stylesheets via wp_enqueue_style() /
						// wp_add_inline_style() / wp_print_styles() so they
						// appear immediately before </head> in the document.
						$this->output_extracted_styles( $leadconnector_style_parts['styles'] );

						if ( $leadconnector_has_head_close ) {
							echo wp_kses( '</head>', $leadconnector_native_allowed );
						}

						// Body content (everything between </head> and
						// </body>), escaped at output.
						echo wp_kses( $leadconnector_pre_body_close, $leadconnector_native_allowed );

						// Emit extracted scripts via wp_enqueue_script() /
						// wp_print_script_tag() / wp_print_inline_script_tag()
						// at the bottom of the body so the DOM is available
						// when they execute, mirroring the upstream document.
						$this->output_extracted_scripts( $leadconnector_script_parts['scripts'], 'footer' );

						if ( $leadconnector_has_body_close ) {
							echo wp_kses( '</body>', $leadconnector_native_allowed );
						}

						// Any markup after </body> (typically just </html>),
						// escaped at output.
						echo wp_kses( $leadconnector_post_body_close, $leadconnector_native_allowed );
					} else {
						echo wp_kses( $content, $allowed_html );
					}
				} else {
					$this->redirect_to_funnel_step_url( $funnel_step_url );
				}
				exit();
			}
		}
	}

	/**
	 * Detect active SMTP plugins that may conflict with LeadConnector email.
	 *
	 * @return array
	 */
	public function check_smtp_plugin_conflict() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins               = get_plugins();
		$could_have_conflict   = false;
		$plugins_with_conflict = '';
		$active_plugins        = get_option( 'active_plugins' );
		foreach ( $plugins as $plugin_key => $plugin ) { // get the key of array plugins aswell.
			if ( strpos( $plugin['Name'], 'SMTP' ) !== false ) {
				if ( in_array( $plugin_key, $active_plugins, true ) ) {
					$could_have_conflict = true;

					if ( '' === $plugins_with_conflict ) {
						$plugins_with_conflict .= $plugin['Name'];
					} else {
						$plugins_with_conflict .= ', ' . $plugin['Name'] . ', ';
					}
				}
			}
		}

		return array(
			'could_have_conflict' => $could_have_conflict,
			'plugins'             => $plugins_with_conflict,
		);
	}

	/**
	 * Mirrors is_wp_purge_all_domains_cache_authorized() for leadconnector-admin.js purge toolbar visibility.
	 *
	 * @param WP_REST_Request $request Request (unused).
	 * @return WP_REST_Response JSON wpPurgeAllDomainsCacheAuthorized boolean.
	 */
	public function leadconnector_rest_wp_purge_all_domains_cache_authorization_status( $request ) {
		unset( $request );
		return new WP_REST_Response(
			array(
				'wpPurgeAllDomainsCacheAuthorized' => $this->is_wp_purge_all_domains_cache_authorized(),
			),
			200
		);
	}

	// The `leadconnector_async_save_custom_values()` REST callback was
	// removed in 3.0.32 alongside its registered route
	// (`/leadconnector_internal_api/v1/save_custom_values`). The deferred
	// custom-values write now runs in-process via the
	// `leadconnector_save_custom_values_event` WP-Cron hook handled by
	// `leadconnector_handle_save_custom_values_event()`, which does not
	// require an authentication context to perform an internal cache write
	// and therefore does not need WordPress auth cookies to travel through
	// any plugin-generated HTTP request.

	// Native funnel embedding renders LeadConnector-authored content inline for
	// SEO parity under a first-party trust boundary: payloads originate from the
	// vendor-owned LeadConnector platform via allowlisted origins, and scripts
	// and styles are re-emitted through WordPress-native APIs to preserve fidelity.
	/**
	 * Setup native display with WordPress headers and footers
	 * For theme-agnostic header/footer support, using the content filter approach
	 * instead of custom template loading. This works with ALL themes including
	 *
	 * @param WP_Post $leadconnector_page The page post object.
	 * @param string  $funnel_step_url The funnel step URL.
	 * @param string  $leadconnector_post_meta Post meta data.
	 * @param string  $leadconnector_step_tracking_code Step tracking code.
	 * @param string  $leadconnector_funnel_tracking_code Funnel tracking code.
	 * @param bool    $leadconnector_use_site_favicon Whether to use site favicon.
	 */
	private function setup_native_display_with_wp_headers( $leadconnector_page, $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon ) {
		// Setup WordPress query to treat this as a valid page.
		$this->setup_wp_query_for_native_page( $leadconnector_page );

		// 3.0.32: emit the native-mode Content-Security-Policy `<meta>` tag
		// as the very first thing inside `<head>` so the browser applies
		// the policy to every subsequent stylesheet, script, image, font
		// and XHR the funnel content attempts. Priority `1` puts us ahead
		// of every other wp_head callback (the WP default is `10`).
		add_action( 'wp_head', array( $this, 'output_native_mode_csp' ), 1 );
		wp_enqueue_style(
			'leadconnector-native-page',
			plugin_dir_url( __DIR__ ) . 'public/css/leadconnector-native-page.css',
			array(),
			defined( 'LEAD_CONNECTOR_VERSION' ) ? LEAD_CONNECTOR_VERSION : '3.0.26'
		);
		// D2: polyfill is shipped as a real static asset (public/js/leadconnector-native-polyfills.js)
		// so the WP.org reviewer is not asked to vet inline JavaScript every
		// release, and so caches/CDNs can serve it like any other asset.
		$leadconnector_polyfills_path = plugin_dir_path( __DIR__ ) . 'public/js/leadconnector-native-polyfills.js';
		$leadconnector_polyfills_ver  = file_exists( $leadconnector_polyfills_path ) ? filemtime( $leadconnector_polyfills_path ) : $this->version;
		wp_enqueue_script(
			'leadconnector-native-polyfills',
			plugin_dir_url( __DIR__ ) . 'public/js/leadconnector-native-polyfills.js',
			array(),
			$leadconnector_polyfills_ver,
			false
		);

		// Add content filter to inject native HTML into the page content.
		$this->add_native_content_filter( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon );

		// Add head content via wp_head action.
		$this->add_native_head_content( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon );

		// Add footer tracking code.
		$this->add_native_footer_tracking( $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code );

		// Add body class for styling.
		$this->add_native_body_class();
	}

	/**
	 * Setup WordPress query variables for native page display
	 *
	 * @param WP_Post $leadconnector_page The page post object.
	 */
	private function setup_wp_query_for_native_page( $leadconnector_page ) {
		global $wp_query;

		// Reset query vars to treat this as a valid page.
		$wp_query->is_404        = false;
		$wp_query->is_page       = true;
		$wp_query->is_singular   = true;
		$wp_query->is_home       = false;
		$wp_query->found_posts   = 1;
		$wp_query->post_count    = 1;
		$wp_query->max_num_pages = 1;

		// Set the current post via setup_postdata(); do not assign the global directly.
		$wp_query->post              = $leadconnector_page;
		$wp_query->posts             = array( $leadconnector_page );
		$wp_query->queried_object    = $leadconnector_page;
		$wp_query->queried_object_id = $leadconnector_page->ID;

		setup_postdata( $leadconnector_page );
	}

	/**
	 * Add content filter to inject native HTML content
	 *
	 * @param string $funnel_step_url The funnel step URL.
	 * @param string $leadconnector_post_meta Post meta data.
	 * @param string $leadconnector_step_tracking_code Step tracking code.
	 * @param string $leadconnector_funnel_tracking_code Funnel tracking code.
	 * @param bool   $leadconnector_use_site_favicon Whether to use site favicon.
	 */
	private function add_native_content_filter( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon ) {
		add_filter(
			'the_content',
			function ( $content ) use ( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon ) {
				unset( $content );
				$native_html_content = $this->get_page_iframe_native( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon );

				if ( ! empty( $native_html_content ) ) {
					$native_body_content = $this->extract_body_content( $native_html_content );

					// Separate scripts and stylesheets before wp_kses() so the
					// kses pass cannot text-escape their bodies. wp_kses keeps
					// inline <script> and <style> tags on its allowlist but
					// HTML-encodes content inside them, which silently breaks
					// inline JS and CSS combinator selectors (`.c-row > .inner`
					// becoming `.c-row &gt; .inner`).
					$script_parts = $this->extract_scripts_from_html( $native_body_content );
					$style_parts  = $this->extract_styles_from_html( $script_parts['html'] );
					$clean_html   = $style_parts['html'];
					$body_scripts = $script_parts['scripts'];
					$body_styles  = $style_parts['styles'];

					// Schedule extracted body scripts and styles for output in wp_footer.
					if ( ! empty( $body_scripts ) || ! empty( $body_styles ) ) {
						$admin_ref = $this;
						add_action(
							'wp_footer',
							function () use ( $admin_ref, $body_scripts, $body_styles ) {
								if ( ! empty( $body_styles ) ) {
									$admin_ref->output_extracted_styles( $body_styles );
								}
								if ( ! empty( $body_scripts ) ) {
									$admin_ref->output_extracted_scripts( $body_scripts, 'footer' );
								}
							},
							5
						);
					}

					return wp_kses(
						'<div class="leadconnector-native-content">' . $clean_html . '</div>',
						$this->native_body_allowed_tags()
					);
				}

				$error_allowed = array(
					'div' => array(
						'class' => true,
						'style' => true,
					),
					'h2'  => array(),
					'p'   => array(),
				);
				return wp_kses(
					'<div style="padding: 2em; text-align: center;"><h2>' . esc_html__( 'Error: Failed to load funnel content.', 'leadconnector' ) . '</h2><p>' . esc_html__( 'Please check if the funnel URL is valid.', 'leadconnector' ) . '</p></div>',
					$error_allowed
				);
			},
			10
		);
	}

	/**
	 * Extract body content from HTML
	 *
	 * Uses regex-based extraction to preserve HTML5 elements like video, source, etc.
	 * DOMDocument has issues with HTML5 self-closing tags like source.
	 *
	 * @param string $html_content The full HTML content.
	 * @return string The body content.
	 */
	private function extract_body_content( $html_content ) {
		// Extract BODY content using regex to preserve HTML5 elements (video, source, etc.).
		if ( preg_match( '/<body[^>]*>(.*)<\/body>/is', $html_content, $body_matches ) ) {
			return $body_matches[1];
		}

		// If no body tag found, try to strip doctype/html/head wrapper if present.
		$body_content = preg_replace( '/^.*?<body[^>]*>/is', '', $html_content );
		$body_content = preg_replace( '/<\/body>.*$/is', '', $body_content );

		// If still no body content extracted, use original content.
		if ( empty( trim( $body_content ) ) ) {
			return $html_content;
		}

		return $body_content;
	}

	// Regex extraction preserves HTML5 self-closing tags (<source>, <track>,
	// <picture>) and srcset semantics that DOMDocument's HTML5 round-trip drops.
	/**
	 * Separate <script> tags from HTML so the markup can be run through
	 * wp_kses() while scripts are output via WordPress's script API.
	 *
	 * Wp_kses() cannot properly handle <script> tags — it strips inline JS
	 * content. This method extracts all script tags from the given HTML and
	 * returns them in a structured array for safe re-injection via
	 * wp_enqueue_script() / wp_print_inline_script_tag().
	 *
	 * @param string $html Raw HTML that may contain script tags.
	 * @return array {
	 *     @type string $html      The HTML with all script tags removed.
	 *     @type array  $scripts   Indexed array of script info arrays:
	 *         @type string $type      The script type attribute (default 'text/javascript').
	 *         @type string $src       External script URL (empty for inline).
	 *         @type string $content   Inline script content (empty for external).
	 *         @type array  $attrs     All other attributes (async, defer, crossorigin, etc.).
	 * }
	 */
	public function extract_scripts_from_html( $html ) {
		$scripts = array();

		if ( empty( $html ) ) {
			return array(
				'html'    => '',
				'scripts' => $scripts,
			);
		}

		// Match all <script ...>...</script> and self-closing <script .../> tags.
		$pattern = '/<script([^>]*)>(.*?)<\/script>/is';
		preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$attrs_string = trim( $match[1] );
			$content      = $match[2];

			// Parse attributes from the tag.
			$attrs = array();
			if ( preg_match_all( '/(\w[\w\-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/', $attrs_string, $attr_matches, PREG_SET_ORDER ) ) {
				foreach ( $attr_matches as $am ) {
					$attr_name  = strtolower( $am[1] );
					$attr_value = '';
					if ( isset( $am[2] ) && '' !== $am[2] ) {
						$attr_value = $am[2];
					} elseif ( isset( $am[3] ) && '' !== $am[3] ) {
						$attr_value = $am[3];
					} elseif ( isset( $am[4] ) && '' !== $am[4] ) {
						$attr_value = $am[4];
					} else {
						$attr_value = $attr_name; // boolean attribute.
					}
					$attrs[ $attr_name ] = $attr_value;
				}
			}

			$type = isset( $attrs['type'] ) ? $attrs['type'] : 'text/javascript';
			$src  = isset( $attrs['src'] ) ? $attrs['src'] : '';

			unset( $attrs['type'], $attrs['src'] );

			$scripts[] = array(
				'type'    => $type,
				'src'     => $src,
				'content' => $content,
				'attrs'   => $attrs,
			);
		}

		// Remove all script tags from the HTML.
		$clean_html = preg_replace( $pattern, '', $html );

		return array(
			'html'    => $clean_html,
			'scripts' => $scripts,
		);
	}

	/**
	 * Strip <style> blocks and <link rel="stylesheet"> tags from a chunk of HTML
	 * so they can be re-emitted via WordPress's stylesheet API.
	 *
	 * Why this exists: when the native-funnel HTML response is run through
	 * wp_kses(), kses preserves the <style> tag (it is on the allowlist) but
	 * text-escapes the CSS *inside* the tag. That turns selectors like
	 * `.c-row > .inner` into `.c-row &gt; .inner`, which is invalid CSS — so
	 * desktop two-column layout rules silently stop matching, which manifests
	 * as broken alignment in the rendered funnel page. Extracting styles
	 * before kses (and re-emitting them via wp_register_style /
	 * wp_add_inline_style afterwards) is the WP.org-recommended pattern that
	 * mirrors how this class already handles inline <script> blocks.
	 *
	 * @since 3.0.30
	 * @param string $html Raw HTML that may contain <style> or stylesheet <link> tags.
	 * @return array {
	 *     @type string $html   The HTML with all extracted style tags removed.
	 *     @type array  $styles Indexed array of style info arrays:
	 *         @type string $src     External stylesheet URL (empty for inline blocks).
	 *         @type string $content Inline CSS text (empty for external).
	 * }
	 */
	public function extract_styles_from_html( $html ) {
		$styles = array();

		if ( ! is_string( $html ) || '' === $html ) {
			return array(
				'html'   => '',
				'styles' => $styles,
			);
		}

		// Strip <link rel="stylesheet" ...> tags first so we can capture their
		// href values before the regex replace removes them.
		$link_pattern = '/<link\b[^>]*\brel\s*=\s*(?:"[^"]*\bstylesheet\b[^"]*"|\'[^\']*\bstylesheet\b[^\']*\')[^>]*\/?>/is';
		if ( preg_match_all( $link_pattern, $html, $link_matches ) ) {
			foreach ( $link_matches[0] as $tag ) {
				$href = '';
				if ( preg_match( '/\bhref\s*=\s*"([^"]*)"|\bhref\s*=\s*\'([^\']*)\'/i', $tag, $href_match ) ) {
					$href = '' !== $href_match[1] ? $href_match[1] : $href_match[2];
				}
				if ( '' !== $href ) {
					$styles[] = array(
						'src'     => $href,
						'content' => '',
					);
				}
			}
			$html = preg_replace( $link_pattern, '', $html );
		}

		// Strip inline <style>...</style> blocks.
		$style_pattern = '/<style\b[^>]*>(.*?)<\/style>/is';
		if ( preg_match_all( $style_pattern, $html, $style_matches ) ) {
			foreach ( $style_matches[1] as $css ) {
				$styles[] = array(
					'src'     => '',
					'content' => (string) $css,
				);
			}
			$html = preg_replace( $style_pattern, '', $html );
		}

		return array(
			'html'   => (string) $html,
			'styles' => $styles,
		);
	}

	/**
	 * Output previously extracted stylesheets and inline CSS via the WordPress
	 * styles API so they appear in the document without being run through
	 * wp_kses() (which would entity-escape `>`, `<`, `&` and `"` inside the
	 * CSS source and silently break selectors / values).
	 *
	 * Output-time hardening (3.0.32):
	 *
	 *   - External stylesheet `src` URLs are passed through `esc_url()` with
	 *     an explicit `http`/`https` protocol allowlist so non-network
	 *     schemes (`javascript:`, `data:`, `file:`, `phar:`, …) cannot reach
	 *     `wp_register_style()`.
	 *   - Inline CSS bodies (which originate from remote LeadConnector funnel
	 *     HTML and are therefore untrusted at the WordPress trust boundary)
	 *     are routed through `sanitize_extracted_inline_css()` before
	 *     `wp_add_inline_style()`, satisfying the WordPress.org plugin
	 *     review team's "escape / sanitize at the echo site" requirement.
	 *     CSS is a non-escapable serialization (no general-purpose escaping
	 *     function keeps it both safe and renderable), so an allowlist
	 *     sanitizer replaces the escape step.
	 *
	 * Inline blocks are attached to a per-block dummy handle via
	 * wp_add_inline_style() (the WP-canonical pattern) and printed via
	 * wp_print_styles(), which works correctly when called from inside a
	 * wp_head action callback regardless of whether the default style queue
	 * has already been flushed for the request.
	 *
	 * @since 3.0.30
	 * @param array $styles Array of style info from extract_styles_from_html().
	 */
	public function output_extracted_styles( $styles ) {
		if ( empty( $styles ) ) {
			return;
		}

		static $idx = 0;

		foreach ( $styles as $style ) {
			++$idx;
			$handle = 'leadconnector-funnel-style-' . $idx;

			if ( ! empty( $style['src'] ) ) {
				$style_src = esc_url( $style['src'], array( 'http', 'https' ) );
				if ( '' === $style_src ) {
					continue;
				}
				wp_register_style( $handle, $style_src, array(), $this->version );
				wp_enqueue_style( $handle );
				wp_print_styles( $handle );
				continue;
			}

			if ( ! empty( $style['content'] ) ) {
				$sanitized_css = $this->sanitize_extracted_inline_css( (string) $style['content'] );
				if ( '' === $sanitized_css ) {
					continue;
				}
				wp_register_style( $handle, false, array(), $this->version );
				wp_enqueue_style( $handle );
				wp_add_inline_style( $handle, $sanitized_css );
				wp_print_styles( $handle );
			}
		}
	}

	/**
	 * Sanitize inline CSS extracted from remote funnel HTML before it is
	 * passed to `wp_add_inline_style()` (or otherwise echoed inside a
	 * `<style>` block).
	 *
	 * The CSS bodies handled here originate from LeadConnector-authored
	 * funnel HTML that the plugin fetches via `wp_remote_get()`. At the
	 * WordPress trust boundary they are untrusted data and must be
	 * sanitized at the output site per the WordPress.org "escape / sanitize
	 * late" guideline. WordPress core does not ship an `esc_css()` helper
	 * because CSS is a non-escapable serialization (no general-purpose
	 * escaping function keeps CSS both safe and renderable), so this
	 * function applies an allowlist sanitizer instead:
	 *
	 *   - Drops invalid UTF-8 and line-/paragraph-separator characters
	 *     that can be used to confuse the CSS / HTML parser.
	 *   - Strips any HTML tag fragments (`</style>`, `<script>`, `<iframe>`,
	 *     etc.) embedded in the CSS body — if rendered, those would let
	 *     attacker-controlled upstream content break out of the inline
	 *     `<style>` element it lives in.
	 *   - Removes the legacy IE `expression( … )` construct (CSS-side
	 *     JavaScript evaluation).
	 *   - Removes legacy Mozilla `-moz-binding:` (XBL/XML script binding).
	 *   - Removes IE `behavior:` HTC bindings (script execution via HTC).
	 *   - Drops `@import` rules entirely. They can pull in arbitrary
	 *     attacker-controlled stylesheets from any origin and bypass the
	 *     per-host vetting the rest of the funnel pipeline relies on.
	 *     External stylesheets are loaded through the `<link rel=stylesheet>`
	 *     branch above (which goes through `esc_url()` and `wp_register_style()`),
	 *     not `@import`.
	 *   - Strips dangerous URI schemes (`javascript:`, `vbscript:`,
	 *     `livescript:`, `mocha:`, `jar:`, `file:`) wherever they appear in
	 *     the stylesheet, so they cannot reach a `url()` / `src()` /
	 *     `@import` context. `data:` URIs are dropped unless they reference
	 *     `image/*`, `font/*`, or `application/font-*`, so legitimate
	 *     embedded fonts and images survive but `data:text/html` (HTML
	 *     smuggling) and `data:application/javascript` do not.
	 *
	 * Everything else (selectors, declarations, custom properties, vendor
	 * prefixes, `@media`, `@keyframes`, `@font-face`, `calc()`, `var()`,
	 * `url(http(s)://…)`, root-relative and same-origin URLs) passes
	 * through unchanged so native-mode funnel pages retain their visual
	 * fidelity.
	 *
	 * @since 3.0.32
	 * @param string $css Raw inline CSS body from a remote `<style>...</style>` block.
	 * @return string Sanitized CSS safe to pass into `wp_add_inline_style()`. Empty string when input is unusable.
	 */
	public function sanitize_extracted_inline_css( $css ) {
		if ( ! is_string( $css ) || '' === $css ) {
			return '';
		}

		// 1) Drop invalid UTF-8 and HTML-significant control / separator
		// characters (NUL, LINE SEPARATOR U+2028, PARAGRAPH SEPARATOR U+2029)
		// that can be used to confuse the browser's CSS / HTML parser.
		$css      = wp_check_invalid_utf8( $css, true );
		$stripped = preg_replace( '/[\x00\x{2028}\x{2029}]/u', '', (string) $css );
		if ( is_string( $stripped ) ) {
			$css = $stripped;
		}

		// 2) Strip embedded HTML tags. CSS has no legitimate use for HTML
		// markup; a stray "</style><script>…" injected into inline CSS
		// would let untrusted upstream content break out of the inline
		// <style> element it is rendered into.
		$css = preg_replace(
			'#</?\s*(?:script|style|iframe|object|embed|link|meta|base|svg|html|body|head|noscript|template|xml)\b[^>]*>#i',
			'',
			(string) $css
		);

		// 3) Remove known script-injection CSS constructs.
		$css = preg_replace( '/expression\s*\([^)]*\)/i', '', (string) $css );
		$css = preg_replace( '/-moz-binding\s*:\s*[^;}]+;?/i', '', (string) $css );
		$css = preg_replace( '/\bbehavior\s*:\s*[^;}]+;?/i', '', (string) $css );

		// 4) Drop @import rules entirely. External stylesheets are handled
		// via the <link rel="stylesheet"> branch in output_extracted_styles()
		// where esc_url() with an http/https allowlist gates the URL.
		$css = preg_replace( '/@import\b[^;]*;?/i', '', (string) $css );

		// 5) Strip dangerous URI-scheme markers wherever they appear so they
		// can never reach a url() / src() context. Coarse stripping is
		// intentional: a CSS stylesheet has no legitimate reason to contain
		// the literal token "javascript:" outside a URI value, so the only
		// damage is to incidental comment text. Same-origin and relative
		// URLs, http(s):, mailto:, tel:, and data:(image|font) targets are
		// untouched.
		$css = preg_replace(
			'#\b(?:javascript|vbscript|livescript|mocha|jar|file|phar)\s*:#i',
			'',
			(string) $css
		);

		// 6) Allow only image/ and font/ data: URIs; strip everything else
		// (especially `data:text/html`, `data:application/javascript`, and
		// `data:application/xhtml+xml`, which are HTML / script smuggling
		// vectors).
		$css = preg_replace_callback(
			'#\bdata\s*:\s*([^,\s)\'\";]*)#i',
			static function ( $matches ) {
				$mime = strtolower( (string) $matches[1] );
				if ( '' === $mime ) {
					return '';
				}
				if ( preg_match( '#^(image/|font/|application/font-)#', $mime ) ) {
					return $matches[0];
				}
				return '';
			},
			(string) $css
		);

		return is_string( $css ) ? $css : '';
	}

	/**
	 * Return the host allowlist used to gate external `<script src>` URLs
	 * extracted from remote LeadConnector funnel HTML before they are
	 * enqueued on the WordPress origin.
	 *
	 * The list covers:
	 *
	 *   - All LeadConnector platform subdomains (the LC marketing /
	 *     funnel-builder product loads helper bundles dynamically from
	 *     subdomains like `stcdn.`, `images.`, `widgets.`, `app.` etc.).
	 *   - LeadConnector-operated sibling brands (`msgsndr.com`,
	 *     `reputationhub.site`).
	 *   - LC's media-storage CDN (`filesafe.space`).
	 *   - Bunny Fonts (`bunny.net`) — LC's default privacy-focused font
	 *     provider for funnels.
	 *   - Common payment-processor hosts that funnel checkouts integrate
	 *     with (Stripe, PayPal, Apple Pay). These cannot be replaced with
	 *     an iframe sandbox because the providers refuse to load inside
	 *     `<iframe>` for PCI / 3D Secure reasons.
	 *   - Common analytics / tag manager hosts that funnels commonly embed.
	 *
	 * Entries can take two forms:
	 *
	 *   - Exact host: `app.leadconnectorhq.com`
	 *   - Subdomain wildcard: `*.leadconnectorhq.com` (matches any direct
	 *     or nested subdomain — but NOT the apex `leadconnectorhq.com`).
	 *
	 * Sites with custom payment gateways or analytics providers can extend
	 * the list via the `leadconnector_funnel_allowed_script_hosts` filter.
	 *
	 * @since 3.0.32
	 * @return string[] Host patterns (no scheme, no port). Matching is
	 *                  done by `is_funnel_script_src_allowed()`.
	 */
	public function funnel_allowed_script_hosts() {
		$hosts = array(
			// LeadConnector platform. Wildcard covers app, services, rest,
			// api, backend, widgets, marketplace, stcdn (static CDN that
			// hosts the funnel runtime bundles + intl-tel-input +
			// libphonenumber), images (funnel image proxy), and any other
			// LC subdomain the funnel runtime fetches dynamically.
			'*.leadconnectorhq.com',
			'leadconnectorhq.com',
			// LeadConnector-operated sibling brands.
			'*.msgsndr.com',
			'msgsndr.com',
			'*.reputationhub.site',
			'reputationhub.site',
			// LC's media-storage CDN (`assets.cdn.filesafe.space`).
			'*.filesafe.space',
			// Cloudflare Turnstile captcha (replaces Google reCAPTCHA).
			'challenges.cloudflare.com',
			// Google-hosted CDNs LC funnels commonly load static assets
			// and fonts from.
			'*.googleapis.com',
			'*.gstatic.com',
			// Bunny Fonts — LC's default privacy-focused font CDN.
			'*.bunny.net',
			// Payment-processor JS that funnels embed for checkouts.
			// These providers refuse to run inside an <iframe> sandbox
			// (PCI / 3DS), so native-mode rendering on the WordPress
			// origin is the only viable display path for funnels that
			// take money.
			'*.stripe.com',
			'*.stripe.network',
			'*.paypal.com',
			'*.paypalobjects.com',
			// Apple Pay handshake / asset CDN (only relevant when a funnel
			// renders Apple Pay buttons).
			'applepay.cdn-apple.com',
			// Tag managers / analytics that funnels often integrate with.
			'*.googletagmanager.com',
			'*.google-analytics.com',
			'connect.facebook.net',
			'*.facebook.net',
			// Google Ads conversion tracking / remarketing.
			'*.googleadservices.com',
			'*.doubleclick.net',
		);

		/**
		 * Filters the funnel `<script src>` host allowlist.
		 *
		 * Use this from a site mu-plugin / theme `functions.php` to register
		 * extra payment-processor or analytics hosts your funnels depend on.
		 *
		 * Entries may be exact hosts (`api.example.com`) or subdomain
		 * wildcards (`*.example.com`); the wildcard form matches any
		 * direct or nested subdomain but never the apex.
		 *
		 * @since 3.0.32
		 * @param string[] $hosts Default host allowlist.
		 */
		$hosts = apply_filters( 'leadconnector_funnel_allowed_script_hosts', $hosts );

		return is_array( $hosts ) ? array_values( array_unique( array_filter( array_map( 'strtolower', array_map( 'strval', $hosts ) ) ) ) ) : array();
	}

	/**
	 * Decide whether an external `<script src>` URL extracted from remote
	 * funnel HTML belongs to a host on the funnel script allowlist.
	 *
	 * Comparison is done on the parsed host portion of the URL (after
	 * lowercasing). Matches either:
	 *
	 *   - Exact host equality (`app.leadconnectorhq.com`), or
	 *   - A `*.host.tld` wildcard entry — which matches any direct or
	 *     nested subdomain (`stcdn.leadconnectorhq.com`,
	 *     `images.leadconnectorhq.com`) but NOT the apex
	 *     (`leadconnectorhq.com`). The apex must be listed separately if
	 *     it should also be allowed.
	 *
	 * @since 3.0.32
	 * @param string $src External script URL.
	 * @return bool True when the URL's host is on the allowlist.
	 */
	public function is_funnel_script_src_allowed( $src ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return false;
		}

		// Protocol-relative URLs (`//host/path`) are accepted by wp_parse_url.
		$host = wp_parse_url( $src, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$host          = strtolower( $host );
		$allowed_hosts = $this->funnel_allowed_script_hosts();

		foreach ( $allowed_hosts as $allowed ) {
			if ( '*.' === substr( $allowed, 0, 2 ) ) {
				// Wildcard entry: `*.foo.com` matches any host that ends
				// with `.foo.com`. The apex `foo.com` is NOT matched by
				// the wildcard — it must be listed separately.
				$suffix = substr( $allowed, 1 ); // ".foo.com"
				if ( strlen( $host ) > strlen( $suffix )
					&& substr( $host, -strlen( $suffix ) ) === $suffix
				) {
					return true;
				}
				continue;
			}

			if ( $host === $allowed ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the Content-Security-Policy directives used by native-mode
	 * funnel pages.
	 *
	 * Returns a directive => sources map. Each source list is later
	 * deduplicated and joined with a single space before being placed in
	 * a `<meta http-equiv="Content-Security-Policy">` tag.
	 *
	 * The default policy is intentionally permissive — it does not break
	 * payment processors, embedded video players, analytics, or the
	 * LeadConnector platform itself — but it (a) pins the document's
	 * default fetch to the funnel host allowlist, (b) refuses `<object>` /
	 * Flash / legacy plugins outright, and (c) forbids the page from
	 * being framed off-origin (clickjacking).
	 *
	 * Sites that need to extend the policy (e.g. an additional analytics
	 * processor or a self-hosted CDN) should hook the
	 * `leadconnector_native_mode_csp_directives` filter instead of
	 * monkey-patching this method. Sites that want to disable CSP entirely
	 * for a particular page can short-circuit by returning an empty array
	 * from the filter — `output_native_mode_csp()` is a no-op when there
	 * are no directives.
	 *
	 * @since 3.0.32
	 * @return array<string, string[]> Directive => list of source expressions.
	 */
	public function funnel_native_mode_csp_directives() {
		// CSP source list reuses the same patterns the script-host
		// allowlist returns (`*.foo.com`, `bar.foo.com`) so that an
		// administrator who extends one via filter does not have to keep
		// the two lists in sync. The CSP host-source grammar already
		// accepts `https://*.foo.com` as a valid wildcard, so this is a
		// straight pass-through with a scheme prefix.
		$script_hosts = $this->funnel_allowed_script_hosts();

		$to_sources = static function ( array $hosts ) {
			$out = array();
			foreach ( $hosts as $host ) {
				$host = strtolower( trim( (string) $host ) );
				if ( '' === $host ) {
					continue;
				}
				$out[] = 'https://' . $host;
			}
			return $out;
		};

		// Inline JS and `eval` are required by LC's Vue runtime, Stripe.js
		// (Stripe wraps payment-method tokenization in `new Function()`),
		// and most tag managers. Disabling them would break payment flows
		// in native mode, which is the explicit reason the funnel is
		// rendered natively in the first place.
		$script_src = array_merge(
			array( "'self'", "'unsafe-inline'", "'unsafe-eval'" ),
			$to_sources( $script_hosts )
		);

		// Style sources are intentionally permissive (`https:`). Funnel
		// pages routinely pull stylesheets from a long tail of
		// vendor-chosen font / icon CDNs (Bunny Fonts, Google Fonts,
		// Stripe Elements, payment-processor checkouts, etc.) and CSS
		// has no script-execution surface in modern browsers. The
		// dangerous CSS payloads (`expression(…)`, `behavior:`,
		// `-moz-binding`, `javascript:` URIs in `url()`) are already
		// neutralized by `sanitize_extracted_inline_css()` before any
		// remote CSS is enqueued, so this is defense-in-depth, not the
		// primary control. `'unsafe-inline'` is required for the inline
		// styles funnels emit on individual elements.
		$style_src = array( "'self'", "'unsafe-inline'", 'https:' );

		// Image / font / media sources also use `https:`. Funnel images
		// come from `images.leadconnectorhq.com`,
		// `assets.cdn.filesafe.space`, the merchant's own CDN, plus any
		// third-party preview / thumbnail provider the page embeds.
		// Restricting these to an explicit allowlist breaks every funnel
		// with a non-LC image and provides effectively zero security
		// benefit (images cannot execute script).
		$img_src   = array( "'self'", 'data:', 'blob:', 'https:' );
		$font_src  = array( "'self'", 'data:', 'https:' );
		$media_src = array( "'self'", 'data:', 'blob:', 'https:' );

		// `connect-src` controls XHR / fetch / WebSocket / EventSource
		// targets. LC's Vue runtime hits a wide variety of LC subdomains
		// (api, services, backend, widgets, etc.) at runtime, and
		// payment processors hit api.stripe.com, m.stripe.com, q.stripe.com,
		// PayPal, etc. The wildcard list above already covers all of
		// these, so we mirror the script-host allowlist with the addition
		// of `https:` as a safety net for the long tail of vendor
		// telemetry / form endpoints. Removing the wildcard tightens
		// this materially.
		$connect_src = array_merge(
			array( "'self'", 'https:' ),
			$to_sources( $script_hosts )
		);

		// `frame-src` controls which origins may be loaded inside
		// `<iframe>` elements emitted by funnel content (Stripe Elements,
		// PayPal Smart Buttons, Apple Pay, embedded YouTube / Vimeo
		// videos, etc.). Keep the list scoped to the canonical embeds
		// funnels need.
		$frame_src = array(
			"'self'",
			'https://js.stripe.com',
			'https://hooks.stripe.com',
			'https://m.stripe.com',
			'https://m.stripe.network',
			'https://www.paypal.com',
			'https://*.paypal.com',
			'https://www.youtube.com',
			'https://www.youtube-nocookie.com',
			'https://player.vimeo.com',
			'https://www.google.com',
			'https://*.leadconnectorhq.com',
			'https://*.msgsndr.com',
			'https://challenges.cloudflare.com',
			'https://*.reputationhub.site',
			'https://reputationhub.site',
		);

		// `form-action` keeps funnel form POSTs scoped to LC. Funnels
		// submit leads and payments to LC endpoints; off-allowlist
		// targets are almost always a sign of injection.
		$form_action = array_merge(
			array( "'self'" ),
			$to_sources( $script_hosts )
		);

		$directives = array(
			'default-src'     => array( "'self'" ),
			'script-src'      => $script_src,
			'style-src'       => $style_src,
			'img-src'         => $img_src,
			'font-src'        => $font_src,
			'connect-src'     => $connect_src,
			'frame-src'       => $frame_src,
			'media-src'       => $media_src,
			// Hard "no" for Flash / Java applet / `<object>` content. We
			// never need to load those for funnels and they're a XSS
			// escape hatch.
			'object-src'      => array( "'none'" ),
			// Clickjacking guard: refuse to be framed off-origin.
			'frame-ancestors' => array( "'self'" ),
			// Block `<base href>` smuggling that could rewrite every
			// relative URL on the document to an attacker-controlled host.
			'base-uri'        => array( "'self'" ),
			'form-action'     => $form_action,
		);

		/**
		 * Filters the native-mode CSP directive map before emission.
		 *
		 * Hook into this to extend (or completely replace) the policy.
		 * Return an empty array to skip emission of the CSP `<meta>` tag
		 * for the current request.
		 *
		 * @since 3.0.32
		 * @param array<string, string[]> $directives Directive => sources map.
		 */
		$directives = apply_filters( 'leadconnector_native_mode_csp_directives', $directives );

		return is_array( $directives ) ? $directives : array();
	}

	/**
	 * Build the native-mode CSP `<meta>` tag as an escaped HTML string.
	 *
	 * Returns the empty string when the directive filter zeroed out the
	 * policy. Caller is responsible for echoing the result; this is
	 * already wp_kses-sanitized against a minimal `meta` allowlist.
	 *
	 * @since 3.0.32
	 * @return string Escaped `<meta>` HTML, or empty string.
	 */
	public function get_native_mode_csp_meta_html() {
		$directives = $this->funnel_native_mode_csp_directives();
		if ( empty( $directives ) ) {
			return '';
		}

		$parts = array();
		foreach ( $directives as $directive => $sources ) {
			$directive = is_string( $directive ) ? trim( $directive ) : '';
			if ( '' === $directive || ! is_array( $sources ) ) {
				continue;
			}

			$clean_sources = array();
			foreach ( $sources as $source ) {
				$source = trim( (string) $source );
				if ( '' !== $source ) {
					$clean_sources[ $source ] = true;
				}
			}
			if ( empty( $clean_sources ) ) {
				continue;
			}

			$parts[] = $directive . ' ' . implode( ' ', array_keys( $clean_sources ) );
		}

		if ( empty( $parts ) ) {
			return '';
		}

		$policy = implode( '; ', $parts );

		// wp_kses with a minimal `meta` allowlist is the WP.org-recommended
		// pattern for echoing a `<meta>` tag. esc_attr() on the policy
		// string defangs any future filter callback that returns a stray
		// quote / angle bracket in a source expression.
		$meta_html = sprintf(
			'<meta http-equiv="Content-Security-Policy" content="%s">' . "\n",
			esc_attr( $policy )
		);

		$allowed = array(
			'meta' => array(
				'http-equiv' => true,
				'content'    => true,
			),
		);

		return wp_kses( $meta_html, $allowed );
	}

	/**
	 * Echo the native-mode Content-Security-Policy as an HTML `<meta>` tag.
	 *
	 * Emitted as a `<meta http-equiv="Content-Security-Policy" content="…">`
	 * because the plugin must not assume the ability to write HTTP response
	 * headers (most hosts emit headers before `template_redirect` fires,
	 * and several layered cache plugins strip plugin-emitted `header()`
	 * calls). The CSP `<meta>` form is normative HTML5 and is honored by
	 * every evergreen browser the funnel checkout flows target.
	 *
	 * Must be called as early as possible inside `<head>` (we register
	 * the wp_head callback at priority `1`) so that browsers apply the
	 * policy to every subsequent stylesheet, script, image, font, and
	 * XHR the funnel content tries to load.
	 *
	 * @since 3.0.32
	 * @return void
	 */
	public function output_native_mode_csp() {
		$meta_html = $this->get_native_mode_csp_meta_html();
		if ( '' === $meta_html ) {
			return;
		}

		// The string is already wp_kses-sanitized inside
		// get_native_mode_csp_meta_html() so it is safe to echo as-is. We
		// avoid a second wp_kses pass at the echo site because that would
		// re-collapse the policy whitespace and break some legitimate
		// source expressions (e.g. `nonce-…`) that future filters might
		// register.
		echo $meta_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_native_mode_csp_meta_html() returns a wp_kses-sanitized string.
	}

	/**
	 * Insert the native-mode CSP `<meta>` tag immediately after the first
	 * `<head>` opening tag inside a chunk of HTML markup.
	 *
	 * Used by the standalone native-render path where there is no
	 * `wp_head` action to hook into. Falls back to prepending the meta
	 * tag if no `<head>` opening tag is found (degraded, but still
	 * applies to anything the browser parses afterwards).
	 *
	 * @since 3.0.32
	 * @param string $html HTML chunk containing the upstream `<head>`.
	 * @return string $html with the CSP `<meta>` injected.
	 */
	public function inject_native_mode_csp_into_head_html( $html ) {
		$meta_html = $this->get_native_mode_csp_meta_html();
		if ( '' === $meta_html ) {
			return $html;
		}

		// Insert the CSP meta tag right after the first `<head ...>`.
		$injected = preg_replace(
			'/(<head\b[^>]*>)/i',
			'$1' . "\n" . $meta_html,
			(string) $html,
			1,
			$count
		);

		if ( null === $injected || 0 === (int) $count ) {
			// No `<head>` to splice into — prepend so the browser sees
			// the policy before parsing any subsequent fetch-triggering
			// element.
			return $meta_html . (string) $html;
		}

		return $injected;
	}

	/**
	 * Output previously extracted scripts using WordPress script APIs.
	 *
	 * External non-module scripts are enqueued via wp_enqueue_script().
	 * ES module scripts and inline scripts are output via
	 * wp_print_script_tag() / wp_print_inline_script_tag() (WP 5.7+)
	 * which are the WordPress.org-accepted APIs for printing script tags.
	 *
	 * External `<script src>` URLs are gated on `funnel_allowed_script_hosts()`
	 * (3.0.32) so a future LeadConnector funnel that referenced a script
	 * from an off-allowlist host cannot transparently inject remote JS
	 * into the WordPress origin. Rejected `src` entries are dropped; if
	 * the same script record also carried an inline body, the inline body
	 * is dropped too because it was authored to pair with the rejected
	 * external script. Inline-only scripts pass through (no src host to
	 * check) and are emitted via `wp_print_inline_script_tag()` — see
	 * the native-mode trust-boundary section of `README.txt` for the
	 * documented rationale.
	 *
	 * Should be called inside a wp_head or wp_footer action.
	 *
	 * @param array  $scripts  Array of script info from extract_scripts_from_html().
	 * @param string $position 'head' or 'footer' — determines where scripts load.
	 */
	public function output_extracted_scripts( $scripts, $position = 'footer' ) {
		if ( empty( $scripts ) ) {
			return;
		}

		$in_footer  = ( 'footer' === $position );
		static $idx = 0;

		foreach ( $scripts as $script ) {
			++$idx;
			$is_module = ( 'module' === $script['type'] );
			$handle    = 'leadconnector-funnel-script-' . $idx;

			if ( ! empty( $script['src'] ) ) {
				// 3.0.32: host-allowlist gate. Skip the entire script record
				// (and its paired inline body) when the src host is not on
				// the funnel allowlist — this is the primary control that
				// keeps native mode WP.org-defensible without breaking
				// payment processor integrations.
				if ( ! $this->is_funnel_script_src_allowed( $script['src'] ) ) {
					continue;
				}

				if ( $is_module || ! empty( $script['attrs'] ) ) {
					// ES module or script with special attributes (crossorigin, etc.)
					// — output directly via wp_print_script_tag since wp_enqueue_script
					// doesn't handle module type or crossorigin.
					$tag_attrs = array(
						'src' => esc_url( $script['src'] ),
					);
					if ( $is_module ) {
						$tag_attrs['type'] = 'module';
					}
					foreach ( $script['attrs'] as $ak => $av ) {
						if ( 'id' === $ak ) {
							$tag_attrs['id'] = $av;
						} else {
							$tag_attrs[ $ak ] = $av;
						}
					}
					wp_print_script_tag( $tag_attrs );
				} else {
					// Plain external script — use standard WP enqueue.
					wp_enqueue_script(
						$handle,
						esc_url( $script['src'] ),
						array(),
						$this->version,
						$in_footer
					);
				}

				// If the external script also has inline content, append it.
				if ( ! empty( $script['content'] ) ) {
					wp_print_inline_script_tag( $script['content'], $is_module ? array( 'type' => 'module' ) : array() );
				}
			} elseif ( ! empty( $script['content'] ) ) {
				// Inline-only script — use wp_print_inline_script_tag (WP 5.7+).
				$tag_attrs = array();
				if ( 'text/javascript' !== $script['type'] ) {
					$tag_attrs['type'] = $script['type'];
				}
				foreach ( $script['attrs'] as $ak => $av ) {
					$tag_attrs[ $ak ] = $av;
				}
				wp_print_inline_script_tag( $script['content'], $tag_attrs );
			}
		}
	}

	/**
	 * Render previously extracted scripts as a sanitized HTML string.
	 *
	 * Used by callers that build a standalone HTML response document and need
	 * to splice script tags into the document body without going through
	 * wp_kses() with <script> in the allowlist. Each script is rendered using
	 * the WordPress core helpers wp_get_script_tag() / wp_get_inline_script_tag(),
	 * which apply attribute escaping (esc_url for src, esc_attr for other
	 * attributes) and route through the wp_inline_script_attributes filter the
	 * same way wp_print_inline_script_tag() does. The returned string is
	 * therefore equivalent in safety to what those print helpers would emit;
	 * it is intentionally not run through wp_kses() afterwards because kses
	 * cannot validate JS content and would either strip the entire <script>
	 * tag (when scripts are removed from the allowlist, per H5) or
	 * text-escape the JS body and corrupt it.
	 *
	 * @since 3.0.30
	 * @param array $scripts Array of script info records produced by
	 *                       extract_scripts_from_html().
	 * @return string Concatenated <script> HTML; empty string when $scripts is empty.
	 */
	public function render_extracted_scripts_html( $scripts ) {
		if ( empty( $scripts ) ) {
			return '';
		}

		$html = '';

		foreach ( $scripts as $script ) {
			$type      = isset( $script['type'] ) ? (string) $script['type'] : 'text/javascript';
			$is_module = ( 'module' === $type );
			$content   = isset( $script['content'] ) ? (string) $script['content'] : '';
			$src       = isset( $script['src'] ) ? (string) $script['src'] : '';
			$attrs     = isset( $script['attrs'] ) && is_array( $script['attrs'] ) ? $script['attrs'] : array();

			if ( '' !== $src ) {
				// 3.0.32: host-allowlist gate. Same logic as
				// output_extracted_scripts() — see funnel_allowed_script_hosts().
				// Drop the entire record (including any paired inline body
				// authored to run alongside the rejected src).
				if ( ! $this->is_funnel_script_src_allowed( $src ) ) {
					continue;
				}

				$tag_attrs = array(
					'src' => esc_url( $src ),
				);
				if ( $is_module ) {
					$tag_attrs['type'] = 'module';
				}
				foreach ( $attrs as $ak => $av ) {
					$tag_attrs[ $ak ] = $av;
				}
				$html .= wp_get_script_tag( $tag_attrs );

				if ( '' !== $content ) {
					$html .= wp_get_inline_script_tag(
						$content,
						$is_module ? array( 'type' => 'module' ) : array()
					);
				}
				continue;
			}

			if ( '' !== $content ) {
				$tag_attrs = array();
				if ( 'text/javascript' !== $type ) {
					$tag_attrs['type'] = $type;
				}
				foreach ( $attrs as $ak => $av ) {
					$tag_attrs[ $ak ] = $av;
				}
				$html .= wp_get_inline_script_tag( $content, $tag_attrs );
			}
		}

		return $html;
	}


	/**
	 * Allowed-HTML map for funnel body content rendered inside the_content.
	 *
	 * Exposed publicly so callers can pass the array directly into wp_kses()
	 * at the echo site (the WordPress.org-recommended escaping pattern).
	 *
	 * @return array<string,array<string,bool>>
	 */
	public function native_body_allowed_tags() {
		$allowed = wp_kses_allowed_html( 'post' );

		$body_extras = array(
			'style'          => array(
				'type'  => true,
				'media' => true,
			),
			'iframe'         => array(
				'src'             => true,
				'srcdoc'          => true,
				'width'           => true,
				'height'          => true,
				'frameborder'     => true,
				'allowfullscreen' => true,
				'allow'           => true,
				'loading'         => true,
				'name'            => true,
				'sandbox'         => true,
				'referrerpolicy'  => true,
			),
			'video'          => array(
				'src'         => true,
				'width'       => true,
				'height'      => true,
				'controls'    => true,
				'autoplay'    => true,
				'loop'        => true,
				'muted'       => true,
				'poster'      => true,
				'preload'     => true,
				'playsinline' => true,
				'crossorigin' => true,
			),
			'source'         => array(
				'src'    => true,
				'srcset' => true,
				'sizes'  => true,
				'type'   => true,
				'media'  => true,
			),
			'audio'          => array(
				'src'         => true,
				'controls'    => true,
				'autoplay'    => true,
				'loop'        => true,
				'muted'       => true,
				'preload'     => true,
				'crossorigin' => true,
			),
			'form'           => array(
				'action'         => true,
				'method'         => true,
				'name'           => true,
				'target'         => true,
				'enctype'        => true,
				'accept-charset' => true,
				'autocomplete'   => true,
				'novalidate'     => true,
				'rel'            => true,
			),
			'input'          => array(
				'type'           => true,
				'name'           => true,
				'value'          => true,
				'placeholder'    => true,
				'required'       => true,
				'checked'        => true,
				'disabled'       => true,
				'readonly'       => true,
				'maxlength'      => true,
				'minlength'      => true,
				'min'            => true,
				'max'            => true,
				'step'           => true,
				'pattern'        => true,
				'autocomplete'   => true,
				'autofocus'      => true,
				'multiple'       => true,
				'accept'         => true,
				'src'            => true,
				'alt'            => true,
				'list'           => true,
				'form'           => true,
				'formaction'     => true,
				'formenctype'    => true,
				'formmethod'     => true,
				'formnovalidate' => true,
				'formtarget'     => true,
				'inputmode'      => true,
				'size'           => true,
				'width'          => true,
				'height'         => true,
			),
			'select'         => array(
				'name'         => true,
				'multiple'     => true,
				'required'     => true,
				'disabled'     => true,
				'autofocus'    => true,
				'autocomplete' => true,
				'form'         => true,
				'size'         => true,
			),
			'option'         => array(
				'value'    => true,
				'selected' => true,
				'disabled' => true,
				'label'    => true,
			),
			'optgroup'       => array(
				'label'    => true,
				'disabled' => true,
			),
			'textarea'       => array(
				'name'         => true,
				'rows'         => true,
				'cols'         => true,
				'placeholder'  => true,
				'required'     => true,
				'disabled'     => true,
				'readonly'     => true,
				'maxlength'    => true,
				'minlength'    => true,
				'autocomplete' => true,
				'autofocus'    => true,
				'wrap'         => true,
				'form'         => true,
			),
			'picture'        => array(),
			'svg'            => array(
				'xmlns'               => true,
				'xmlns:xlink'         => true,
				'viewbox'             => true,
				'viewBox'             => true,
				'width'               => true,
				'height'              => true,
				'fill'                => true,
				'stroke'              => true,
				'preserveAspectRatio' => true,
				'aria-hidden'         => true,
				'focusable'           => true,
			),
			'path'           => array(
				'd'               => true,
				'fill'            => true,
				'fill-rule'       => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'opacity'         => true,
				'transform'       => true,
			),
			'g'              => array(
				'fill'      => true,
				'stroke'    => true,
				'transform' => true,
				'opacity'   => true,
			),
			'circle'         => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'rect'           => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'stroke' => true,
			),
			'line'           => array(
				'x1'           => true,
				'y1'           => true,
				'x2'           => true,
				'y2'           => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'polyline'       => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
			),
			'polygon'        => array(
				'points' => true,
				'fill'   => true,
				'stroke' => true,
			),
			'ellipse'        => array(
				'cx'     => true,
				'cy'     => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'stroke' => true,
			),
			'text'           => array(
				'x'           => true,
				'y'           => true,
				'fill'        => true,
				'font-size'   => true,
				'font-family' => true,
				'text-anchor' => true,
			),
			'tspan'          => array(
				'x'    => true,
				'y'    => true,
				'dx'   => true,
				'dy'   => true,
				'fill' => true,
			),
			'defs'           => array(),
			'use'            => array(
				'href'       => true,
				'xlink:href' => true,
				'x'          => true,
				'y'          => true,
				'width'      => true,
				'height'     => true,
			),
			'symbol'         => array(
				'viewBox' => true,
				'viewbox' => true,
			),
			'mask'           => array( 'maskUnits' => true ),
			'clippath'       => array(),
			'lineargradient' => array(
				'x1'            => true,
				'y1'            => true,
				'x2'            => true,
				'y2'            => true,
				'gradientUnits' => true,
			),
			'stop'           => array(
				'offset'       => true,
				'stop-color'   => true,
				'stop-opacity' => true,
			),
		);

		foreach ( $body_extras as $tag => $attrs ) {
			$allowed[ $tag ] = isset( $allowed[ $tag ] ) ? array_merge( $allowed[ $tag ], $attrs ) : $attrs;
		}

		// Merge HTML5 structural / interactive / embedded tags missing from 'post'.
		foreach ( $this->leadconnector_html5_extra_tags() as $tag => $attrs ) {
			$allowed[ $tag ] = isset( $allowed[ $tag ] ) ? array_merge( $allowed[ $tag ], $attrs ) : $attrs;
		}

		// Apply the global attribute set (class/id/style/role/lang/data-*/aria-*) to every tag.
		return $this->leadconnector_merge_global_html_attributes( $allowed );
	}


	/**
	 * Allowed-HTML map for a full standalone native HTML document.
	 *
	 * Exposed publicly so callers can pass the array directly into wp_kses()
	 * at the echo site (the WordPress.org-recommended escaping pattern).
	 *
	 * @return array<string,array<string,bool>>
	 */
	public function native_full_page_allowed_tags() {
		// Start from the body allowlist (which already includes HTML5 layout
		// tags, forms, media, SVG, and global attributes via the helpers).
		$allowed = $this->native_body_allowed_tags();

		// Layer the head allowlist on top so <meta>, <link>, <style>,
		// <title>, <base>, <noscript> are also valid in the document.
		foreach ( $this->funnel_html_allowed_tags() as $tag => $attrs ) {
			$allowed[ $tag ] = isset( $allowed[ $tag ] ) ? array_merge( $allowed[ $tag ], $attrs ) : $attrs;
		}

		// Add structural document tags (html / head / body) that aren't
		// valid in either of the fragment-level allowlists above. Event
		// handler attributes (onload, onclick, etc.) are deliberately
		// omitted so kses strips them.
		$structural = array(
			'html' => array(
				'xmlns'  => true,
				'prefix' => true,
			),
			'head' => array( 'profile' => true ),
			'body' => array(),
		);
		foreach ( $structural as $tag => $attrs ) {
			$allowed[ $tag ] = isset( $allowed[ $tag ] ) ? array_merge( $allowed[ $tag ], $attrs ) : $attrs;
		}

		// Re-apply globals so the structural document tags get them too.
		return $this->leadconnector_merge_global_html_attributes( $allowed );
	}

	/**
	 * Add native head content via wp_head action
	 *
	 * @param string $funnel_step_url The funnel step URL.
	 * @param string $leadconnector_post_meta Post meta data.
	 * @param string $leadconnector_step_tracking_code Step tracking code.
	 * @param string $leadconnector_funnel_tracking_code Funnel tracking code.
	 * @param bool   $leadconnector_use_site_favicon Whether to use site favicon.
	 */
	private function add_native_head_content( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon ) {
		add_action(
			'wp_head',
			function () use ( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon ) {
				// Get native HTML and extract head content.
				$native_html_content = $this->get_page_iframe_native( $funnel_step_url, $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code, $leadconnector_use_site_favicon );

				if ( ! empty( $native_html_content ) ) {
					$this->output_head_content( $native_html_content );
				}

				// Add meta fields and tracking codes.
				$this->output_meta_and_tracking( $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code );

				// Add favicon.
				$this->output_favicon( $leadconnector_use_site_favicon );

				// Add styling for full-width display.
				$this->output_native_page_styles();
			},
			10
		);
	}

	/**
	 * Output head content from native HTML
	 *
	 * Uses regex-based extraction to preserve JavaScript and HTML5 elements.
	 * DOMDocument corrupts inline JavaScript which breaks Lead Connector's config (baseURL, etc.)
	 *
	 * @param string $html_content The full HTML content.
	 */
	private function output_head_content( $html_content ) {

		// Extract HEAD content using regex to preserve JavaScript exactly as-is
		// DOMDocument corrupts inline JS which breaks Lead Connector's configuration.
		if ( preg_match( '/<head[^>]*>(.*?)<\/head>/is', $html_content, $head_matches ) ) {
			$head_content = $head_matches[1];

			// Remove title tags as WordPress handles those.
			$head_content = preg_replace( '/<title[^>]*>.*?<\/title>/is', '', $head_content );

			// Separate scripts and stylesheets BEFORE wp_kses(). kses keeps
			// the <script> and <style> tags but text-escapes their bodies,
			// which corrupts inline JS (`<` becomes `&lt;`) and CSS selectors
			// (`>` becomes `&gt;` — breaking the desktop two-column layout
			// for funnel rows that use `.c-row > .inner`). Re-emitting these
			// via the WP script/style APIs preserves the source exactly.
			$script_parts = $this->extract_scripts_from_html( $head_content );
			$style_parts  = $this->extract_styles_from_html( $script_parts['html'] );
			echo wp_kses( $style_parts['html'], $this->funnel_html_allowed_tags() );
			$this->output_extracted_styles( $style_parts['styles'] );
			$this->output_extracted_scripts( $script_parts['scripts'], 'head' );

			// wp_kses strips onload="this.media='all'" from font <link> tags.
			// That attribute is a standard non-blocking font-swap technique: the
			// stylesheet loads with media="print", then onload flips it to "all".
			// Re-implement the same behaviour with a small script.
			$this->output_font_swap_script();
		}
	}

	/**
	 * Output a small inline script that activates print-only font stylesheets.
	 *
	 * Funnel pages use the pattern:
	 *   <link rel="stylesheet" href="fonts.googleapis.com/..." media="print" onload="this.media='all'">
	 * wp_kses correctly strips the onload handler (event handlers are unsafe).
	 * This script restores the intended behaviour: once the stylesheet loads
	 * (media="print" still triggers a background fetch), switch it to all media.
	 *
	 * Uses wp_print_inline_script_tag() — the WordPress 5.7+ official API for
	 * printing inline script tags from PHP. The plugin's "Requires at least"
	 * header is 5.7, so the function is always available; the previous
	 * function_exists() guard has been removed. This API is preferred over
	 * raw <script> echoes because it routes through the
	 * wp_inline_script_attributes filter and applies CSP-aware attribute
	 * handling.
	 *
	 * This method runs inside the wp_head callback at priority 10 (after
	 * wp_print_head_scripts at priority 9), so we deliberately print inline
	 * at the current document position rather than calling wp_enqueue_script()
	 * — which would defer this script to wp_footer and let the font-swap
	 * run after the page has rendered with print-only fonts.
	 */
	private function output_font_swap_script() {
		$js = "document.querySelectorAll('link[rel=\"stylesheet\"][media=\"print\"]').forEach(function(l){"
			. "if(l.href&&l.href.indexOf('fonts.')!==-1){"
			. "l.onload=function(){this.media='all'};"
			. "if(l.sheet)l.media='all';"
			. '}});';

		wp_print_inline_script_tag( $js );
	}

	/**
	 * Output meta fields and tracking codes in head
	 *
	 * @param string $leadconnector_post_meta Post meta data.
	 * @param string $leadconnector_step_tracking_code Step tracking code.
	 * @param string $leadconnector_funnel_tracking_code Funnel tracking code.
	 */
	private function output_meta_and_tracking( $leadconnector_post_meta, $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code ) {
		$post_meta_fields    = $this->get_meta_fields( $leadconnector_post_meta );
		$head_tracking_code  = $this->get_tracking_code( $leadconnector_funnel_tracking_code, true, true );
		$head_tracking_code .= $this->get_tracking_code( $leadconnector_step_tracking_code, true );

		if ( ! empty( $post_meta_fields ) ) {
			echo wp_kses( $post_meta_fields, $this->funnel_html_allowed_tags() );
		}

		// H5: <script> is no longer in funnel_html_allowed_tags(), so the
		// tracking code's analytics/GTM/pixel scripts must be separated and
		// re-emitted via the WordPress script API rather than being passed
		// through wp_kses() verbatim. The non-script remainder (noscript
		// fallbacks, tracking pixels, comments) still flows through kses.
		if ( ! empty( $head_tracking_code ) ) {
			$tracking_parts = $this->extract_scripts_from_html( $head_tracking_code );
			echo wp_kses( $tracking_parts['html'], $this->funnel_html_allowed_tags() );
			$this->output_extracted_scripts( $tracking_parts['scripts'], 'head' );
		}
	}

	/**
	 * Output favicon if enabled
	 *
	 * @param bool $leadconnector_use_site_favicon Whether to use site favicon.
	 */
	private function output_favicon( $leadconnector_use_site_favicon ) {
		if ( $leadconnector_use_site_favicon ) {
			$favicon_url = get_site_icon_url();
			if ( $favicon_url ) {
				$link_allowed = array(
					'link' => array(
						'rel'  => true,
						'type' => true,
						'href' => true,
					),
				);
				echo wp_kses( '<link rel="icon" type="image/x-icon" href="' . esc_url( $favicon_url ) . '">', $link_allowed );
			}
		}
	}

	/**
	 * Output native page styles for full-width display.
	 */
	private function output_native_page_styles() {
	}

	/**
	 * Add footer tracking codes
	 *
	 * @param string $leadconnector_step_tracking_code Step tracking code.
	 * @param string $leadconnector_funnel_tracking_code Funnel tracking code.
	 */
	private function add_native_footer_tracking( $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code ) {
		add_action(
			'wp_footer',
			function () use ( $leadconnector_step_tracking_code, $leadconnector_funnel_tracking_code ) {
				$footer_tracking_code  = $this->get_tracking_code( $leadconnector_funnel_tracking_code, false, true );
				$footer_tracking_code .= $this->get_tracking_code( $leadconnector_step_tracking_code, false );
				if ( ! empty( $footer_tracking_code ) ) {
					// H5: extract <script> tags before wp_kses() so the kses
					// allowlist no longer needs to include 'script'. The
					// non-script remainder (noscript pixels, tracking comments)
					// still passes through kses; scripts are re-emitted via
					// the WordPress script API.
					$tracking_parts = $this->extract_scripts_from_html( $footer_tracking_code );
					echo wp_kses( $tracking_parts['html'], $this->funnel_html_allowed_tags() );
					$this->output_extracted_scripts( $tracking_parts['scripts'], 'footer' );
				}
			},
			10
		);
	}

	/**
	 * Add body class for native pages
	 */
	private function add_native_body_class() {
		add_filter(
			'body_class',
			function ( $classes ) {
				$classes[] = 'leadconnector-native-page';
				return $classes;
			}
		);
	}

	/**
	 * Performs the same remote purge as the "Purge everything on all domains" admin bar action.
	 *
	 * @return bool True when LeadConnector services report success; false on error, missing OAuth, or misconfiguration.
	 */
	private function purge_everything_on_all_domains() {
		$response = $this->leadconnector_wp_get( 'wp_purge_all_domains_cache' );
		return is_array( $response ) && isset( $response['error'] ) && false === $response['error'];
	}

	/**
	 * Conditionally clear caches on save_post, skipping autosaves,
	 * revisions, and non-public post types to avoid excessive cache purges.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function maybe_clear_all_caches( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$public_types = get_post_types( array( 'public' => true ) );
		if ( ! in_array( $post->post_type, $public_types, true ) ) {
			return;
		}

		$this->clear_all_caches();
	}

	/**
	 * Clear cache from popular caching plugins
	 * This ensures that changes (like widget enable/disable) are reflected immediately
	 *
	 * @since 1.0.0
	 * @return array Array of results with plugin names and success status
	 */
	public function clear_all_caches() {
		$results = array();

		// Define cache clearing methods for popular plugins
		// Easy to add more plugins to this array.
		$cache_plugins = array(
			// 1. WP Rocket
			'wp_rocket'            => array(
				'name'  => 'WP Rocket',
				'check' => function () {
					return function_exists( 'rocket_clean_domain' );
				},
				'clear' => function () {
					rocket_clean_domain();
					// Also clear minified CSS and JS.
					if ( function_exists( 'rocket_clean_minify' ) ) {
						rocket_clean_minify();
					}
				},
			),

			// 2. W3 Total Cache
			'w3_total_cache'       => array(
				'name'  => 'W3 Total Cache',
				'check' => function () {
					return function_exists( 'w3tc_flush_all' );
				},
				'clear' => function () {
					w3tc_flush_all();
				},
			),

			// 4. LiteSpeed Cache
			'litespeed_cache'      => array(
				'name'  => 'LiteSpeed Cache',
				'check' => function () {
					return class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' );
				},
				'clear' => function () {
					LiteSpeed\Purge::purge_all();
				},
			),

			// 5. WP Fastest Cache
			'wp_fastest_cache'     => array(
				'name'  => 'WP Fastest Cache',
				'check' => function () {
					return class_exists( 'WpFastestCache' );
				},
				'clear' => function () {
					if ( class_exists( 'WpFastestCache' ) ) {
						$wpfc = new WpFastestCache();
						$wpfc->deleteCache();
					}
				},
			),

			// 6. Cache Enabler
			'cache_enabler'        => array(
				'name'  => 'Cache Enabler',
				'check' => function () {
					return class_exists( 'Cache_Enabler' );
				},
				'clear' => function () {
					if ( class_exists( 'Cache_Enabler' ) ) {
						Cache_Enabler::clear_total_cache();
					}
				},
			),

			// 7. Comet Cache
			'comet_cache'          => array(
				'name'  => 'Comet Cache',
				'check' => function () {
					return class_exists( 'comet_cache' );
				},
				'clear' => function () {
					if ( class_exists( 'comet_cache' ) ) {
						comet_cache::clear();
					}
				},
			),

			// 8. Hummingbird
			'hummingbird'          => array(
				'name'  => 'Hummingbird',
				'check' => function () {
					return class_exists( 'Hummingbird\WP_Hummingbird' ) && method_exists( 'Hummingbird\WP_Hummingbird', 'flush_cache' );
				},
				'clear' => function () {
					Hummingbird\WP_Hummingbird::flush_cache();
				},
			),

			// 9. SiteGround Optimizer
			'siteground_optimizer' => array(
				'name'  => 'SiteGround Optimizer',
				'check' => function () {
					return function_exists( 'sg_cachepress_purge_cache' );
				},
				'clear' => function () {
					sg_cachepress_purge_cache();
				},
			),

			// 10. Autoptimize
			'autoptimize'          => array(
				'name'  => 'Autoptimize',
				'check' => function () {
					return class_exists( 'autoptimizeCache' );
				},
				'clear' => function () {
					if ( class_exists( 'autoptimizeCache' ) ) {
						autoptimizeCache::clearall();
					}
				},
			),

			// 11. Swift Performance
			'swift_performance'    => array(
				'name'  => 'Swift Performance',
				'check' => function () {
					return class_exists( 'Swift_Performance_Cache' );
				},
				'clear' => function () {
					if ( class_exists( 'Swift_Performance_Cache' ) ) {
						Swift_Performance_Cache::clear_all_cache();
					}
				},
			),

			// 12. Breeze (Cloudways)
			'breeze'               => array(
				'name'  => 'Breeze',
				'check' => function () {
					return class_exists( 'Breeze_PurgeCache' );
				},
				'clear' => function () {
					if ( class_exists( 'Breeze_PurgeCache' ) ) {
						Breeze_PurgeCache::breeze_cache_flush();
					}
				},
			),

			// Registry template: each entry defines name, check, and clear callbacks.
		);

		// Try to clear cache for each detected plugin.
		foreach ( $cache_plugins as $key => $plugin ) {
			try {
				if ( call_user_func( $plugin['check'] ) ) {
					call_user_func( $plugin['clear'] );
					$results[ $key ] = array(
						'name'    => $plugin['name'],
						'success' => true,
						'message' => 'Cache cleared successfully',
					);
				}
			} catch ( Exception $e ) {
				$results[ $key ] = array(
					'name'    => $plugin['name'],
					'success' => false,
					'message' => 'Error: ' . $e->getMessage(),
				);
			}
		}

		// Remote purge (same as "Purge everything on all domains" in the CDN toolbar).
		$purge_everything_on_all_domains_ok = $this->purge_everything_on_all_domains();
		if ( $purge_everything_on_all_domains_ok ) {
			$results['purge_everything_on_all_domains'] = array(
				'name'    => 'Purge everything on all domains',
				'success' => true,
				'message' => __( 'Remote cache purge completed.', 'leadconnector' ),
			);
		}

		return $results;
	}

	/**
	 * Clear cache with admin notice feedback
	 * Wrapper function that can be called from hooks
	 *
	 * @since 1.0.0
	 */
	public function clear_cache_with_notice() {
		$results = $this->clear_all_caches();

		$cleared_plugins = array_filter(
			$results,
			function ( $result ) {
				return $result['success'];
			}
		);

		if ( ! empty( $cleared_plugins ) ) {
			$plugin_names = array_map(
				function ( $result ) {
					return $result['name'];
				},
				$cleared_plugins
			);

			$message = sprintf(
				'LeadConnector: Cache cleared for %d plugin(s): %s',
				count( $cleared_plugins ),
				implode( ', ', $plugin_names )
			);

			add_action(
				'admin_notices',
				function () use ( $message ) {
					$notice_allowed = array(
						'div' => array( 'class' => true ),
						'p'   => array(),
					);
					echo wp_kses( '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>', $notice_allowed );
				}
			);
		}

		return $results;
	}
}
