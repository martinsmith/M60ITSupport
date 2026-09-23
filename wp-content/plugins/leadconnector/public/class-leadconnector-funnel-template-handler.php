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

/**
 * Lead Connector Native Template Handler
 *
 * This file is loaded by the `template_include` filter in the main plugin
 * when a funnel step is set to display natively with WordPress headers and footers.
 */

/**
 * M5: Keep template variables out of the global namespace (PrefixAllGlobals).
 */
function leadconnector_render_native_template() {
	// --- 1. Get All Data ---
	$post_id         = get_the_ID();
	$funnel_step_url = get_post_meta( $post_id, 'leadconnector_step_url', true );

	// Get tracking codes and other settings.
	$leadconnector_post_meta            = get_post_meta( $post_id, 'leadconnector_step_meta', true );
	$leadconnector_step_tracking_code   = get_post_meta( $post_id, 'leadconnector_step_trackingCode', true );
	$leadconnector_funnel_tracking_code = get_post_meta( $post_id, 'leadconnector_funnel_tracking_code', true );
	$leadconnector_use_site_favicon     = get_post_meta( $post_id, 'leadconnector_use_site_favicon', true );

	// --- 2. Instantiate Admin Class to Use Helper Methods ---
	// Load the admin class file if not already loaded
	if ( ! class_exists( 'LeadConnector_Admin' ) ) {
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-leadconnector-admin.php';
	}

	// Get plugin name and version from constants or defaults.
	$leadconnector_plugin_name    = defined( 'LEAD_CONNECTOR_PLUGIN_NAME' ) ? LEAD_CONNECTOR_PLUGIN_NAME : 'LeadConnector';
	$leadconnector_plugin_version = defined( 'LEAD_CONNECTOR_VERSION' ) ? LEAD_CONNECTOR_VERSION : '3.0.15';

	// Instantiate the admin class to access its methods.
	$leadconnector_admin = new LeadConnector_Admin( $leadconnector_plugin_name, $leadconnector_plugin_version );

	// Use the admin class methods to get properly formatted meta fields and tracking codes.
	$post_meta_fields    = $leadconnector_admin->get_meta_fields( $leadconnector_post_meta );
	$head_tracking_code  = $leadconnector_admin->get_tracking_code( $leadconnector_funnel_tracking_code, true, true );
	$head_tracking_code .= $leadconnector_admin->get_tracking_code( $leadconnector_step_tracking_code, true );

	$footer_tracking_code  = $leadconnector_admin->get_tracking_code( $leadconnector_funnel_tracking_code, false, true );
	$footer_tracking_code .= $leadconnector_admin->get_tracking_code( $leadconnector_step_tracking_code, false );

	// Favicon Logic.
	$favicon_code = '';
	if ( $leadconnector_use_site_favicon ) {
		$favicon_url = get_site_icon_url();
		if ( $favicon_url ) {
			$favicon_code = '<link rel="icon" type="image/x-icon" href="' . esc_url( $favicon_url ) . '">';
		}
	}

	// --- 2b. Fetch Native HTML Content ---
	$native_html_content = '';
	$native_head_content = '';
	$native_body_content = '';

	// Fetch the HTML content using the get_page_iframe_native method.
	$native_html_content = $leadconnector_admin->get_page_iframe_native(
		$funnel_step_url,
		$leadconnector_post_meta,
		$leadconnector_step_tracking_code,
		$leadconnector_funnel_tracking_code,
		$leadconnector_use_site_favicon
	);

	$leadconnector_logger = LeadConnector_Logger::get_instance();
	$leadconnector_logger->debug(
		'Template Handler',
		array(
			'post_id'        => $post_id,
			'funnel_url'     => $funnel_step_url,
			'content_status' => empty( $native_html_content ) ? 'EMPTY' : strlen( $native_html_content ) . ' bytes',
		)
	);

	// Extract head and body content from the fetched HTML.
	$leadconnector_head_scripts = array();
	$leadconnector_body_scripts = array();

	if ( ! empty( $native_html_content ) ) {
		// Native funnel HTML is parsed with regex to preserve HTML5 self-closing
		// tags. Trust model documented near setup_native_display_with_wp_headers
		// in admin/class-leadconnector-admin.php.

		// Use regex-based extraction to preserve HTML5 elements like video, source, etc.
		// DOMDocument has issues with HTML5 self-closing tags like <source>.

		// Extract HEAD content using regex.
		if ( preg_match( '/<head[^>]*>(.*?)<\/head>/is', $native_html_content, $head_matches ) ) {
			$head_content = $head_matches[1];
			// Remove title tags as WordPress will handle those.
			$native_head_content = preg_replace( '/<title[^>]*>.*?<\/title>/is', '', $head_content );
		}

		// Extract BODY content using regex to preserve HTML5 elements.
		if ( preg_match( '/<body[^>]*>(.*)<\/body>/is', $native_html_content, $body_matches ) ) {
			$native_body_content = $body_matches[1];
		} else {
			// If no body tag found, try to use the entire content
			// Remove doctype, html, head tags if present.
			$native_body_content = preg_replace( '/^.*?<body[^>]*>/is', '', $native_html_content );
			$native_body_content = preg_replace( '/<\/body>.*$/is', '', $native_body_content );

			// If still no body content extracted, use original content.
			if ( empty( trim( $native_body_content ) ) ) {
				$native_body_content = $native_html_content;
			}
		}

		// --- Separate scripts and styles from the HTML so wp_kses() can sanitise
		// the surrounding markup while scripts and stylesheets are re-injected
		// via WordPress's script/style APIs. wp_kses() text-escapes content
		// inside <script> and <style> tags, which corrupts inline JS and
		// breaks CSS selectors that use the `>` combinator (turning
		// `.c-row > .inner` into `.c-row &gt; .inner`). Extraction must run
		// before kses to preserve the original source. ---
		$leadconnector_head_styles = array();
		$leadconnector_body_styles = array();

		if ( ! empty( $native_head_content ) ) {
			$head_script_parts          = $leadconnector_admin->extract_scripts_from_html( $native_head_content );
			$native_head_content        = $head_script_parts['html'];
			$leadconnector_head_scripts = $head_script_parts['scripts'];

			$head_style_parts          = $leadconnector_admin->extract_styles_from_html( $native_head_content );
			$native_head_content       = $head_style_parts['html'];
			$leadconnector_head_styles = $head_style_parts['styles'];
		}

		if ( ! empty( $native_body_content ) ) {
			$body_script_parts          = $leadconnector_admin->extract_scripts_from_html( $native_body_content );
			$native_body_content        = $body_script_parts['html'];
			$leadconnector_body_scripts = $body_script_parts['scripts'];

			$body_style_parts          = $leadconnector_admin->extract_styles_from_html( $native_body_content );
			$native_body_content       = $body_style_parts['html'];
			$leadconnector_body_styles = $body_style_parts['styles'];
		}
	}

	// --- 3. Add custom body class for this page ---
	add_filter(
		'body_class',
		function ( $classes ) {
			$classes[] = 'leadconnector-native-page';
			return $classes;
		}
	);

	// 3.0.32: emit the native-mode Content-Security-Policy `<meta>` tag at
	// the very top of `<head>` (priority `1`) so the browser applies the
	// policy to every subsequent funnel asset. The admin instance built
	// above already exposes the helper; see funnel_native_mode_csp_directives()
	// for the directive map and the filter that extends it.
	add_action( 'wp_head', array( $leadconnector_admin, 'output_native_mode_csp' ), 1 );

	// --- 4. Add all head content to wp_head ---
	add_action(
		'wp_head',
		function () use ( $leadconnector_admin, $favicon_code, $post_meta_fields, $head_tracking_code, $native_head_content, $leadconnector_head_scripts, $leadconnector_head_styles ) {
			$leadconnector_head_allowed = $leadconnector_admin->funnel_html_allowed_tags();

			// Output favicon if using site favicon.
			if ( ! empty( $favicon_code ) ) {
				echo wp_kses( $favicon_code, $leadconnector_head_allowed );
			}

			if ( ! empty( $post_meta_fields ) ) {
				echo wp_kses( $post_meta_fields, $leadconnector_head_allowed );
			}

			// Output native HTML head content (meta, links — styles & scripts extracted separately).
			if ( ! empty( $native_head_content ) ) {
				echo wp_kses( $native_head_content, $leadconnector_head_allowed );
			}

			// Re-inject head stylesheets via WordPress styles API. wp_kses
			// would otherwise text-escape `>`, `<`, `&` etc. inside <style>
			// tag bodies and break CSS selectors / property values.
			if ( ! empty( $leadconnector_head_styles ) ) {
				$leadconnector_admin->output_extracted_styles( $leadconnector_head_styles );
			}

			// Re-inject head scripts via WordPress script API (wp_kses strips inline JS).
			if ( ! empty( $leadconnector_head_scripts ) ) {
				$leadconnector_admin->output_extracted_scripts( $leadconnector_head_scripts, 'head' );
			}

			// Activate font stylesheets whose onload handler was stripped by wp_kses.
			if ( ! empty( $native_head_content ) ) {
				$leadconnector_admin->output_font_swap_script();
			}

			// H5: extract <script> tags from the tracking code before kses
			// because funnel_html_allowed_tags() no longer permits <script>
			// in its allowlist. Non-script HTML (noscript pixels, tracking
			// comments) still goes through kses; scripts are re-emitted via
			// the WordPress script API.
			if ( ! empty( $head_tracking_code ) ) {
				$head_tracking_parts = $leadconnector_admin->extract_scripts_from_html( $head_tracking_code );
				echo wp_kses( $head_tracking_parts['html'], $leadconnector_head_allowed );
				$leadconnector_admin->output_extracted_scripts( $head_tracking_parts['scripts'], 'head' );
			}

			$leadconnector_native_css = '#wpadminbar { display: none !important; }'
			. ' html { margin-top: 0 !important; }'
			. ' body.leadconnector-native-page .leadconnector-native-content { width: 100%; }'
			. ' body.leadconnector-native-page .entry-content { padding: 0 !important; margin: 0 !important; }';

			wp_register_style( 'leadconnector-native-page', false, array(), LEAD_CONNECTOR_VERSION );
			wp_enqueue_style( 'leadconnector-native-page' );
			wp_add_inline_style( 'leadconnector-native-page', $leadconnector_native_css );

			// D2: ship the crypto.randomUUID polyfill as a real static asset
			// instead of an inline script per WP.org reviewer guidance. The
			// remaining body-class assertion is funnel-template-specific so it
			// stays as a small inline footer script attached to the polyfill
			// handle (which is enqueued in the footer).
			$leadconnector_polyfills_path = plugin_dir_path( __DIR__ ) . 'public/js/leadconnector-native-polyfills.js';
			$leadconnector_polyfills_ver  = file_exists( $leadconnector_polyfills_path ) ? filemtime( $leadconnector_polyfills_path ) : LEAD_CONNECTOR_VERSION;
			wp_enqueue_script(
				'leadconnector-native-polyfills',
				plugin_dir_url( __DIR__ ) . 'public/js/leadconnector-native-polyfills.js',
				array(),
				$leadconnector_polyfills_ver,
				true
			);

			$leadconnector_body_class_js = "document.addEventListener('DOMContentLoaded',function(){"
			. "if(document.body&&!document.body.classList.contains('leadconnector-native-page')){"
			. "document.body.classList.add('leadconnector-native-page');}});";

			wp_add_inline_script( 'leadconnector-native-polyfills', $leadconnector_body_class_js );
		}
	);

	// --- 5. Add footer tracking code, body styles, and body scripts before wp_footer ---
	add_action(
		'wp_footer',
		function () use ( $leadconnector_admin, $footer_tracking_code, $leadconnector_body_scripts, $leadconnector_body_styles ) {
			// Re-inject body styles that were separated before wp_kses() to
			// avoid CSS-source corruption from kses entity-escaping.
			if ( ! empty( $leadconnector_body_styles ) ) {
				$leadconnector_admin->output_extracted_styles( $leadconnector_body_styles );
			}

			// Re-inject body scripts that were separated before wp_kses().
			if ( ! empty( $leadconnector_body_scripts ) ) {
				$leadconnector_admin->output_extracted_scripts( $leadconnector_body_scripts, 'footer' );
			}

			// H5: extract <script> tags from the footer tracking code
			// before kses, then route them through the WordPress script API.
			if ( ! empty( $footer_tracking_code ) ) {
				$footer_tracking_parts = $leadconnector_admin->extract_scripts_from_html( $footer_tracking_code );
				echo wp_kses( $footer_tracking_parts['html'], $leadconnector_admin->funnel_html_allowed_tags() );
				$leadconnector_admin->output_extracted_scripts( $footer_tracking_parts['scripts'], 'footer' );
			}
		}
	);

	// --- 6. Render the Page ---

	// Use WordPress's natural template loading
	// This approach works with block themes, classic themes, page builders like Divi, Elementor, etc.

	get_header();

	// Check if we're in the WordPress loop.
	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			?>
		<main id="main" class="site-main">
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<?php if ( ! empty( $native_body_content ) ) : ?>
					<div class="leadconnector-native-content">
						<?php
						echo wp_kses( $native_body_content, $leadconnector_admin->native_body_allowed_tags() );
						?>
					</div>
				<?php else : ?>
					<div class="entry-content" style="padding: 2em; text-align: center;">
						<h2><?php esc_html_e( 'Error: Failed to load funnel content.', 'leadconnector' ); ?></h2>
						<p><?php esc_html_e( 'Please check if the funnel URL is valid.', 'leadconnector' ); ?></p>
						<?php
						/**
						 * D4: previously rendered a visible "Debug Information" panel into the
						 * front-end response whenever WP_DEBUG was on. Production sites commonly
						 * enable WP_DEBUG (with WP_DEBUG_DISPLAY = false) for ops visibility, so
						 * this leaked plugin internals to anonymous visitors. Diagnostic data is
						 * now routed to error_log() instead and never appears in the rendered
						 * markup.
						 */
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							if ( empty( $native_html_content ) ) {
								$leadconnector_debug_status = 'Empty/No content received';
							} elseif ( strpos( (string) $native_html_content, '<!-- Error' ) === 0 ) {
								$leadconnector_debug_status = 'Error returned: ' . preg_replace( '/^<!--\s*Error:\s*|\s*-->$/i', '', (string) $native_html_content );
							} else {
								$leadconnector_debug_status = 'Content received but parsing failed';
							}

							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							error_log(
								sprintf(
									'[LeadConnector] Native funnel render failed. Post ID: %1$s, Funnel URL: %2$s, Content Status: %3$s',
									(string) $post_id,
									(string) $funnel_step_url,
									$leadconnector_debug_status
								)
							);
						}
						?>
					</div>
				<?php endif; ?>
			</article>
		</main>
			<?php
		}
	} else {
		// Fallback if post loop doesn't work.
		?>
	<main id="main" class="site-main">
		<?php if ( ! empty( $native_body_content ) ) : ?>
			<div class="leadconnector-native-content">
				<?php
				echo wp_kses( $native_body_content, $leadconnector_admin->native_body_allowed_tags() );
				?>
			</div>
		<?php else : ?>
			<div style="padding: 2em; text-align: center;">
				<h2><?php esc_html_e( 'Error: Failed to load funnel content.', 'leadconnector' ); ?></h2>
				<p><?php esc_html_e( 'Please check if the funnel URL is valid.', 'leadconnector' ); ?></p>
			</div>
		<?php endif; ?>
	</main>
		<?php
	}

	get_footer();
}

leadconnector_render_native_template();