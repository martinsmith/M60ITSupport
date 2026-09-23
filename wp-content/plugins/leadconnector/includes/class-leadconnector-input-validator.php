<?php
/**
 *
 * LeadConnector inbound input: whitelist → validate → sanitize → input DTO. WordPress-native; fail-fast.
 *
 * @package LeadConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whitelist, validate, and sanitize inbound REST input into typed DTOs.
 *
 * @package LeadConnector
 */
final class LeadConnector_Input_Validator {

	/**
	 * Validate and sanitize a raw associative array into a typed DTO.
	 *
	 * @param array  $raw             Raw input data.
	 * @param string $input_dto_class Fully-qualified DTO class name.
	 * @return object|\WP_Error
	 */
	public static function from_array( array $raw, string $input_dto_class ) {
		if ( ! is_subclass_of( $input_dto_class, LeadConnector_Abstract_Input_DTO::class, true ) ) {
			return new WP_Error( 'leadconnector_bad_input_dto', __( 'Invalid input DTO class.', 'leadconnector' ) );
		}

		$schema          = $input_dto_class::schema();
		$accepted_fields = array_intersect_key( $raw, array_flip( array_keys( $schema ) ) );
		$sanitized       = array();

		foreach ( $schema as $field_name => $field_rules ) {
			if ( empty( $field_rules['required'] ) ) {
				if ( ! isset( $accepted_fields[ $field_name ] ) ) {
					continue;
				}
				if ( null === $accepted_fields[ $field_name ] ) {
					continue;
				}
				if ( is_string( $accepted_fields[ $field_name ] ) && '' === trim( $accepted_fields[ $field_name ] ) ) {
					continue;
				}
			} elseif ( ! array_key_exists( $field_name, $accepted_fields ) ) {
				return new WP_Error( 'leadconnector_required', __( 'Missing required field.', 'leadconnector' ), array( 'field' => $field_name ) );
			} elseif ( null === $accepted_fields[ $field_name ] || ( is_string( $accepted_fields[ $field_name ] ) && '' === trim( $accepted_fields[ $field_name ] ) ) ) {
				return new WP_Error( 'leadconnector_required', __( 'Missing required field.', 'leadconnector' ), array( 'field' => $field_name ) );
			}

			$sanitized_value = self::clean_single_field_value( $accepted_fields[ $field_name ], $field_rules, $field_name );

			if ( is_wp_error( $sanitized_value ) ) {
				return $sanitized_value;
			}

			$sanitized[ $field_name ] = $sanitized_value;
		}

		return $input_dto_class::from_validated( $sanitized );
	}

	/**
	 * Build a DTO from a REST request's JSON or body params.
	 *
	 * @param WP_REST_Request $request         REST request object.
	 * @param string          $input_dto_class Fully-qualified DTO class name.
	 * @return object|\WP_Error
	 */
	public static function from_rest_request( WP_REST_Request $request, string $input_dto_class ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			$params = array();
		}

		if ( array() === $params ) {
			$body_params = $request->get_body_params();
			if ( is_array( $body_params ) ) {
				$params = $body_params;
			}
		}

		return self::from_array( $params, $input_dto_class );
	}

	/**
	 * Return a cached validated DTO from the request or validate anew.
	 *
	 * @param WP_REST_Request $request   REST request object.
	 * @param string          $dto_class Fully-qualified DTO class name.
	 * @return object|\WP_Error
	 */
	public static function from_rest_request_cached( WP_REST_Request $request, string $dto_class ) {
		$cached = $request->get_param( '_leadconnector_validated_input' );

		return is_object( $cached ) && is_a( $cached, $dto_class, false ) ? $cached : self::from_rest_request( $request, $dto_class );
	}

	/**
	 * Standard JSON shape for REST 400 responses.
	 *
	 * @param \WP_Error $error Validation error.
	 * @return array{success:false,errors:array<string,string>}
	 */
	public static function rest_error_payload( \WP_Error $error ) {
		$error_data     = $error->get_error_data();
		$field_selector = '_global';

		if ( is_array( $error_data ) && isset( $error_data['field'] ) ) {
			$field_selector = (string) $error_data['field'];
		}

		return array(
			'success' => false,
			'errors'  => array( $field_selector => $error->get_error_message() ),
		);
	}

	/**
	 * Validate and sanitize a single field value according to its schema rules.
	 *
	 * @param mixed  $raw_value   Raw input value.
	 * @param array  $field_rules Schema rules for this field.
	 * @param string $field_name  Human-readable field name for errors.
	 * @return mixed|\WP_Error
	 */
	private static function clean_single_field_value( $raw_value, array $field_rules, string $field_name ) {
		$field_type = isset( $field_rules['type'] ) ? (string) $field_rules['type'] : 'text';

		switch ( $field_type ) {
			case 'text':
				// PHP 7.4 compatible (avoid \Stringable, added in PHP 8.0).
				if ( ! is_scalar( $raw_value ) ) {
					return new WP_Error( 'leadconnector_type', __( 'Invalid text.', 'leadconnector' ), array( 'field' => $field_name ) );
				}
				$sanitized_value = sanitize_text_field( is_string( $raw_value ) ? $raw_value : (string) $raw_value );
				break;

			case 'email':
				$sanitized_value = sanitize_email( is_string( $raw_value ) ? $raw_value : (string) $raw_value );

				if ( '' !== $sanitized_value && ! is_email( $sanitized_value ) ) {
					return new WP_Error( 'leadconnector_email', __( 'Invalid email.', 'leadconnector' ), array( 'field' => $field_name ) );
				}

				break;

			case 'url':
				$url_string      = is_string( $raw_value ) ? $raw_value : (string) $raw_value;
				$sanitized_value = esc_url_raw( $url_string );

				if ( '' !== $url_string && '' === $sanitized_value ) {
					return new WP_Error( 'leadconnector_url', __( 'Invalid URL.', 'leadconnector' ), array( 'field' => $field_name ) );
				}

				break;

			case 'int':
				if ( is_bool( $raw_value ) ) {
					return new WP_Error( 'leadconnector_int', __( 'Invalid integer.', 'leadconnector' ), array( 'field' => $field_name ) );
				}

				if ( is_string( $raw_value ) && preg_match( '/^-?\d+$/', trim( $raw_value ) ) !== 1 ) {
					return new WP_Error( 'leadconnector_int', __( 'Invalid integer.', 'leadconnector' ), array( 'field' => $field_name ) );
				}

				$sanitized_value = intval( $raw_value );
				break;

			case 'float':
				if ( ! is_numeric( $raw_value ) ) {
					return new WP_Error( 'leadconnector_float', __( 'Invalid number.', 'leadconnector' ), array( 'field' => $field_name ) );
				}

				$sanitized_value = floatval( $raw_value );
				break;

			case 'bool':
				$parsed_boolean = filter_var( $raw_value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

				if ( null === $parsed_boolean ) {
					return new WP_Error( 'leadconnector_bool', __( 'Invalid boolean.', 'leadconnector' ), array( 'field' => $field_name ) );
				}

				$sanitized_value = (bool) $parsed_boolean;
				break;

			case 'html':
				if ( ! is_string( $raw_value ) ) {
					return new WP_Error( 'leadconnector_html', __( 'Invalid HTML.', 'leadconnector' ), array( 'field' => $field_name ) );
				}

				$sanitized_value = wp_kses_post( $raw_value );
				break;

			case 'key':
				$sanitized_value = sanitize_key( is_string( $raw_value ) ? $raw_value : (string) $raw_value );
				break;

			case 'list':
				return self::clean_list_field( $raw_value, $field_rules, $field_name );

			default:
				return new WP_Error( 'leadconnector_schema', __( 'Unknown field type.', 'leadconnector' ), array( 'field' => $field_name ) );

		}

		return self::apply_numeric_and_enum_constraints( $sanitized_value, $field_rules, $field_name, $field_type );
	}

	/**
	 * Validate and sanitize a list-type field and its nested rows.
	 *
	 * @param mixed                $raw_list        Raw list input.
	 * @param array<string, mixed> $field_rules     Schema rules for the list field.
	 * @param string               $list_field_name Human-readable field name for errors.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private static function clean_list_field( $raw_list, array $field_rules, $list_field_name ) {
		if ( ! is_array( $raw_list ) ) {
			return new WP_Error( 'leadconnector_list_type', __( 'Must be an array.', 'leadconnector' ), array( 'field' => $list_field_name ) );
		}

		$item_columns = isset( $field_rules['item'] ) && is_array( $field_rules['item'] ) ? $field_rules['item'] : null;
		if ( null === $item_columns || array() === $item_columns ) {
			return new WP_Error( 'leadconnector_list_schema', __( 'List schema missing item definitions.', 'leadconnector' ), array( 'field' => $list_field_name ) );
		}

		$item_field_names = array_keys( $item_columns );
		$max_row_count    = isset( $field_rules['max_items'] ) ? max( 0, intval( $field_rules['max_items'] ) ) : 500;
		$rows_ordered     = array_values( $raw_list );

		if ( count( $rows_ordered ) > $max_row_count ) {
			return new WP_Error( 'leadconnector_list_max', __( 'Too many rows.', 'leadconnector' ), array( 'field' => $list_field_name ) );
		}

		if ( isset( $field_rules['min_items'] ) && count( $rows_ordered ) < max( 0, intval( $field_rules['min_items'] ) ) ) {
			return new WP_Error(
				'leadconnector_list_min',
				__( 'Too few rows.', 'leadconnector' ),
				array(
					'field' => $list_field_name,
				)
			);
		}

		$sanitized_rows = array();
		foreach ( $rows_ordered as $row_index => $row_payload ) {
			if ( ! is_array( $row_payload ) ) {
				return new WP_Error( 'leadconnector_list_row', __( 'Each item must be an object.', 'leadconnector' ), array( 'field' => $list_field_name . '[' . $row_index . ']' ) );
			}

			$row_accepted_columns = array_intersect_key( $row_payload, array_flip( $item_field_names ) );
			$sanitized_row        = array();

			foreach ( $item_columns as $nested_field_name => $nested_field_rules ) {
				$nested_field_path = $list_field_name . '[' . $row_index . '].' . $nested_field_name;

				if ( empty( $nested_field_rules['required'] ) ) {
					if ( ! isset( $row_accepted_columns[ $nested_field_name ] ) ) {
						continue;
					}
					if ( null === $row_accepted_columns[ $nested_field_name ] ) {
						continue;
					}
					if ( is_string( $row_accepted_columns[ $nested_field_name ] ) && '' === trim( $row_accepted_columns[ $nested_field_name ] ) ) {
						continue;
					}
				} elseif ( ! array_key_exists( $nested_field_name, $row_accepted_columns )
					|| null === $row_accepted_columns[ $nested_field_name ]
					|| ( is_string( $row_accepted_columns[ $nested_field_name ] ) && '' === trim( $row_accepted_columns[ $nested_field_name ] ) ) ) {
					return new WP_Error(
						'leadconnector_required_nested',
						__( 'Missing required field.', 'leadconnector' ),
						array(
							'field' => $nested_field_path,
						)
					);
				}

				$sanitized_nested_value = self::clean_single_field_value( $row_accepted_columns[ $nested_field_name ], $nested_field_rules, $nested_field_path );

				if ( is_wp_error( $sanitized_nested_value ) ) {
					return $sanitized_nested_value;
				}

				$sanitized_row[ $nested_field_name ] = $sanitized_nested_value;
			}

			$sanitized_rows[] = $sanitized_row;
		}

		return $sanitized_rows;
	}

	/**
	 * Apply numeric range and enum constraints to a sanitized value.
	 *
	 * @param mixed  $value       Sanitized field value.
	 * @param array  $field_rules Schema rules for this field.
	 * @param string $field_name  Human-readable field name for errors.
	 * @param string $field_type  Schema type identifier.
	 * @return mixed|\WP_Error
	 */
	private static function apply_numeric_and_enum_constraints( $value, array $field_rules, string $field_name, string $field_type ) {
		if ( isset( $field_rules['enum'] ) && is_array( $field_rules['enum'] ) ) {
			$allowed_values = array_map( 'strval', $field_rules['enum'] );

			if ( ! in_array( (string) $value, $allowed_values, true ) ) {
				return new WP_Error( 'leadconnector_enum', __( 'Invalid choice.', 'leadconnector' ), array( 'field' => $field_name ) );
			}
		}

		if ( isset( $field_rules['max_length'] ) && is_string( $value ) ) {
			$max_length = intval( $field_rules['max_length'] );

			$character_count = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			if ( $max_length > 0 && $character_count > $max_length ) {
				return new WP_Error( 'leadconnector_len', __( 'Value too long.', 'leadconnector' ), array( 'field' => $field_name ) );
			}
		}

		if ( 'int' === $field_type ) {
			if ( isset( $field_rules['min'] ) && intval( $value ) < intval( $field_rules['min'] ) ) {
				return new WP_Error( 'leadconnector_min', __( 'Below minimum.', 'leadconnector' ), array( 'field' => $field_name ) );
			}

			if ( isset( $field_rules['max'] ) && intval( $value ) > intval( $field_rules['max'] ) ) {
				return new WP_Error( 'leadconnector_max', __( 'Above maximum.', 'leadconnector' ), array( 'field' => $field_name ) );
			}
		}

		if ( 'float' === $field_type ) {
			if ( isset( $field_rules['min'] ) && floatval( $value ) < floatval( $field_rules['min'] ) ) {
				return new WP_Error( 'leadconnector_min', __( 'Below minimum.', 'leadconnector' ), array( 'field' => $field_name ) );
			}

			if ( isset( $field_rules['max'] ) && floatval( $value ) > floatval( $field_rules['max'] ) ) {
				return new WP_Error( 'leadconnector_max', __( 'Above maximum.', 'leadconnector' ), array( 'field' => $field_name ) );
			}
		}

		return $value;
	}

	/**
	 * Sanitize a JSON object key to alphanumeric, underscore, and hyphen characters.
	 *
	 * @param mixed $key JSON object key.
	 * @return mixed
	 */
	private static function sanitize_json_key( $key ) {
		return preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key );
	}

	/**
	 * Recursively sanitize a json_decode output node.
	 *
	 * @param mixed $node Value from json_decode.
	 * @return mixed
	 */
	private static function proxy_sanitize_json_node( $node ) {
		if ( $node instanceof \stdClass ) {
			$out = new \stdClass();
			foreach ( (array) $node as $prop => $child ) {
				if ( is_string( $prop ) ) {
					$prop = self::sanitize_json_key( $prop );
					if ( '' === $prop ) {
						continue;
					}
				}
				$out->{$prop} = self::proxy_sanitize_json_node( $child );
			}
			return $out;
		}

		if ( is_array( $node ) ) {
			$out = array();
			foreach ( $node as $key => $child ) {
				if ( is_string( $key ) ) {
					$key = self::sanitize_json_key( $key );
					if ( '' === $key ) {
						continue;
					}
				}
				$out[ $key ] = self::proxy_sanitize_json_node( $child );
			}
			return $out;
		}

		if ( is_string( $node ) ) {
			return sanitize_text_field( $node );
		}

		if ( is_bool( $node ) || is_int( $node ) || is_float( $node ) || null === $node ) {
			return $node;
		}

		return null;
	}

	/**
	 * JSON string → sanitized stdClass/list graph for legacy proxy handlers ($body->field).
	 *
	 * @param mixed $json_string Query `data` or raw POST body.
	 * @return object|array|WP_Error
	 */
	public static function proxy_json_to_object( $json_string ) {
		if ( null === $json_string || '' === $json_string ) {
			return new \stdClass();
		}
		if ( ! is_string( $json_string ) ) {
			return new WP_Error(
				'leadconnector_json_type',
				__( 'Invalid data parameter.', 'leadconnector' ),
				array( 'field' => 'data' )
			);
		}

		$root = json_decode( $json_string, false );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'leadconnector_json',
				__( 'Invalid JSON.', 'leadconnector' ),
				array( 'field' => 'data' )
			);
		}

		return self::proxy_sanitize_json_node( $root );
	}

	/**
	 * Return the cached proxy query data graph or parse it fresh.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @param array           $params  Query parameters.
	 * @return object|array|\WP_Error
	 */
	private static function proxy_cached_query_data( WP_REST_Request $request, array $params ) {
		$cached = $request->get_param( '_leadconnector_proxy_data_graph' );

		return null !== $cached ? $cached : self::proxy_json_to_object( isset( $params['data'] ) ? $params['data'] : '' );
	}

	/**
	 * Return the cached proxy POST body graph or parse it fresh.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return object|array|\WP_Error
	 */
	private static function proxy_cached_post_body( WP_REST_Request $request ) {
		$cached = $request->get_param( '_leadconnector_proxy_post_body_graph' );

		return ( is_object( $cached ) || is_array( $cached ) )
			? $cached
			: self::proxy_json_to_object( $request->get_body() );
	}

	/**
	 * Ensure a graph value is a stdClass object.
	 *
	 * @param mixed $graph Parsed JSON graph or WP_Error.
	 * @return object|\WP_Error
	 */
	private static function proxy_graph_object( $graph ) {
		if ( is_wp_error( $graph ) ) {
			return $graph;
		}

		return is_object( $graph ) ? $graph : json_decode( wp_json_encode( $graph ), false );
	}

	/**
	 * Return the proxy query-string `data` as a sanitized stdClass object.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @param array           $params  Query parameters.
	 * @return object|\WP_Error
	 */
	public static function proxy_query_as_object( WP_REST_Request $request, array $params ) {
		return self::proxy_graph_object( self::proxy_cached_query_data( $request, $params ) );
	}

	/**
	 * Return the proxy POST body as a sanitized stdClass object.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return object|\WP_Error
	 */
	public static function proxy_post_as_object( WP_REST_Request $request ) {
		return self::proxy_graph_object( self::proxy_cached_post_body( $request ) );
	}
}
