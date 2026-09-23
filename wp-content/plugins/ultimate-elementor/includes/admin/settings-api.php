<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UltimateElementor\Classes\UAEL_Helper;
use UltimateElementor\Classes\UAEL_Maxmind_Database;

/**
 * Class Settings_Api.
 */
class Settings_Api {

	/**
	 * Instance.
	 *
	 * @access private
	 * @var object Class object.
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Settings_Api
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {

		$routes = array(
            '/widgets' => 'get_uael_widgets',
            '/postskins' => 'get_uael_post_skins',
            '/settings' => array('GET' => 'get_uael_settings', 'POST' => 'save_uael_settings'),
            '/branding' => array('GET' => 'get_uael_branding', 'POST' => 'save_uael_branding'),
            '/plugins' => 'get_plugins_list',
            '/validateApiKey' => 'validate_google_places_api_key',
            '/templates' => 'get_templates_status',
            '/mcp-settings' => array( 'GET' => 'get_mcp_settings', 'POST' => 'update_mcp_settings' ),
            '/mcp-abilities' => 'get_mcp_abilities',
        );

		foreach ( $routes as $route => $callback ) {
            if ( is_array( $callback ) ) {
                foreach ( $callback as $method => $cb ) {
                    register_rest_route(
                        'uael/v1',
                        $route,
                        array(
                            'methods'             => $method,
                            'callback'            => array( $this, $cb ),
                            'permission_callback' => array( $this, 'get_items_permissions_check' ),
                        )
                    );
                }
            } else {
				$call_method = ( '/validateApiKey' === $route ) ? 'POST' : 'GET';
                register_rest_route(
                    'uael/v1',
                    $route,
                    array(
                        'methods'             => $call_method,
                        'callback'            => array( $this, $callback ),
                        'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    )
                );
            }
        }
	}

	/**
	 * Get Starter Templates Status.
	 */
	public function get_templates_status( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
		}

		$templates_status = UAEL_Helper::starter_templates_status();

		$response_data = array(
			'templates_status' => $templates_status,
		);
	
		if ( 'Activated' === $templates_status ) {
			$response_data['redirect_url'] = UAEL_Helper::starter_templates_link();
		}

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Validate Google Places API key.
	 */
	public function validate_google_places_api_key( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
	
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
		}
	
		$api_key = $request->get_param( 'apiKey' );
		$api_type = $request->get_param( 'source' );

		if( 'google' === $api_type || 'yelp' === $api_type ) {
	
			// Perform your API key validation logic here.
			UAEL_Helper::get_api_authentication( $api_type, $api_key );

			if( 'google' === $api_type ) {
				$google_status = get_option( 'uael_google_api_status' );

				if ( 'yes-new' === $google_status  || 'yes' === $google_status ) {
                    return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Your API key authenticated successfully!', 'uael' ) ), 200 );
                } elseif ( 'no' === $google_status ) {
                    return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Entered API key is invalid', 'uael' ) ), 400 );
                } elseif ( 'exceeded' === $google_status ) {
                    $error_message = sprintf(
                        '%1$s%s%2$s %3$s%s%4$s',
                        '<b>' . __( 'Google Error Message:', 'uael' ) . '</b>',
                        __( 'You have exceeded your daily request quota for this API. If you did not set a custom daily request quota, verify your project has an active billing account.', 'uael' ),
                        '<a href="http://g.co/dev/maps-no-account" target="_blank" rel="noopener">',
                        __( 'Click here to enable billing.', 'uael' ),
                        '</a>'
                    );
                    return new WP_REST_Response( array( 'success' => false, 'message' => $error_message ), 400 );
                } else {
                    return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Unknown error', 'uael' ) ), 400 );
                }
			}

			if( 'yelp' === $api_type ) {
				$yelp_status = get_option( 'uael_yelp_api_status' );

				if ( 'yes' === $yelp_status ) {
                    return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Your API key authenticated successfully!', 'uael' ) ), 200 );
                } elseif ( 'no' === $yelp_status ) {
                    return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Entered API key is invalid', 'uael' ) ), 400 );
                } else {
                    return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Unknown error', 'uael' ) ), 400 );
                }
			}
		} else if( 'facebook' === $api_type ) {
			$response = UAEL_Helper::facebook_token_authentication( $api_key );
			if ( 200 === $response ) {
                return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Access Token authenticated successfully!', 'uael' ) ), 200 );
            } else {
                return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Invalid Access Token', 'uael' ) ), 400 );
            }
		}
		
		return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Unknown error', 'uael' ) ), 400 );
        
	}

	/**
	 * Check whether a given request has permission to read notes.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request = null ) {

		if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'uae_rest_not_allowed', __( 'Sorry, you are not authorized to perform this action.', 'uael' ), array( 'status' => 403 ) );
        }

		// Verify the REST nonce here (the permission_callback) so unauthorized or
		// CSRF requests are rejected with a 403 before any route callback runs,
		// instead of relying on a manual check inside each callback body.
		$nonce = ( $request instanceof \WP_REST_Request ) ? $request->get_header( 'X-WP-Nonce' ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'uae_rest_invalid_nonce', __( 'Invalid nonce.', 'uael' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Callback function to return settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_uael_widgets( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        $all_widgets = UAEL_Helper::get_all_widgets_list();

        if ( ! is_array( $all_widgets ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Widgets not found', 'uael' ) ), 404 ); // Return not found response
        }

		return new WP_REST_Response( $all_widgets, 200 );
	}

	/**
	 * Callback function to return settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_uael_post_skins( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        $post_skins = UAEL_Helper::get_post_skin_options();

        if ( ! is_array( $post_skins ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Not found', 'uael' ) ), 404 ); // Return not found response
        }

		return new WP_REST_Response( $post_skins, 200 );
	}

	/**
	 * Callback function to return settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_uael_settings( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        // Fetch your settings here
        $settings = UAEL_Helper::get_admin_settings_option( '_uael_integration', array(), true );

        if ( ! is_array( $settings ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Settings not found', 'uael' ) ), 404 ); // Return not found response
        }

		return new WP_REST_Response( $settings, 200 );
	}

	public function save_uael_settings( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

		// Get the settings from the request
		$settings = $request->get_json_params();
		$new_settings   = array();
		$maxmind_status = 'success';

		// Verify MaxMind key and download database
		if ( isset( $settings['uael_maxmind_geolocation_license_key'] ) ) {
            $geolite_db = new UAEL_Maxmind_Database();
            $result     = $geolite_db->verify_key_and_download_database( $settings['uael_maxmind_geolocation_license_key'] );
            if ( isset( $result['error'] ) && $result['error'] ) {
                $maxmind_status = 'error';
            }
        }

		// Allow-list of integration setting keys (mirrors the integrations-update
		// ability) so arbitrary keys can't be written into _uael_integration.
		$allowed_keys = array(
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

		// Loop through the input and sanitize each allow-listed value.
		foreach ( (array) $settings as $key => $val ) {

			if ( ! in_array( $key, $allowed_keys, true ) ) {
				continue;
			}

			if ( is_array( $val ) ) {
				foreach ( $val as $k => $v ) {
					$new_settings[ $key ][ $k ] = sanitize_text_field( $v );
				}
			} else {
				$new_settings[ $key ] = sanitize_text_field( $val );
			}
		}
	
		// Save the settings back to the database
		UAEL_Helper::update_admin_settings_option( '_uael_integration', $new_settings, true );
	
		return new WP_REST_Response( array(
			'message' => __( 'Settings saved successfully', 'uael' ),
			'maxmind_status' => $maxmind_status
		), 200 );
    }

	/**
	 * Callback function to return plugins list.
	 *
	 * @return WP_REST_Response
	 */
	public function get_plugins_list( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        // Fetch branding settings
        $plugins_list = UAEL_Helper::get_bsf_plugins_list();

        if ( ! is_array( $plugins_list ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Plugins list not found', 'uael' ) ), 404 );
        }

		return new WP_REST_Response( $plugins_list, 200 );
		
	}

	/**
	 * Callback function to return settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_uael_branding( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }

        // Fetch branding settings
        $branding_settings = UAEL_Helper::get_white_labels();

        if ( ! is_array( $branding_settings ) ) {
            return new WP_REST_Response( array( 'message' => __( 'Branding settings not found', 'uael' ) ), 404 );
        }

		return new WP_REST_Response( $branding_settings, 200 );
		
	}

	public function save_uael_branding( $request ) {

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
        }
	
		// Get the settings from the request
		$settings        = $request->get_json_params();
		$stored_settings = UAEL_Helper::get_white_labels();
	
		// Sanitize and prepare the settings for saving
		$new_settings = array();

		// Allow-list + URL escaping (mirrors the branding-update ability) so
		// arbitrary keys can't be stored and URL fields can't carry javascript:
		// schemes that would render as href in admin pages.
		$allowed_keys    = array(
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
		$url_keys        = array( 'knowledgebase_url', 'support_url', 'logo_url' );
		$nested_url_keys = array( 'agency' => array( 'author_url' ) );

		if ( is_array( $settings ) ) {
			foreach ( $settings as $key => $val ) {

				if ( ! in_array( $key, $allowed_keys, true ) ) {
					continue;
				}

				if ( is_array( $val ) ) {
					$nested_urls = isset( $nested_url_keys[ $key ] ) ? $nested_url_keys[ $key ] : array();
					foreach ( $val as $k => $v ) {
						$new_settings[ $key ][ $k ] = in_array( $k, $nested_urls, true ) ? esc_url_raw( $v ) : sanitize_text_field( $v );
					}
				} elseif ( in_array( $key, $url_keys, true ) ) {
					$new_settings[ $key ] = esc_url_raw( $val );
				} else {
					$new_settings[ $key ] = sanitize_text_field( $val );
				}
			}
		}

		if ( ! isset( $new_settings['agency']['hide_branding'] ) ) {
			$new_settings['agency']['hide_branding'] = false;
		} else {
			// Add a hide branding component option
		}

		$checkbox_var = array(
			'replace_logo',
			'internal_help_links',
		);

		foreach ( $checkbox_var as $key => $value ) {
			if ( ! isset( $new_settings[ $value ] ) ) {
				$new_settings[ $value ] = 'disable';
			}
		}
		
		$new_settings = wp_parse_args( $new_settings, $stored_settings );
		
		// Save the settings back to the database
		UAEL_Helper::update_admin_settings_option( '_uael_white_label', $new_settings, true );

		return new WP_REST_Response( 'Branding settings saved successfully', 200 );

	}

	/**
	 * Get MCP abilities list for AI Tools settings page.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_mcp_abilities( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
		}

		// Ensure handlers are populated — wp_abilities_api_init may not have fired yet.
		$this->ensure_active_handlers_loaded();

		$registry = $this->get_active_registry();

		if ( ! $registry ) {
			return new WP_REST_Response( array(), 200 );
		}

		$abilities = $registry->get_abilities_info();
		$settings  = get_option( 'uae_mcp_settings', array() );
		$disabled  = ! empty( $settings['disabled_abilities'] ) && is_array( $settings['disabled_abilities'] )
			? $settings['disabled_abilities']
			: array();

		foreach ( $abilities as &$ability ) {
			$ability['is_enabled'] = ! in_array( $ability['name'], $disabled, true )
				&& ! in_array( $ability['full_name'], $disabled, true );
		}
		unset( $ability );

		return new WP_REST_Response( $abilities, 200 );
	}

	/**
	 * Get MCP settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_mcp_settings( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
		}

		$defaults = array(
			'enable_abilities'    => false,
			'allow_modifications' => true,
			'dedicated_server'    => false,
			'angie_enabled'       => false,
			'disabled_abilities'  => array(),
		);

		$settings = get_option( 'uae_mcp_settings', array() );
		$settings = wp_parse_args( $settings, $defaults );

		$settings['is_abilities_api_active'] = function_exists( 'wp_register_ability' );
		$settings['is_mcp_adapter_active']   = class_exists( '\WP\MCP\Core\McpAdapter' );
		$settings['is_angie_active']         = class_exists( 'Elementor\Plugin' );
		$settings['dedicated_server_url']    = rest_url( 'uae/mcp' );
		$settings['total_abilities']         = $this->count_uael_abilities();

		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Update MCP settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_mcp_settings( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'uael' ), array( 'status' => 403 ) );
		}

		$defaults     = array(
			'enable_abilities'    => false,
			'allow_modifications' => true,
			'dedicated_server'    => false,
			'angie_enabled'       => false,
			'disabled_abilities'  => array(),
		);
		$current      = get_option( 'uae_mcp_settings', array() );
		$current      = wp_parse_args( $current, $defaults );
		$allowed_keys = array_keys( $defaults );
		$params       = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_params', __( 'Invalid parameters.', 'uael' ), array( 'status' => 400 ) );
		}

		// Capture prior toggle state before overwriting, to detect first-enable milestones.
		$was_abilities_enabled = ! empty( $current['enable_abilities'] );
		$was_server_enabled    = ! empty( $current['dedicated_server'] );
		$was_angie_enabled     = ! empty( $current['angie_enabled'] );

		foreach ( $allowed_keys as $key ) {
			if ( ! isset( $params[ $key ] ) ) {
				continue;
			}

			if ( 'disabled_abilities' === $key ) {
				$current[ $key ] = is_array( $params[ $key ] )
					? array_values( array_map( 'sanitize_text_field', $params[ $key ] ) )
					: array();
			} else {
				$current[ $key ] = (bool) $params[ $key ];
			}
		}

		// Autoload disabled — this option is only needed when the AI Tools
		// settings/abilities are actually loaded, not on every front-end request.
		update_option( 'uae_mcp_settings', $current, false );

		// Track the first time each AI Tools toggle is switched on as a milestone
		// event. Dedup in UAEL_Analytics_Events::track() ensures each fires once per
		// site lifecycle, giving an install-to-enable funnel without any usage detail.
		if ( class_exists( 'UAEL_Analytics_Events' ) ) {
			$install_time       = (int) get_option( 'uae_usage_installed_time', 0 );
			$days_since_install = $install_time > 0 ? (int) floor( ( time() - $install_time ) / DAY_IN_SECONDS ) : 0;

			$toggle_events = array(
				'enable_abilities' => array(
					'was'   => $was_abilities_enabled,
					'event' => 'abilities_enabled',
				),
				'dedicated_server' => array(
					'was'   => $was_server_enabled,
					'event' => 'mcp_server_enabled',
				),
				'angie_enabled'    => array(
					'was'   => $was_angie_enabled,
					'event' => 'angie_enabled',
				),
			);

			foreach ( $toggle_events as $key => $meta ) {
				if ( ! $meta['was'] && ! empty( $current[ $key ] ) ) {
					UAEL_Analytics_Events::track(
						$meta['event'],
						'yes',
						array( 'days_since_install' => (string) $days_since_install )
					);
				}
			}
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'settings' => $current,
			),
			200
		);
	}

	/**
	 * Get the active ability registry.
	 *
	 * In standalone mode: Pro's own registry.
	 * In dual mode: HFE's registry (which includes Pro's handlers).
	 *
	 * @return object|null Registry instance or null.
	 */
	private function get_active_registry() {
		// Try Pro's own registry first (standalone mode).
		$bootstrap = class_exists( 'UAEL_Abilities_Bootstrap' ) ? UAEL_Abilities_Bootstrap::instance() : null;
		$registry  = $bootstrap ? $bootstrap->get_registry() : null;

		if ( $registry ) {
			return $registry;
		}

		// Dual mode: use HFE's registry.
		if ( class_exists( 'HFE_Abilities_Loader' ) ) {
			$hfe_loader = HFE_Abilities_Loader::instance();
			if ( method_exists( $hfe_loader, 'get_registry' ) ) {
				return $hfe_loader->get_registry();
			}
		}

		return null;
	}

	/**
	 * Ensure the active registry has handlers loaded.
	 *
	 * Calls ensure_handlers_loaded() on whichever bootstrap is active
	 * (Pro standalone or HFE dual mode).
	 *
	 * @return void
	 */
	private function ensure_active_handlers_loaded() {
		// Use Pro's own bootstrap ONLY in standalone mode — i.e. when it actually
		// owns a registry. In dual mode Pro has no registry of its own (its
		// handlers are registered into HFE's registry), so we must ensure HFE's
		// loader instead. Otherwise the registry that get_active_registry()
		// returns (HFE's) is never populated on Pro's settings page and the
		// abilities list/count come back empty. Mirrors get_active_registry().
		$bootstrap = class_exists( 'UAEL_Abilities_Bootstrap' ) ? UAEL_Abilities_Bootstrap::instance() : null;

		if ( $bootstrap
			&& method_exists( $bootstrap, 'get_registry' ) && $bootstrap->get_registry()
			&& method_exists( $bootstrap, 'ensure_handlers_loaded' ) ) {
			$bootstrap->ensure_handlers_loaded();
			return;
		}

		// Dual mode (or Pro has no registry of its own): use HFE's loader, which
		// also fires uae_mcp_register_abilities so Pro's handlers join HFE's registry.
		if ( class_exists( 'HFE_Abilities_Loader' ) ) {
			$hfe_loader = HFE_Abilities_Loader::instance();

			if ( method_exists( $hfe_loader, 'ensure_handlers_loaded' ) ) {
				$hfe_loader->ensure_handlers_loaded();
			}
		}
	}

	/**
	 * Count registered abilities.
	 *
	 * @return int
	 */
	private function count_uael_abilities() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return 0;
		}

		// Count via wp_get_abilities() rather than the registry's discover()-
		// populated names. discover() only runs on wp_abilities_api_init, which
		// on the settings page may not have fired yet (e.g. when the MCP Adapter
		// is inactive) — leaving get_registered_names() empty and the count at 0.
		// wp_get_abilities() lazily fires that hook and performs registration,
		// so the total is correct with or without the MCP Adapter. Mirrors the
		// Lite plugin's count.
		$all_abilities = wp_get_abilities();
		$count         = 0;
		$prefix        = class_exists( 'UAEL_Ability_Registry' ) ? UAEL_Ability_Registry::PREFIX : 'uae/';

		foreach ( $all_abilities as $ability_name => $ability ) {
			if ( 0 === strpos( $ability_name, $prefix ) ) {
				++$count;
			}
		}

		return $count;
	}
}

// Initialize the Settings_Api class
Settings_Api::get_instance();