<?php
/**
 *
 * LeadConnector Plugin
 * Copyright (C) 2020-2026 LeadConnector
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package LeadConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Canonical, properly-namespaced WP-CLI command.
	 *
	 * Prefer `wp leadconnector elementor-template-import` in all new tooling.
	 * The body below is intentionally duplicated (not extracted into a shared
	 * closure) so that this command and the legacy `elementor template-import`
	 * alias remain byte-for-byte equivalent in execution and output, and so
	 * the legacy block stays untouched until it is removed.
	 */
	WP_CLI::add_command(
		'leadconnector elementor-template-import',
		function ( $args, $assoc_args ) {
			$template_id = isset( $assoc_args['template_id'] ) ? intval( $assoc_args['template_id'] ) : 0;
			$page_id     = isset( $assoc_args['page_id'] ) ? intval( $assoc_args['page_id'] ) : 0;

			if ( ! $template_id || ! $page_id ) {
				WP_CLI::error( 'Both --template_id and --page_id are required.' );
			}

			// Check if Elementor is active.
			if ( ! class_exists( 'Elementor\Plugin' ) ) {
				WP_CLI::error( 'Elementor plugin is not active.' );
			}

			// Validate the template.
			$template_post = get_post( $template_id );
			if ( ! $template_post || 'elementor_library' !== $template_post->post_type ) {
				WP_CLI::error( "Template ID {$template_id} is not a valid Elementor template." );
			}

			// Validate the target page.
			$target_post = get_post( $page_id );
			if ( ! $target_post || 'page' !== $target_post->post_type ) {
				WP_CLI::error( "Page ID {$page_id} is not a valid WordPress page." );
			}

			// Fetch template content and settings.
			$template_data = get_post_meta( $template_id, '_elementor_data', true );
			$page_settings = get_post_meta( $template_id, '_elementor_page_settings', true );

			if ( ! $template_data ) {
				WP_CLI::error( "Template ID {$template_id} has no _elementor_data." );
			}

			// Import meta values from the template.
			$edit_mode         = get_post_meta( $template_id, '_elementor_edit_mode', true );
			$template_type     = get_post_meta( $template_id, '_elementor_template_type', true );
			$elementor_version = get_post_meta( $template_id, '_elementor_version', true );
			$wp_page_template  = get_post_meta( $template_id, '_wp_page_template', true );

			if ( $edit_mode ) {
				update_post_meta( $page_id, '_elementor_edit_mode', $edit_mode );
			}
			if ( $template_type ) {
				update_post_meta( $page_id, '_elementor_template_type', $template_type );
			}
			if ( $elementor_version ) {
				update_post_meta( $page_id, '_elementor_version', $elementor_version );
			}

			/*
			 * Always force the Elementor full-width template; the original
			 * $wp_page_template from the source template is intentionally ignored.
			 */
			update_post_meta( $page_id, '_wp_page_template', 'elementor_theme' );

			// SECURITY: do NOT call maybe_unserialize() here. get_post_meta()
			// has already run the stored value through maybe_unserialize()
			// internally, so this would be a *second* unserialize pass over a
			// plain string. Because maybe_serialize() re-serializes strings
			// that merely look serialized, a meta value written as the literal
			// `O:8:"Evil":0:{}` is returned by get_post_meta() as that string
			// and a second maybe_unserialize() would revive it into a live
			// object — a PHP object-injection sink reachable by anyone who can
			// edit an elementor_library post. Pass the values through as-is.
			update_post_meta( $page_id, '_elementor_data', $template_data );
			if ( ! empty( $page_settings ) ) {
				update_post_meta( $page_id, '_elementor_page_settings', $page_settings );
			}

			// Optional: clear Elementor cache.
			if ( method_exists( '\Elementor\Plugin', 'instance' ) ) {
				$elementor = \Elementor\Plugin::instance();
				if ( method_exists( $elementor, 'files_manager' ) ) {
					$elementor->files_manager->clear_cache();
				}
			}

			WP_CLI::success( "Template {$template_id} imported successfully into page {$page_id}." );
		}
	);

	/**
	 * Backward-compatible alias for the Elementor template import command.
	 *
	 * @deprecated Use `wp leadconnector elementor-template-import` instead.
	 *
	 * Registered under the top-level `elementor` namespace for backward
	 * compatibility. This alias will be removed in a future release once all
	 * known callers have been migrated to the canonical command above.
	 */
	WP_CLI::add_command(
		'elementor template-import',
		function ( $args, $assoc_args ) {
			$template_id = isset( $assoc_args['template_id'] ) ? intval( $assoc_args['template_id'] ) : 0;
			$page_id     = isset( $assoc_args['page_id'] ) ? intval( $assoc_args['page_id'] ) : 0;

			if ( ! $template_id || ! $page_id ) {
				WP_CLI::error( 'Both --template_id and --page_id are required.' );
			}

			// Check if Elementor is active.
			if ( ! class_exists( 'Elementor\Plugin' ) ) {
				WP_CLI::error( 'Elementor plugin is not active.' );
			}

			// Validate the template.
			$template_post = get_post( $template_id );
			if ( ! $template_post || 'elementor_library' !== $template_post->post_type ) {
				WP_CLI::error( "Template ID {$template_id} is not a valid Elementor template." );
			}

			// Validate the target page.
			$target_post = get_post( $page_id );
			if ( ! $target_post || 'page' !== $target_post->post_type ) {
				WP_CLI::error( "Page ID {$page_id} is not a valid WordPress page." );
			}

			// Fetch template content and settings.
			$template_data = get_post_meta( $template_id, '_elementor_data', true );
			$page_settings = get_post_meta( $template_id, '_elementor_page_settings', true );

			if ( ! $template_data ) {
				WP_CLI::error( "Template ID {$template_id} has no _elementor_data." );
			}

			// Import meta values from the template.
			$edit_mode         = get_post_meta( $template_id, '_elementor_edit_mode', true );
			$template_type     = get_post_meta( $template_id, '_elementor_template_type', true );
			$elementor_version = get_post_meta( $template_id, '_elementor_version', true );
			$wp_page_template  = get_post_meta( $template_id, '_wp_page_template', true );

			if ( $edit_mode ) {
				update_post_meta( $page_id, '_elementor_edit_mode', $edit_mode );
			}
			if ( $template_type ) {
				update_post_meta( $page_id, '_elementor_template_type', $template_type );
			}
			if ( $elementor_version ) {
				update_post_meta( $page_id, '_elementor_version', $elementor_version );
			}

			/*
			 * Always force the Elementor full-width template; the original
			 * $wp_page_template from the source template is intentionally ignored.
			 */
			update_post_meta( $page_id, '_wp_page_template', 'elementor_theme' );

			// SECURITY: do NOT call maybe_unserialize() here. get_post_meta()
			// has already run the stored value through maybe_unserialize()
			// internally, so this would be a *second* unserialize pass over a
			// plain string. Because maybe_serialize() re-serializes strings
			// that merely look serialized, a meta value written as the literal
			// `O:8:"Evil":0:{}` is returned by get_post_meta() as that string
			// and a second maybe_unserialize() would revive it into a live
			// object — a PHP object-injection sink reachable by anyone who can
			// edit an elementor_library post. Pass the values through as-is.
			update_post_meta( $page_id, '_elementor_data', $template_data );
			if ( ! empty( $page_settings ) ) {
				update_post_meta( $page_id, '_elementor_page_settings', $page_settings );
			}

			// Optional: clear Elementor cache.
			if ( method_exists( '\Elementor\Plugin', 'instance' ) ) {
				$elementor = \Elementor\Plugin::instance();
				if ( method_exists( $elementor, 'files_manager' ) ) {
					$elementor->files_manager->clear_cache();
				}
			}

			WP_CLI::success( "Template {$template_id} imported successfully into page {$page_id}." );
		}
	);

	/**
	 * Updates a page's Elementor data from a JSON file on the server.
	 *
	 * Bypasses WP-CLI argument length limits for large _elementor_data payloads.
	 * Used by AI page customization (colors, content, images).
	 *
	 * Usage:
	 *   wp leadconnector elementor-template-update --page_id=1500 --file=/path/to/update.json
	 */
	WP_CLI::add_command(
		'leadconnector elementor-template-update',
		function ( $args, $assoc_args ) {
			$page_id   = isset( $assoc_args['page_id'] ) ? intval( $assoc_args['page_id'] ) : 0;
			$file_path = isset( $assoc_args['file'] ) ? $assoc_args['file'] : '';

			if ( ! $page_id || ! $file_path ) {
				WP_CLI::error( 'Both --page_id and --file are required.' );
			}

			if ( ! class_exists( 'Elementor\Plugin' ) ) {
				WP_CLI::error( 'Elementor plugin is not active.' );
			}

			$target_post = get_post( $page_id );
			if ( ! $target_post || 'page' !== $target_post->post_type ) {
				WP_CLI::error( "Page ID {$page_id} is not a valid WordPress page." );
			}

			if ( ! file_exists( $file_path ) ) {
				WP_CLI::error( "File not found: {$file_path}" );
			}

			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( empty( $wp_filesystem ) ) {
				WP_CLI::error( 'Unable to initialize filesystem.' );
			}

			$json_content = $wp_filesystem->get_contents( $file_path );
			if ( false === $json_content ) {
				WP_CLI::error( "Failed to read file: {$file_path}" );
			}

			$decoded = json_decode( $json_content, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				WP_CLI::error( 'Invalid JSON in file: ' . json_last_error_msg() );
			}

			$content_array  = isset( $decoded['content'] ) ? $decoded['content'] : $decoded;
			$elementor_json = wp_json_encode( $content_array );
			update_post_meta( $page_id, '_elementor_data', wp_slash( $elementor_json ) );

			if ( isset( $decoded['page_settings'] ) && ! empty( $decoded['page_settings'] ) ) {
				update_post_meta( $page_id, '_elementor_page_settings', $decoded['page_settings'] );
			}

			update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $page_id, '_wp_page_template', 'elementor_theme' );

			if ( method_exists( '\Elementor\Plugin', 'instance' ) ) {
				$elementor = \Elementor\Plugin::instance();
				if ( method_exists( $elementor, 'files_manager' ) ) {
					$elementor->files_manager->clear_cache();
				}

				if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
					$post_css = new \Elementor\Core\Files\CSS\Post( $page_id );
					$post_css->update();
				}
			}

			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			WP_CLI::success( "Elementor data updated on page {$page_id}." );
		}
	);
}
