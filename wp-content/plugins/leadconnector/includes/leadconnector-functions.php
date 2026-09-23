<?php
/**
 * Shared plugin helper and shortcode functions.
 *
 * @package LeadConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read a property from a decoded LeadConnector API object (camelCase keys).
 *
 * @param object|null $api_object Decoded JSON object.
 * @param string      $property   Property name as returned by the API.
 * @param mixed       $fallback   Default when missing.
 * @return mixed
 */
function leadconnector_api_prop( $api_object, $property, $fallback = null ) {
	if ( ! is_object( $api_object ) ) {
		return $fallback;
	}

	$values = (array) $api_object;

	return array_key_exists( $property, $values ) ? $values[ $property ] : $fallback;
}

/**
 * Decode a stored JSON payload (plain JSON or legacy base64-wrapped JSON).
 *
 * @param string $stored Serialized tracking payload from post meta.
 * @return object|null
 */
function leadconnector_decode_stored_json( $stored ) {
	if ( ! is_string( $stored ) || '' === $stored ) {
		return null;
	}

	$decoded = json_decode( $stored );
	if ( is_object( $decoded ) ) {
		return $decoded;
	}

	$binary = leadconnector_decode_base64_payload( $stored );
	if ( false !== $binary ) {
		$decoded = json_decode( $binary );
		if ( is_object( $decoded ) ) {
			return $decoded;
		}
	}

	return null;
}

/**
 * Decode a base64 payload from the LeadConnector API (funnel tracking HTML).
 *
 * @param string $payload Base64-encoded payload from the API.
 * @return string
 */
function leadconnector_decode_api_base64_payload( $payload ) {
	if ( ! is_string( $payload ) || '' === $payload ) {
		return '';
	}

	$decoded = leadconnector_decode_base64_payload( $payload );
	return false === $decoded ? '' : $decoded;
}

/**
 * Decode a base64 payload using libsodium when available, otherwise PHP core.
 *
 * Wire-compatible with sodium_bin2base64( ..., SODIUM_BASE64_VARIANT_ORIGINAL ).
 *
 * @param string $payload Base64-encoded payload.
 * @return string|false Raw bytes, or false on failure.
 */
function leadconnector_decode_base64_payload( $payload ) {
	if ( ! is_string( $payload ) || '' === $payload ) {
		return false;
	}

	if ( function_exists( 'sodium_base642bin' ) ) {
		try {
			return sodium_base642bin( $payload, SODIUM_BASE64_VARIANT_ORIGINAL, true );
		} catch ( Exception $e ) {
			// Invalid sodium input; fall through to PHP's decoder.
			unset( $e );
		}
	}

	// Decoding a stored payload, not obfuscating source.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	$decoded = base64_decode( $payload, true );
	if ( false === $decoded || '' === $decoded ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded = base64_decode( $payload, false );
	}

	return ( false === $decoded || '' === $decoded ) ? false : $decoded;
}

/**
 * Sanitize shortcode attribute / option fragments for safe output contexts.
 *
 * @param mixed  $value   Raw value.
 * @param string $context One of 'text', 'url', 'id', 'attr'.
 * @return string
 */
function leadconnector_sanitize_shortcode_value( $value, $context = 'text' ) {
	if ( empty( $value ) ) {
		return '';
	}

	$value = trim( (string) $value );
	$value = preg_replace( '/\bon[a-z]+\s*=\s*["\'][^"\']*["\']/i', '', $value );
	$value = wp_strip_all_tags( $value );
	$value = str_replace( array( '"', "'" ), '', $value );

	switch ( $context ) {
		case 'url':
			return esc_url_raw( $value );
		case 'id':
		case 'attr':
			return esc_attr( $value );
		case 'text':
		default:
			return esc_html( $value );
	}
}

/**
 * Allowed HTML tags and attributes for shortcode iframe output.
 *
 * @return array
 */
function leadconnector_shortcode_allowed_html() {
	return array(
		'iframe' => array(
			'src'                     => true,
			'id'                      => true,
			'class'                   => true,
			'style'                   => true,
			'title'                   => true,
			'frameborder'             => true,
			'scrolling'               => true,
			'data-layout'             => true,
			'data-trigger-type'       => true,
			'data-trigger-value'      => true,
			'data-activation-type'    => true,
			'data-activation-value'   => true,
			'data-deactivation-type'  => true,
			'data-deactivation-value' => true,
			'data-form-name'          => true,
			'data-height'             => true,
			'data-layout-iframe-id'   => true,
			'data-form-id'            => true,
		),
		'div'    => array(
			'class' => true,
		),
	);
}

/**
 * Encrypt an SMTP secret before persisting it to plugin options.
 *
 * Wraps LeadConnector_Data_Encryption so the admin save path and the
 * PHPMailer init hook share a single canonical implementation. Aborts the
 * request with wp_die() if the encryption layer is not ready - the previous
 * "fall back to plaintext" behaviour was a 3.0.30 finding (#A3) because it
 * silently persisted SMTP passwords in cleartext on hosts missing OpenSSL
 * or the WordPress salts.
 *
 * @param string $plaintext Raw secret to encrypt (typically an SMTP password).
 * @return string Encrypted ciphertext.
 */
function leadconnector_encrypt_smtp_value( $plaintext ) {
	if ( ! is_string( $plaintext ) || '' === $plaintext ) {
		return $plaintext;
	}

	if ( ! class_exists( 'LeadConnector_Data_Encryption' ) ) {
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-leadconnector-data-encryption.php';
	}

	$encryption = new LeadConnector_Data_Encryption();
	if ( ! $encryption->is_ready() ) {
		wp_die(
			esc_html__( 'LeadConnector: cannot encrypt SMTP credentials. The OpenSSL PHP extension and the LOGGED_IN_KEY / LOGGED_IN_SALT constants in wp-config.php are required. Refusing to persist plaintext credentials.', 'leadconnector' ),
			esc_html__( 'LeadConnector: encryption unavailable', 'leadconnector' ),
			array( 'response' => 500 )
		);
	}

	$ciphertext = $encryption->encrypt( $plaintext );
	if ( false === $ciphertext || '' === $ciphertext ) {
		wp_die(
			esc_html__( 'LeadConnector: SMTP credential encryption failed unexpectedly. Please retry; if the failure persists, contact support.', 'leadconnector' ),
			esc_html__( 'LeadConnector: encryption failed', 'leadconnector' ),
			array( 'response' => 500 )
		);
	}

	return $ciphertext;
}

/**
 * Decrypt an SMTP secret previously stored via leadconnector_encrypt_smtp_value().
 *
 * Falls back to the original value when the input is not a ciphertext produced
 * by this plugin (legacy plaintext rows from before SMTP encryption was added).
 * The decryption routine validates the embedded salt, so a plaintext value
 * cannot accidentally decrypt to anything other than false.
 *
 * @param string $stored Stored value from plugin options.
 * @return string Plaintext secret, or the original value when not encrypted.
 */
function leadconnector_decrypt_smtp_value( $stored ) {
	if ( ! is_string( $stored ) || '' === $stored ) {
		return $stored;
	}

	if ( ! class_exists( 'LeadConnector_Data_Encryption' ) ) {
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-leadconnector-data-encryption.php';
	}

	$encryption = new LeadConnector_Data_Encryption();
	$plaintext  = $encryption->decrypt( $stored );

	return is_string( $plaintext ) ? $plaintext : $stored;
}

/**
 * PHPMailer init: apply SMTP options from plugin settings.
 *
 * @param object $phpmailer PHPMailer instance.
 */
function leadconnector_phpmailer_init_smtp( $phpmailer ) {
	$options          = get_option( LEAD_CONNECTOR_OPTION_NAME );
	$encrypted_secret = isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD ] )
		? $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PASSWORD ]
		: '';
	$smtp_password    = leadconnector_decrypt_smtp_value( $encrypted_secret );

	$settings = array(
		'Host'     => $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_SERVER ],
		'SMTPAuth' => true,
		'Port'     => $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_PORT ],
		'Username' => $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL ],
		'Password' => $smtp_password,
		'From'     => $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_EMAIL_SMTP_EMAIL ],
	);

	$phpmailer->isSMTP();
	foreach ( $settings as $property => $value ) {
		$phpmailer->{$property} = $value;
	}
}

/**
 * Shortcode: phone number pool (scripts enqueued; no inline tags).
 *
 * @param array $params Shortcode attributes.
 * @return string
 */
function leadconnector_shortcode_phone_pool( $params ) {
	$options     = get_option( LEAD_CONNECTOR_OPTION_NAME );
	$location_id = leadconnector_sanitize_shortcode_value( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ?? '', 'id' );
	$pool_id     = leadconnector_sanitize_shortcode_value( $params['id'] ?? '', 'id' );
	if ( '' === $location_id || '' === $pool_id ) {
		return '';
	}
	$pool_js    = esc_url_raw( sprintf( 'https://api.leadconnectorhq.com/loc/%s/pool/%s/number_pool.js', $location_id, $pool_id ) );
	$session_js = 'https://api.leadconnectorhq.com/js/user_session.js';
	$ver        = defined( 'LEAD_CONNECTOR_VERSION' ) ? LEAD_CONNECTOR_VERSION : '1.0.0';
	wp_enqueue_script( 'leadconnector-np-' . md5( $pool_js ), $pool_js, array(), $ver, true );
	wp_enqueue_script( 'leadconnector-user-session', esc_url_raw( $session_js ), array(), $ver, true );
	return '';
}

/**
 * Shortcode: form embed.
 *
 * @param array $params Shortcode attributes.
 * @return string
 */
function leadconnector_shortcode_form( $params ) {
	$form_id    = isset( $params['id'] ) ? leadconnector_sanitize_shortcode_value( $params['id'], 'id' ) : '';
	$form_title = isset( $params['title'] ) ? leadconnector_sanitize_shortcode_value( $params['title'], 'text' ) : '';
	if ( '' === $form_id ) {
		return '';
	}
	$ver = defined( 'LEAD_CONNECTOR_VERSION' ) ? LEAD_CONNECTOR_VERSION : '1.0.0';
	wp_enqueue_script( 'leadconnector-msgsndr-form-embed', esc_url_raw( LEADCONNECTOR_FORM_EMBED_SCRIPT_URL ), array(), $ver, true );

	return wp_kses(
		sprintf(
			'<iframe src="%s" style="width:100%%;height:100%%;border:none;" id="%s" data-layout="{\'id\':\'INLINE\',\'minimizedTitle\':\'\',\'isLeftAligned\':true,\'isRightAligned\':false,\'allowMinimize\':false}" data-trigger-type="alwaysShow" data-trigger-value="" data-activation-type="alwaysActivated" data-activation-value="" data-deactivation-type="neverDeactivate" data-deactivation-value="" data-form-name="%s" data-height="600" data-layout-iframe-id="inline-%s" data-form-id="%s" title="%s"></iframe>',
			esc_url( 'https://api.leadconnectorhq.com/widget/form/' . $form_id ),
			esc_attr( 'inline-' . $form_id ),
			esc_attr( $form_title ),
			esc_attr( $form_id ),
			esc_attr( $form_id ),
			esc_attr( $form_title )
		),
		leadconnector_shortcode_allowed_html()
	);
}

/**
 * Shortcode: calendar embed.
 *
 * @param array $params Shortcode attributes.
 * @return string
 */
function leadconnector_shortcode_calendar( $params ) {
	$slug = isset( $params['slug'] ) ? leadconnector_sanitize_shortcode_value( $params['slug'], 'id' ) : '';
	$id   = isset( $params['id'] ) ? leadconnector_sanitize_shortcode_value( $params['id'], 'id' ) : '';
	if ( '' === $slug && '' !== $id ) {
		$slug = $id;
	}
	$class = isset( $params['class'] ) ? leadconnector_sanitize_shortcode_value( $params['class'], 'attr' ) : '';
	$title = isset( $params['title'] ) ? leadconnector_sanitize_shortcode_value( $params['title'], 'text' ) : 'LeadConnector Calendar';
	if ( '' === $slug ) {
		return wp_kses( '<div class="leadconnector-calendar-error">Calendar slug or id is required.</div>', leadconnector_shortcode_allowed_html() );
	}
	$ver = defined( 'LEAD_CONNECTOR_VERSION' ) ? LEAD_CONNECTOR_VERSION : '1.0.0';
	wp_enqueue_script( 'leadconnector-msgsndr-form-embed', esc_url_raw( LEADCONNECTOR_FORM_EMBED_SCRIPT_URL ), array(), $ver, true );

	$iframe_base = LEADCONNECTOR_CALENDAR_IFRAME_BASE_URL;
	$iframe_src  = sprintf( '%s/widget/booking/%s', $iframe_base, $slug );
	$iframe_id   = $slug . '_' . time();

	return wp_kses(
		sprintf(
			'<iframe src="%s" style="width: 100%%;border:none;overflow: hidden;" scrolling="no" id="%s" class="%s" title="%s"></iframe>',
			esc_url( $iframe_src ),
			esc_attr( $iframe_id ),
			esc_attr( $class ),
			esc_attr( $title )
		),
		leadconnector_shortcode_allowed_html()
	);
}

/**
 * Shortcode: survey embed.
 *
 * @param array $params Shortcode attributes.
 * @return string
 */
function leadconnector_shortcode_survey( $params ) {
	$survey_id    = isset( $params['id'] ) ? leadconnector_sanitize_shortcode_value( $params['id'], 'id' ) : '';
	$survey_title = isset( $params['title'] ) ? leadconnector_sanitize_shortcode_value( $params['title'], 'text' ) : '';
	if ( '' === $survey_id ) {
		return '';
	}
	$ver = defined( 'LEAD_CONNECTOR_VERSION' ) ? LEAD_CONNECTOR_VERSION : '1.0.0';
	wp_enqueue_script( 'leadconnector-msgsndr-form-embed', esc_url_raw( LEADCONNECTOR_FORM_EMBED_SCRIPT_URL ), array(), $ver, true );

	return wp_kses(
		sprintf(
			'<iframe src="%s" style="border:none;width:100%%;" scrolling="no" id="%s" title="%s"></iframe>',
			esc_url( LEADCONNECTOR_SURVEY_WIDGET_BASE_URL . '/widget/survey/' . $survey_id ),
			esc_attr( $survey_id ),
			esc_attr( $survey_title )
		),
		leadconnector_shortcode_allowed_html()
	);
}

/**
 * Shortcode: quiz embed.
 *
 * @param array $params Shortcode attributes.
 * @return string
 */
function leadconnector_shortcode_quiz( $params ) {
	$quiz_id    = isset( $params['id'] ) ? leadconnector_sanitize_shortcode_value( $params['id'], 'id' ) : '';
	$quiz_title = isset( $params['title'] ) ? leadconnector_sanitize_shortcode_value( $params['title'], 'text' ) : '';
	if ( '' === $quiz_id ) {
		return '';
	}
	$ver = defined( 'LEAD_CONNECTOR_VERSION' ) ? LEAD_CONNECTOR_VERSION : '1.0.0';
	wp_enqueue_script( 'leadconnector-msgsndr-form-embed', esc_url_raw( LEADCONNECTOR_FORM_EMBED_SCRIPT_URL ), array(), $ver, true );

	return wp_kses(
		sprintf(
			'<iframe src="%s" style="border:none;width:100%%;" scrolling="no" id="%s" title="%s"></iframe>',
			esc_url( LEADCONNECTOR_QUIZ_WIDGET_BASE_URL . '/widget/quiz/' . $quiz_id ),
			esc_attr( $quiz_id ),
			esc_attr( $quiz_title )
		),
		leadconnector_shortcode_allowed_html()
	);
}

/**
 * Shortcode: reviews widget.
 *
 * @param array $params Shortcode attributes.
 * @return string
 */
function leadconnector_shortcode_reviews_widget( $params ) {
	$options      = get_option( LEAD_CONNECTOR_OPTION_NAME );
	$location_id  = leadconnector_sanitize_shortcode_value( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ?? '', 'id' );
	$widget_id    = isset( $params['id'] ) ? leadconnector_sanitize_shortcode_value( $params['id'], 'id' ) : '';
	$widget_title = isset( $params['title'] ) ? leadconnector_sanitize_shortcode_value( $params['title'], 'text' ) : 'Review Widget';
	if ( '' === $widget_id || '' === $location_id ) {
		return '';
	}
	$ver = defined( 'LEAD_CONNECTOR_VERSION' ) ? LEAD_CONNECTOR_VERSION : '1.0.0';
	wp_enqueue_script( 'leadconnector-review-widget', esc_url_raw( LEADCONNECTOR_REPUTATION_WIDGET_SCRIPT_URL ), array(), $ver, true );
	$style_handle = 'leadconnector-reviews-widget-inline';
	wp_register_style( $style_handle, false, array(), $ver );
	wp_enqueue_style( $style_handle );
	wp_add_inline_style( $style_handle, 'iframe.leadconnector_reviews_widget{width: inherit !important;}' );

	$iframe_src = esc_url( LEADCONNECTOR_REPUTATION_WIDGET_BASE_URL . '/' . $location_id . '?widgetId=' . rawurlencode( $widget_id ) );

	return wp_kses(
		sprintf(
			"<iframe class='leadconnector_reviews_widget lc_reviews_widget' src='%s' frameborder='0' scrolling='no' style='min-width: 100%%; width: 100%%;' title='%s'></iframe>",
			$iframe_src,
			esc_attr( $widget_title )
		),
		leadconnector_shortcode_allowed_html()
	);
}

/**
 * Schedule OAuth token refresh cron if not already scheduled.
 */
function leadconnector_schedule_oauth_refresh_cron() {
	if ( ! wp_next_scheduled( 'leadconnector_twicedaily_refresh_req_v2' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'leadconnector_twicedaily_refresh_req_v2' );
	}
}

/**
 * Collect funnel post IDs across current and legacy post types.
 *
 * @return int[]
 */
function leadconnector_get_legacy_funnel_post_ids() {
	$post_ids = array();

	foreach ( array( 'lc_funnels', 'leadconnector_funnels', lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE ) as $post_type ) {
		$found = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( is_array( $found ) ) {
			$post_ids = array_merge( $post_ids, $found );
		}
	}

	return array_values( array_unique( array_map( 'intval', $post_ids ) ) );
}

/**
 * Migrate legacy lc_* funnel post meta keys to leadconnector_* keys.
 *
 * @return void
 */
function leadconnector_migrate_legacy_funnel_meta_keys() {
	$meta_renames = array(
		'lc_step_url'                       => 'leadconnector_step_url',
		'lc_slug'                           => 'leadconnector_slug',
		'lc_step_meta'                      => 'leadconnector_step_meta',
		'lc_step_trackingCode'              => 'leadconnector_step_trackingCode',
		'lc_funnel_tracking_code'           => 'leadconnector_funnel_tracking_code',
		'lc_use_site_favicon'               => 'leadconnector_use_site_favicon',
		'lc_funnel_name'                    => 'leadconnector_funnel_name',
		'lc_funnel_id'                      => 'leadconnector_funnel_id',
		'lc_step_id'                        => 'leadconnector_step_id',
		'lc_step_name'                      => 'leadconnector_step_name',
		'lc_display_method'                 => 'leadconnector_display_method',
		'lc_include_tracking_code'          => 'leadconnector_include_tracking_code',
		'lc_include_wp_headers_and_footers' => 'leadconnector_include_wp_headers_and_footers',
	);

	foreach ( leadconnector_get_legacy_funnel_post_ids() as $post_id ) {
		foreach ( $meta_renames as $old_key => $new_key ) {
			$value = get_post_meta( $post_id, $old_key, true );
			if ( '' === $value || false === $value ) {
				continue;
			}

			update_post_meta( $post_id, $new_key, $value );
			delete_post_meta( $post_id, $old_key );
		}

		clean_post_cache( $post_id );
	}
}

/**
 * Migrate legacy funnel post types to leadconn_funnels.
 *
 * @return void
 */
function leadconnector_migrate_legacy_funnel_post_types() {
	foreach ( array( 'lc_funnels', 'leadconnector_funnels' ) as $legacy_post_type ) {
		$legacy_posts = get_posts(
			array(
				'post_type'              => $legacy_post_type,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! is_array( $legacy_posts ) ) {
			continue;
		}

		foreach ( $legacy_posts as $legacy_post ) {
			if ( ! $legacy_post instanceof WP_Post ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'        => $legacy_post->ID,
					'post_type' => lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE,
				)
			);
		}
	}
}

/**
 * Migrate legacy lc_seo_overrides_by_path-* options to leadconnector_* names.
 *
 * @return void
 */
function leadconnector_migrate_legacy_seo_option_keys() {
	$legacy_prefix = 'lc_seo_overrides_by_path-';
	$new_prefix    = 'leadconnector_seo_overrides_by_path-';
	$seo_fields    = array( 'page_title', 'meta_description', 'meta_keywords' );

	foreach ( wp_load_alloptions() as $option_name => $option_value ) {
		if ( 0 !== strpos( $option_name, $legacy_prefix ) ) {
			continue;
		}

		leadconnector_migrate_legacy_seo_option( $option_name, $legacy_prefix, $new_prefix, $option_value );
	}

	$paths = array( '', 'home', '/' );
	foreach ( array_keys( leadconnector_get_funnel_slug_index() ) as $slug ) {
		$paths[] = $slug;
	}

	foreach ( array_unique( $paths ) as $path ) {
		foreach ( $seo_fields as $field ) {
			$old_name = $legacy_prefix . $path . '-' . $field;
			leadconnector_migrate_legacy_seo_option( $old_name, $legacy_prefix, $new_prefix );
		}
	}
}

/**
 * Move one legacy SEO option to its leadconnector-prefixed name.
 *
 * @param string     $old_name       Legacy option name.
 * @param string     $legacy_prefix  Legacy option prefix.
 * @param string     $new_prefix     New option prefix.
 * @param mixed|null $known_value    Optional value when already loaded.
 * @return void
 */
function leadconnector_migrate_legacy_seo_option( $old_name, $legacy_prefix, $new_prefix, $known_value = null ) {
	if ( 0 !== strpos( $old_name, $legacy_prefix ) ) {
		return;
	}

	$value = null !== $known_value ? $known_value : get_option( $old_name, false );
	if ( false === $value ) {
		return;
	}

	$new_name = $new_prefix . substr( $old_name, strlen( $legacy_prefix ) );
	if ( false === get_option( $new_name, false ) ) {
		update_option( $new_name, $value );
	}

	delete_option( $old_name );
	wp_cache_delete( $old_name, 'options' );
	wp_cache_delete( $new_name, 'options' );
}

/**
 * Object-cache group for funnel slug index lookups.
 *
 * @return string
 */
function leadconnector_funnel_slug_index_cache_group() {
	return 'leadconnector_funnels';
}

/**
 * Object-cache key for the funnel slug index.
 *
 * @return string
 */
function leadconnector_funnel_slug_index_cache_key() {
	return 'slug_index';
}

/**
 * Persist the funnel slug index to the options table and object cache.
 *
 * @param array<string, int> $index Slug => post ID map.
 */
function leadconnector_persist_funnel_slug_index( array $index ) {
	update_option( lead_connector_constants\LEADCONNECTOR_FUNNEL_SLUG_INDEX_OPTION, $index, false );
	wp_cache_set(
		leadconnector_funnel_slug_index_cache_key(),
		$index,
		leadconnector_funnel_slug_index_cache_group()
	);
}

/**
 * Rebuild the funnel slug index from stored post meta (one-time / migration use).
 *
 * @return array<string, int> Slug => post ID map.
 */
function leadconnector_rebuild_funnel_slug_index() {
	$legacy_post_types = array(
		lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE,
		'lc_funnels',
		'leadconnector_funnels',
	);

	$post_ids = array();
	foreach ( $legacy_post_types as $post_type ) {
		$found = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( is_array( $found ) ) {
			$post_ids = array_merge( $post_ids, $found );
		}
	}

	$post_ids = array_unique( array_map( 'intval', $post_ids ) );
	$index    = array();

	foreach ( $post_ids as $post_id ) {
		$slug = get_post_meta( $post_id, lead_connector_constants\LEADCONNECTOR_FUNNEL_SLUG_META_KEY, true );
		if ( ! is_string( $slug ) || '' === $slug ) {
			$slug = get_post_meta( $post_id, 'lc_slug', true );
		}

		if ( is_string( $slug ) && '' !== $slug ) {
			$index[ $slug ] = $post_id;
		}
	}

	leadconnector_persist_funnel_slug_index( $index );

	return $index;
}

/**
 * Load the funnel slug index from object cache or options.
 *
 * @return array<string, int> Slug => post ID map.
 */
function leadconnector_get_funnel_slug_index() {
	$index = wp_cache_get(
		leadconnector_funnel_slug_index_cache_key(),
		leadconnector_funnel_slug_index_cache_group()
	);

	if ( is_array( $index ) ) {
		return $index;
	}

	$index = get_option( lead_connector_constants\LEADCONNECTOR_FUNNEL_SLUG_INDEX_OPTION, null );
	if ( ! is_array( $index ) ) {
		$index = leadconnector_rebuild_funnel_slug_index();
	} else {
		wp_cache_set(
			leadconnector_funnel_slug_index_cache_key(),
			$index,
			leadconnector_funnel_slug_index_cache_group()
		);
	}

	return $index;
}

/**
 * Resolve a funnel post ID from its public slug without a meta_value query.
 *
 * @param string $slug Public funnel path slug.
 * @return int Post ID, or 0 when not found.
 */
function leadconnector_get_funnel_post_id_by_slug( $slug ) {
	if ( ! is_string( $slug ) || '' === $slug ) {
		return 0;
	}

	$index = leadconnector_get_funnel_slug_index();

	return isset( $index[ $slug ] ) ? (int) $index[ $slug ] : 0;
}

/**
 * Resolve a funnel post object from its public slug.
 *
 * @param string $slug Public funnel path slug.
 * @return WP_Post|null
 */
function leadconnector_get_funnel_post_by_slug( $slug ) {
	$post_id = leadconnector_get_funnel_post_id_by_slug( $slug );
	if ( $post_id <= 0 ) {
		return null;
	}

	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE !== $post->post_type ) {
		return null;
	}

	// Enforce WordPress post-status visibility. The slug index is built with
	// 'post_status' => 'any' (see leadconnector_rebuild_funnel_slug_index())
	// and is pruned only on before_delete_post, so a funnel moved to Draft,
	// Pending, Private or Trash in wp-admin stays in the index. Without this
	// gate process_page_request() would keep serving it to anonymous visitors
	// with an HTTP 200 — i.e. unpublishing or trashing a funnel would not
	// actually take the page down.
	//
	// The check lives in the resolver rather than the caller so every caller
	// inherits it. current_user_can( 'read_post', ... ) preserves the ability
	// of an editor/administrator to view a non-published funnel.
	if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
		return null;
	}

	return $post;
}

/**
 * Register or update a funnel slug in the index after post meta is saved.
 *
 * @param int    $post_id Funnel post ID.
 * @param string $slug    Public funnel path slug.
 */
function leadconnector_register_funnel_slug( $post_id, $slug ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || ! is_string( $slug ) || '' === $slug ) {
		return;
	}

	$index = leadconnector_get_funnel_slug_index();

	foreach ( $index as $existing_slug => $existing_post_id ) {
		if ( (int) $existing_post_id === $post_id && $existing_slug !== $slug ) {
			unset( $index[ $existing_slug ] );
		}
	}

	$index[ $slug ] = $post_id;
	leadconnector_persist_funnel_slug_index( $index );
}

/**
 * Remove a funnel post from the slug index.
 *
 * @param int $post_id Funnel post ID.
 */
function leadconnector_unregister_funnel_post_from_slug_index( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}

	$index   = leadconnector_get_funnel_slug_index();
	$changed = false;

	foreach ( $index as $slug => $existing_post_id ) {
		if ( (int) $existing_post_id === $post_id ) {
			unset( $index[ $slug ] );
			$changed = true;
		}
	}

	if ( $changed ) {
		leadconnector_persist_funnel_slug_index( $index );
	}
}

/**
 * Keep the slug index in sync when a funnel post is deleted.
 *
 * @param int $post_id Post ID being deleted.
 */
function leadconnector_sync_funnel_slug_index_on_delete( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || lead_connector_constants\LEADCONNECTOR_CUSTOM_POST_TYPE !== $post->post_type ) {
		return;
	}

	leadconnector_unregister_funnel_post_from_slug_index( $post_id );
}
add_action( 'before_delete_post', 'leadconnector_sync_funnel_slug_index_on_delete' );
