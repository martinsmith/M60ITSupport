<?php
/**
 * Integrations Validate Handler.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Integrations_Validate_Handler
 *
 * @since 1.45.0
 */
class UAEL_Integrations_Validate_Handler implements UAEL_Ability_Handler {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'integrations-validate';
	}

	/**
	 * Get registration args.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Validate API Key', 'uael' ),
			'description'         => __( 'Validate an integration API key for Google Places, Yelp, or Facebook.', 'uael' ),
			'category'            => 'uael-settings',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'source'  => array(
						'type'        => 'string',
						'description' => __( 'API source: "google", "yelp", or "facebook".', 'uael' ),
						'enum'        => array( 'google', 'yelp', 'facebook' ),
					),
					'api_key' => array(
						'type'        => 'string',
						'description' => __( 'The API key or access token to validate.', 'uael' ),
					),
				),
				'required'   => array( 'source', 'api_key' ),
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
	 * Execute: Validate an API key.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public function execute( $input ) {
		$source  = isset( $input['source'] ) ? sanitize_text_field( $input['source'] ) : '';
		$api_key = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';

		$allowed_sources = array( 'google', 'yelp', 'facebook' );
		if ( ! in_array( $source, $allowed_sources, true ) || empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Valid source (google, yelp, facebook) and api_key are required.', 'uael' ),
			);
		}

		if ( 'facebook' === $source ) {
			$response = \UltimateElementor\Classes\UAEL_Helper::facebook_token_authentication( $api_key );
			return array(
				'success' => 200 === $response,
				'message' => 200 === $response
					? __( 'Access Token authenticated successfully.', 'uael' )
					: __( 'Invalid Access Token.', 'uael' ),
			);
		}

		\UltimateElementor\Classes\UAEL_Helper::get_api_authentication( $source, $api_key );

		if ( 'google' === $source ) {
			$status = get_option( 'uael_google_api_status' );
			$valid  = in_array( $status, array( 'yes', 'yes-new' ), true );
			return array(
				'success' => $valid,
				'message' => $valid
					? __( 'Google API key authenticated successfully.', 'uael' )
					: __( 'Google API key validation failed.', 'uael' ),
			);
		}

		$status = get_option( 'uael_yelp_api_status' );
		return array(
			'success' => 'yes' === $status,
			'message' => 'yes' === $status
				? __( 'Yelp API key authenticated successfully.', 'uael' )
				: __( 'Yelp API key validation failed.', 'uael' ),
		);
	}
}
