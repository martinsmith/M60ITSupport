<?php
/**
 * AI page draft preview URL helpers and front-end bootstrap.
 *
 * Draft pages are not in the rewrite index, so pretty-permalink preview URLs
 * (e.g. /preview/?preview=true) 404. Always mint ?page_id=N links instead.
 *
 * WP-CLI often runs as an admin user; preview iframes are anonymous (user 0).
 * Nonces must be created as user 0, and we also accept a signed LC token.
 *
 * @package LeadConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preview signing secret (site API key, else WP salt).
 *
 * @return string
 */
function leadconnector_get_ai_page_preview_secret(): string {
	$options = get_option( defined( 'LEAD_CONNECTOR_OPTION_NAME' ) ? LEAD_CONNECTOR_OPTION_NAME : 'lead_connector_plugin_options', array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$api_key = isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] )
		? trim( (string) $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_API_KEY ] )
		: '';

	if ( '' !== $api_key ) {
		return $api_key;
	}

	return wp_salt( 'leadconnector_ai_page_preview' );
}

/**
 * Mint a signed preview token for anonymous iframe viewing.
 *
 * @param int $page_id WordPress page ID.
 * @return string Base64-encoded token.
 */
function leadconnector_mint_ai_page_preview_token( int $page_id ): string {
	$expiry  = time() + DAY_IN_SECONDS;
	$payload = $page_id . ':' . $expiry;
	$sig     = substr( hash_hmac( 'sha256', $payload, leadconnector_get_ai_page_preview_secret() ), 0, 32 );

	// Encoding a signed preview token, not obfuscating source.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	return base64_encode( $payload . ':' . $sig );
}

/**
 * Verify a signed preview token.
 *
 * @param string $token   Token from query string.
 * @param int    $page_id Expected page ID.
 * @return bool
 */
function leadconnector_verify_ai_page_preview_token( string $token, int $page_id ): bool {
	// Decoding a signed preview token, not obfuscating source.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	$decoded = base64_decode( $token, true );
	if ( ! is_string( $decoded ) || '' === $decoded ) {
		return false;
	}

	$parts = explode( ':', $decoded );
	if ( 3 !== count( $parts ) ) {
		return false;
	}

	list( $id, $expiry, $sig ) = $parts;
	if ( (int) $id !== $page_id || time() > (int) $expiry ) {
		return false;
	}

	$payload  = $id . ':' . $expiry;
	$expected = substr( hash_hmac( 'sha256', $payload, leadconnector_get_ai_page_preview_secret() ), 0, 32 );

	return hash_equals( $expected, $sig );
}

/**
 * Build a preview URL for an AI WordPress page.
 *
 * @param WP_Post $post Page post object.
 * @return string Preview URL or empty string.
 */
function leadconnector_build_ai_page_preview_url( WP_Post $post ): string {
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return '';
	}

	// Preview iframes are anonymous; WP-CLI defaults to admin and breaks nonces.
	wp_set_current_user( 0 );

	$nonce = wp_create_nonce( 'post_preview_' . $post->ID );
	$token = leadconnector_mint_ai_page_preview_token( (int) $post->ID );

	// Unpublished pages must use page_id — slug paths 404 for drafts.
	if ( 'publish' !== $post->post_status ) {
		return add_query_arg(
			array(
				'page_id'             => $post->ID,
				'preview'             => 'true',
				'preview_nonce'       => $nonce,
				'leadconnector_token' => $token,
			),
			home_url( '/' )
		);
	}

	$preview_url = get_preview_post_link( $post );
	if ( ! is_string( $preview_url ) || '' === $preview_url ) {
		return '';
	}

	return add_query_arg(
		array(
			'preview_nonce'       => $nonce,
			'leadconnector_token' => $token,
		),
		$preview_url
	);
}

/**
 * Whether the current request is authorized to preview a page.
 *
 * @param int $page_id WordPress page ID.
 * @return bool
 */
function leadconnector_is_ai_page_preview_authorized( int $page_id ): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['leadconnector_token'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field( wp_unslash( $_GET['leadconnector_token'] ) );
		if ( leadconnector_verify_ai_page_preview_token( $token, $page_id ) ) {
			return true;
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['preview_nonce'] ) ) {
		// The preview iframe is anonymous, so the nonce this branch verifies
		// was minted as user 0 and must be checked in that same context.
		// Previously wp_set_current_user( 0 ) ran here unconditionally on the
		// mere *presence* of the parameter and was never restored, so any
		// unauthenticated attacker could hand a logged-in user a link with a
		// junk preview_nonce and de-authenticate that user for the remainder
		// of the request. Capture the current user first and always restore
		// it, whatever the verification outcome.
		$leadconnector_previous_user_id = get_current_user_id();

		wp_set_current_user( 0 );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$preview_nonce = sanitize_text_field( wp_unslash( $_GET['preview_nonce'] ) );
		$is_valid      = (bool) wp_verify_nonce( $preview_nonce, 'post_preview_' . $page_id );

		wp_set_current_user( $leadconnector_previous_user_id );

		if ( $is_valid ) {
			return true;
		}
	}

	return false;
}

/**
 * Force the main query to render a draft page preview.
 *
 * @return void
 */
function leadconnector_render_ai_page_preview(): void {
	if ( is_admin() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['page_id'] ) || empty( $_GET['preview'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page_id = absint( wp_unslash( $_GET['page_id'] ) );
	if ( ! $page_id || ! leadconnector_is_ai_page_preview_authorized( $page_id ) ) {
		return;
	}

	$post = get_post( $page_id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return;
	}

	// The post is injected straight into $wp_query below, which bypasses the
	// post_status filtering WP_Query would normally apply and every
	// capability gate core applies in _show_post_preview(). Without an
	// explicit allowlist a preview URL would render trashed and auto-draft
	// pages as well as the drafts the feature is for. Restrict to the
	// statuses a preview is legitimately for.
	$leadconnector_previewable_statuses = array( 'draft', 'pending', 'future', 'private', 'publish' );
	if ( ! in_array( $post->post_status, $leadconnector_previewable_statuses, true ) ) {
		return;
	}

	global $wp_query;

	$wp_query->posts                 = array( $post );
	$wp_query->post                  = $post;
	$wp_query->queried_object        = $post;
	$wp_query->queried_object_id     = $page_id;
	$wp_query->post_count            = 1;
	$wp_query->found_posts           = 1;
	$wp_query->max_num_pages         = 1;
	$wp_query->is_404                = false;
	$wp_query->is_page               = true;
	$wp_query->is_singular           = true;
	$wp_query->is_single             = false;
	$wp_query->is_home               = false;
	$wp_query->is_front_page         = false;
	$wp_query->is_preview            = true;
	$wp_query->query_vars['page_id'] = $page_id;
	$wp_query->query_vars['preview'] = 'true';

	status_header( 200 );
	nocache_headers();
}

add_action( 'template_redirect', 'leadconnector_render_ai_page_preview', 0 );
