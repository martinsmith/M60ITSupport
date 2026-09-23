<?php
/**
 *
 * LeadConnector REST: proxy route `args` + `rest_dispatch_request` JSON/DTO hooks.
 *
 * @package LeadConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST route argument definitions, dispatch-time DTO binding and proxy-data sanitisation.
 *
 * @package LeadConnector
 */
final class LeadConnector_REST_Input_Guard {

	/** Every logical `$endpoint` value accepted by leadconnector_api_proxy when not in direct-remote mode. */
	private const PROXY_ALLOWED_ENDPOINT_SLUGS = array(
		'check_smtp_plugin_conflict',
		'clear_cached_custom_values',
		'forms_get_list',
		'funnels_get_details',
		'funnels_get_list',
		'get_calendar_groups',
		'get_calendars',
		'get_chat_widget',
		'get_chat_widgets',
		'get_custom_value_folders',
		'get_custom_values',
		'get_quizzes',
		'get_reviews_widgets',
		'get_surveys',
		'phone_numbers_get_list',
		'utils_can_do',
		'wp_clear_cron',
		'wp_delete_post',
		'wp_disable_email',
		'wp_disconnect',
		'wp_email_eligibility_check',
		'wp_enable_email',
		'wp_get_all_posts',
		'wp_get_cron_count',
		'wp_get_lc_options',
		'wp_insert_post',
		'wp_purge_all_domains_cache',
		'wp_regenerate_token',
		'wp_save_options',
		'wp_validate_auth_state',
		'wp_validate_oauth',
	);

	/**
	 * Query/body parameters for `/leadconnector_api/v1/proxy` (GET + POST). Uses Core REST arg sanitation + validation.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function proxy_query_args() {
		return array(
			'endpoint'        => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( __CLASS__, 'validate_proxy_endpoint_query_arg' ),
			),
			'direct_endpoint' => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_direct_endpoint_param' ),
			),
			'data'            => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_proxy_data_string' ),
			),
		);
	}

	/**
	 * Sanitize the proxy `data` query parameter to a trimmed string.
	 *
	 * @param mixed $value Raw param.
	 * @return string
	 */
	public static function sanitize_proxy_data_string( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return trim( $value );
	}

	/**
	 * Sanitize the `direct_endpoint` query parameter.
	 *
	 * @param mixed $value Raw param.
	 * @return string
	 */
	public static function sanitize_direct_endpoint_param( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Validate the `endpoint` query parameter against the allow-list or URL format.
	 *
	 * @param mixed           $value   Sanitized endpoint.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public static function validate_proxy_endpoint_query_arg( $value, $request, $param ) {
		unset( $param );
		$endpoint = '';
		if ( is_string( $value ) ) {
			$endpoint = trim( $value );
		} elseif ( null !== $value && '' !== $value ) {
			$endpoint = trim( (string) $value );
		}

		$direct_raw     = $request->get_param( 'direct_endpoint' );
		$is_remote_mode = filter_var( $direct_raw, FILTER_VALIDATE_BOOLEAN );

		if ( $is_remote_mode ) {
			if ( '' === $endpoint ) {
				return new WP_Error(
					'leadconnector_proxy_direct',
					__( 'direct_endpoint requests require an endpoint URL.', 'leadconnector' ),
					array(
						'status' => 400,
						'field'  => 'endpoint',
					)
				);
			}
			if ( ! filter_var( $endpoint, FILTER_VALIDATE_URL ) ) {
				return new WP_Error(
					'leadconnector_proxy_url',
					__( 'direct_endpoint endpoint must be a valid URL.', 'leadconnector' ),
					array(
						'status' => 400,
						'field'  => 'endpoint',
					)
				);
			}
			return true;
		}

		if ( '' !== $endpoint && ! in_array( $endpoint, self::PROXY_ALLOWED_ENDPOINT_SLUGS, true ) ) {
			return new WP_Error(
				'leadconnector_proxy_slug',
				__( 'Unsupported proxy endpoint slug.', 'leadconnector' ),
				array(
					'status' => 400,
					'field'  => 'endpoint',
				)
			);
		}

		return true;
	}

	/**
	 * Return the DTO route bindings for input validation.
	 *
	 * @return array
	 */
	private static function dto_bindings() {
		static $bindings = null;

		if ( null === $bindings ) {
			// The only DTO binding (the `/leadconnector_internal_api/v1/save_custom_values`
			// POST route used by a deprecated loopback save) was removed in
			// 3.0.32 when that route was deleted in favour of an in-process
			// `wp_schedule_single_event()` hand-off. The dispatch filter is
			// kept registered so additional bindings can be reintroduced
			// without changing the bootstrap.
			$bindings = array();
		}

		return $bindings;
	}

	/**
	 * Register the REST dispatch filter for input interception.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rest_dispatch_request', array( __CLASS__, 'intercept_dispatch' ), 5, 4 );
	}

	/**
	 * Intercept REST dispatch to validate and attach DTOs to LeadConnector routes.
	 *
	 * @param mixed           $dispatch_result Current dispatch result.
	 * @param WP_REST_Request $request         REST request object.
	 * @param string          $route           Matched route pattern.
	 * @param array           $handler         Route handler info.
	 * @return mixed|null|\WP_Error
	 */
	public static function intercept_dispatch( $dispatch_result, $request, $route, $handler ) {
		unset( $route, $handler );
		if ( null !== $dispatch_result ) {
			return $dispatch_result;
		}
		if ( ! $request instanceof WP_REST_Request ) {
			return null;
		}

		$normalized_route = (string) $request->get_route();

		if ( ! self::is_lead_connector_route( $normalized_route ) ) {
			return null;
		}

		foreach ( self::dto_bindings() as $bind ) {
			if ( $normalized_route === $bind['route'] && in_array( $request->get_method(), $bind['methods'], true ) ) {
				$validated = LeadConnector_Input_Validator::from_rest_request( $request, $bind['dto'] );
				if ( is_wp_error( $validated ) ) {
					return $validated;
				}
				$request->set_param( '_leadconnector_validated_input', $validated );
				return null;
			}
		}

		if ( self::is_proxy_route( $normalized_route ) ) {
			$maybe_error = self::attach_proxy_json_graphs( $request );
			if ( is_wp_error( $maybe_error ) ) {
				return $maybe_error;
			}
		}

		return null;
	}

	/**
	 * Check whether a route belongs to the LeadConnector plugin.
	 *
	 * @param string $route REST route path.
	 * @return bool
	 */
	private static function is_lead_connector_route( $route ) {
		return strpos( $route, '/leadconnector_api/' ) === 0 || strpos( $route, '/leadconnector_internal_api/' ) === 0 || strpos( $route, '/leadconnector/' ) === 0;
	}

	/**
	 * Check whether a route is the proxy route.
	 *
	 * @param string $route REST route path.
	 * @return bool
	 */
	private static function is_proxy_route( $route ) {
		return '/leadconnector_api/v1/proxy' === $route;
	}

	/**
	 * Parse and attach JSON graph objects to proxy request parameters.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return null|\WP_Error
	 */
	private static function attach_proxy_json_graphs( WP_REST_Request $request ) {
		// SECURITY: these two keys are guard-internal state, but they live in
		// the same parameter bag as client input. WP_REST_Request::get_param()
		// reads JSON, POST, GET and URL params, and neither key is declared in
		// the route `args`, so sanitize_params()/has_valid_params() will
		// neither sanitize nor strip a client-supplied copy. If we only set
		// them conditionally, a caller can pre-seed
		// `?_leadconnector_proxy_post_body_graph[foo]=bar` (with an empty POST
		// body, so the branch below never fires) and the validator will read
		// that array back as an "already sanitized" graph — bypassing
		// proxy_sanitize_json_node() entirely on the way to update_post_meta(),
		// wp_insert_post() and outbound URL construction.
		//
		// Therefore: always overwrite both keys, so any client-supplied value
		// is unconditionally destroyed even when there is nothing to parse.
		$request->set_param( '_leadconnector_proxy_data_graph', null );
		$request->set_param( '_leadconnector_proxy_post_body_graph', null );

		$data = $request->get_param( 'data' );
		if ( is_string( $data ) && '' !== $data ) {
			$graph = LeadConnector_Input_Validator::proxy_json_to_object( $data );
			if ( is_wp_error( $graph ) ) {
				return $graph;
			}
			$request->set_param( '_leadconnector_proxy_data_graph', $graph );
		}

		if ( 'POST' === $request->get_method() ) {
			$raw_body = $request->get_body();
			if ( '' !== $raw_body ) {
				$graph = LeadConnector_Input_Validator::proxy_json_to_object( $raw_body );
				if ( is_wp_error( $graph ) ) {
					return $graph;
				}
				$request->set_param( '_leadconnector_proxy_post_body_graph', $graph );
			}
		}

		return null;
	}
}
