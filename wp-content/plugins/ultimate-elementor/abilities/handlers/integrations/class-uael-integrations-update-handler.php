<?php
/**
 * Integrations Update Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Integrations_Update_Handler
 *
 * @since 1.45.0
 */
class UAEL_Integrations_Update_Handler implements UAEL_Ability_Handler {

	/**
	 * Allowed integration setting keys.
	 *
	 * @var array
	 */
	private static $allowed_keys = array(
		'google_api',
		'developer_mode',
		'language',
		'google_places_api',
		'yelp_api',
		'recaptcha_v3_key',
		'recaptcha_v3_secretkey',
		'recaptcha_v3_score',
		'google_client_id',
		'facebook_app_id',
		'facebook_app_secret',
		'uael_share_button',
		'uael_maxmind_geolocation_license_key',
		'uael_maxmind_geolocation_db_path',
		'uael_twitter_feed_consumer_key',
		'uael_twitter_feed_consumer_secret',
		'instagram_app_id',
		'instagram_app_secret',
		'instagram_app_token',
		'cloudflare_turnstile_site_key',
		'cloudflare_turnstile_secret_key',
	);

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'integrations-update';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Update Integration Settings', 'uael' ),
			'description'         => __( 'Update integration settings such as Google Maps API key, Yelp API key, reCAPTCHA keys, and other service configurations.', 'uael' ),
			'category'            => 'uael-settings',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'settings' => array(
						'type'        => 'string',
						'description' => __( 'JSON string of settings to update. Example: {"google_api":"AIza...","yelp_api":"abc123"}', 'uael' ),
					),
				),
				'required'   => array( 'settings' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'priority'    => 2.0,
					'destructive' => false,
					'idempotent'  => false,
				),
				'mcp'         => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		);
	}

	/**
	 * Execute: Update integration settings.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$raw = isset( $input['settings'] ) ? $input['settings'] : array();

		// Accept both string (Angie) and array (MCP Adapter).
		if ( is_string( $raw ) ) {
			$settings = json_decode( $raw, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$settings = array();
			}
		} else {
			$settings = is_array( $raw ) ? $raw : array();
		}

		if ( empty( $settings ) ) {
			return array(
				'success' => false,
				'message' => __( 'No settings provided.', 'uael' ),
			);
		}

		$new_settings = array();

		foreach ( $settings as $key => $val ) {
			if ( ! in_array( $key, self::$allowed_keys, true ) ) {
				continue;
			}
			if ( ! is_scalar( $val ) ) {
				continue;
			}
			$new_settings[ $key ] = sanitize_text_field( $val );
		}

		if ( empty( $new_settings ) ) {
			return array(
				'success' => false,
				'message' => __( 'No valid settings provided.', 'uael' ),
			);
		}

		$existing = \UltimateElementor\Classes\UAEL_Helper::get_admin_settings_option( '_uael_integration', array(), true );
		if ( is_array( $existing ) ) {
			$new_settings = array_merge( $existing, $new_settings );
		}

		\UltimateElementor\Classes\UAEL_Helper::update_admin_settings_option( '_uael_integration', $new_settings, true );

		return array(
			'success' => true,
			'message' => __( 'Integration settings saved successfully.', 'uael' ),
		);
	}
}
