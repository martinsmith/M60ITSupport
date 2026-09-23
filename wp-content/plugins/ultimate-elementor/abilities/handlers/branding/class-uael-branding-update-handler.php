<?php
/**
 * Branding Update Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Branding_Update_Handler
 *
 * @since 1.45.0
 */
class UAEL_Branding_Update_Handler implements UAEL_Ability_Handler {

	/**
	 * Allowed branding keys.
	 *
	 * @var array
	 */
	private static $allowed_keys = array(
		'agency',
		'plugin',
		'replace_logo',
		'enable_knowledgebase',
		'knowledgebase_url',
		'enable_support',
		'support_url',
		'enable_beta_box',
		'enable_custom_tagline',
		'internal_help_links',
		'logo_url',
	);

	/**
	 * Keys that contain URLs.
	 *
	 * @var array
	 */
	private static $url_keys = array( 'knowledgebase_url', 'support_url', 'logo_url' );

	/**
	 * Nested keys that contain URLs.
	 *
	 * @var array
	 */
	private static $nested_url_keys = array(
		'agency' => array( 'author_url' ),
	);

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'branding-update';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Update Branding Settings', 'uael' ),
			'description'         => __( 'Update white-label branding settings such as plugin name, author name, description, and support URLs.', 'uael' ),
			'category'            => 'uael-settings',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'branding' => array(
						'type'        => 'string',
						'description' => __( 'JSON string of branding settings to update. Example: {"plugin":{"name":"My Plugin"},"agency":{"author":"My Agency"}}', 'uael' ),
					),
				),
				'required'   => array( 'branding' ),
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
	 * Execute: Update branding settings.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$raw = isset( $input['branding'] ) ? $input['branding'] : array();

		// Accept both string (Angie) and array (MCP Adapter).
		if ( is_string( $raw ) ) {
			$branding = json_decode( $raw, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$branding = array();
			}
		} else {
			$branding = is_array( $raw ) ? $raw : array();
		}

		if ( empty( $branding ) ) {
			return array(
				'success' => false,
				'message' => __( 'No branding settings provided.', 'uael' ),
			);
		}

		$stored_settings = \UltimateElementor\Classes\UAEL_Helper::get_white_labels();
		$new_settings    = array();

		foreach ( $branding as $key => $val ) {
			if ( ! in_array( $key, self::$allowed_keys, true ) ) {
				continue;
			}

			if ( is_array( $val ) ) {
				$nested_urls = isset( self::$nested_url_keys[ $key ] ) ? self::$nested_url_keys[ $key ] : array();

				foreach ( $val as $k => $v ) {
					if ( ! is_scalar( $v ) ) {
						continue;
					}
					if ( in_array( $k, $nested_urls, true ) ) {
						$new_settings[ $key ][ $k ] = esc_url_raw( $v );
					} else {
						$new_settings[ $key ][ $k ] = sanitize_text_field( $v );
					}
				}
			} else {
				if ( ! is_scalar( $val ) ) {
					continue;
				}
				if ( in_array( $key, self::$url_keys, true ) ) {
					$new_settings[ $key ] = esc_url_raw( $val );
				} else {
					$new_settings[ $key ] = sanitize_text_field( $val );
				}
			}
		}

		if ( empty( $new_settings ) ) {
			return array(
				'success' => false,
				'message' => __( 'No valid branding settings provided.', 'uael' ),
			);
		}

		if ( ! isset( $new_settings['agency']['hide_branding'] ) ) {
			$new_settings['agency']['hide_branding'] = false;
		}

		$new_settings = wp_parse_args( $new_settings, $stored_settings );

		\UltimateElementor\Classes\UAEL_Helper::update_admin_settings_option( '_uael_white_label', $new_settings, true );

		return array(
			'success' => true,
			'message' => __( 'Branding settings saved successfully.', 'uael' ),
		);
	}
}
