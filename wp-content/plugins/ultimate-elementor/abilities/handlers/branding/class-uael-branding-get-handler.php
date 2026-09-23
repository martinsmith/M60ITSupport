<?php
/**
 * Branding Get Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Branding_Get_Handler
 *
 * @since 1.45.0
 */
class UAEL_Branding_Get_Handler implements UAEL_Ability_Handler {

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
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'branding-get';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Branding Settings', 'uael' ),
			'description'         => __( 'Retrieve current white-label branding settings including plugin name, author, and support URLs.', 'uael' ),
			'category'            => 'uael-settings',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'branding' => array( 'type' => 'object' ),
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
	 * Execute: Get branding settings.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$branding = \UltimateElementor\Classes\UAEL_Helper::get_white_labels();

		if ( ! is_array( $branding ) ) {
			$branding = array();
		}

		$filtered = array();
		foreach ( self::$allowed_keys as $key ) {
			if ( isset( $branding[ $key ] ) ) {
				$filtered[ $key ] = $branding[ $key ];
			}
		}

		return array(
			'success'  => true,
			'branding' => $filtered,
		);
	}
}
