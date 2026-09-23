<?php
/**
 * Integrations Get Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Integrations_Get_Handler
 *
 * @since 1.45.0
 */
class UAEL_Integrations_Get_Handler implements UAEL_Ability_Handler {

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
	 * Sensitive keys to mask in output.
	 *
	 * @var array
	 */
	private static $sensitive_keys = array(
		'google_api',
		'google_places_api',
		'yelp_api',
		'recaptcha_v3_key',
		'recaptcha_v3_secretkey',
		'google_client_id',
		'facebook_app_id',
		'facebook_app_secret',
		'uael_maxmind_geolocation_license_key',
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
		return 'integrations-get';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Integration Settings', 'uael' ),
			'description'         => __( 'Retrieve current integration settings including Google Maps API key, Yelp API key, reCAPTCHA keys, and other service configurations. Sensitive keys are masked.', 'uael' ),
			'category'            => 'uael-settings',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'settings' => array( 'type' => 'object' ),
				),
			),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'priority'    => 1.0,
					'destructive' => false,
					'idempotent'  => true,
				),
				'mcp'         => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		);
	}

	/**
	 * Execute: Get integration settings with sensitive keys masked.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$settings = \UltimateElementor\Classes\UAEL_Helper::get_admin_settings_option( '_uael_integration', array(), true );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$filtered = array();
		foreach ( self::$allowed_keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$filtered[ $key ] = $settings[ $key ];
			}
		}

		// Mask sensitive keys.
		foreach ( self::$sensitive_keys as $key ) {
			if ( isset( $filtered[ $key ] ) && is_string( $filtered[ $key ] ) && ! empty( $filtered[ $key ] ) ) {
				$value = $filtered[ $key ];
				if ( strlen( $value ) > 8 ) {
					$filtered[ $key ] = substr( $value, 0, 4 ) . str_repeat( '*', strlen( $value ) - 8 ) . substr( $value, -4 );
				} else {
					$filtered[ $key ] = str_repeat( '*', strlen( $value ) );
				}
			}
		}

		return array(
			'success'  => true,
			'settings' => $filtered,
		);
	}
}
