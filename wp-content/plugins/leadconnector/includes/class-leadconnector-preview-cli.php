<?php
/**
 * WP-CLI helpers for AI page draft preview URLs.
 *
 * Rocket's interactive shell blocks `wp eval`; this command works like other
 * `wp post get` / `wp leadconnector elementor-*` subcommands.
 *
 * @package LeadConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once __DIR__ . '/class-leadconnector-ai-page-preview.php';

WP_CLI::add_command(
	'leadconnector preview-url',
	function ( $args, $assoc_args ) {
		$page_id = isset( $assoc_args['page_id'] ) ? intval( $assoc_args['page_id'] ) : 0;

		if ( ! $page_id ) {
			WP_CLI::error( '--page_id is required.' );
		}

		$post = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			WP_CLI::error( "Page ID {$page_id} is not a valid WordPress page." );
		}

		$preview_url = leadconnector_build_ai_page_preview_url( $post );
		if ( '' === $preview_url ) {
			WP_CLI::error( "Could not build preview URL for page {$page_id}." );
		}

		WP_CLI::line( $preview_url );
	}
);
