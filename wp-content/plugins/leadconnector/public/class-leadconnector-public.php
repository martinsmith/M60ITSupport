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

require_once __DIR__ . '/class-leadconnector-custom-value-string-replacable.php';

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.leadconnectorhq.com
 * @since      1.0.0
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/public
 */

/**
 * Public-facing hooks: custom-value replacement, shortcodes, and front-end assets.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    LeadConnector
 * @subpackage LeadConnector/public
 */
class LeadConnector_Public {




	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-leadconnector-constants.php';
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Add filters for custom value placeholders across various content types.

		// Post/Page Content.
		add_filter( 'the_content', array( $this, 'replace_custom_value_placeholders' ), 20 );
		add_filter( 'the_excerpt', array( $this, 'replace_custom_value_placeholders' ), 20 );

		// Titles (plain-text contexts — escape the full returned string).
		add_filter( 'the_title', array( $this, 'replace_custom_value_placeholders_in_text' ), 20 );
		add_filter( 'wp_title', array( $this, 'replace_custom_value_placeholders_in_text' ), 20 );
		add_filter( 'document_title_parts', array( $this, 'replace_custom_value_in_title_parts' ), 20 );
		add_filter( 'pre_get_document_title', array( $this, 'replace_custom_value_placeholders_in_text' ), 20 );

		// Widget Content.
		add_filter( 'widget_text', array( $this, 'replace_custom_value_placeholders' ), 20 );
		add_filter( 'widget_text_content', array( $this, 'replace_custom_value_placeholders' ), 20 );
		add_filter( 'widget_title', array( $this, 'replace_custom_value_placeholders_in_text' ), 20 );

		// Meta Description and SEO.
		add_filter( 'get_the_excerpt', array( $this, 'replace_custom_value_placeholders' ), 20 );
		add_filter( 'meta_description', array( $this, 'replace_custom_value_placeholders_in_text' ), 20 );

		// Block content (navigation blocks are intentionally excluded — see
		// replace_custom_value_in_blocks()).
		add_filter( 'render_block', array( $this, 'replace_custom_value_in_blocks' ), 20, 2 );

		// Comments.
		add_filter( 'comment_text', array( $this, 'replace_custom_value_placeholders' ), 20 );
		add_filter( 'comment_excerpt', array( $this, 'replace_custom_value_placeholders' ), 20 );

		// Elementor highlight functionality.
		add_action( 'wp_head', array( $this, 'inject_elementor_highlight_styles' ), 999 );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( 'leadconnector-public', plugin_dir_url( __FILE__ ) . 'css/leadconnector-public.css', array(), $this->version, 'all' );

		if ( get_query_var( 'elementor_highlight' ) === 'true' ) {
			wp_enqueue_style(
				'leadconnector-elementor-highlight',
				plugin_dir_url( __FILE__ ) . 'css/leadconnector-elementor-highlight.css',
				array(),
				$this->version
			);
		}
	}


	/**
	 * Validates and sanitizes content before processing
	 *
	 * @param mixed $content The content to validate.
	 * @return LeadConnector_Custom_Value_String_Replacable Sanitized content wrapper.
	 */
	private function sanitize_content( $content ): LeadConnector_Custom_Value_String_Replacable {

		// Handle null or empty content.
		if ( null === $content ) {
			return new LeadConnector_Custom_Value_String_Replacable( null, false );
		}

		// Handle WP_Post objects.
		if ( $content instanceof WP_Post ) {
			return new LeadConnector_Custom_Value_String_Replacable( $content, false );
		}

		// Handle arrays (like from ACF fields).
		if ( is_array( $content ) ) {
			return new LeadConnector_Custom_Value_String_Replacable( $content, false );
		}

		// Handle objects that implement __toString().
		if ( is_object( $content ) && method_exists( $content, '__toString' ) ) {
			return new LeadConnector_Custom_Value_String_Replacable( $content, false );
		}

		// Handle scalar values (string, int, float, bool).
		if ( is_scalar( $content ) ) {
			return new LeadConnector_Custom_Value_String_Replacable( $content, true );
		}

		// Return empty string for unsupported types.
		return new LeadConnector_Custom_Value_String_Replacable( '', false );
	}

	/**
	 * Output context: the substituted value will be rendered as HTML markup
	 * (e.g. the_content, widget_text, render_block, comment_text).
	 */
	private const CUSTOM_VALUE_CONTEXT_HTML = 'html';

	/**
	 * Output context: the substituted value will be rendered as plain text
	 * (e.g. the_title, wp_title, document_title_parts, meta_description).
	 */
	private const CUSTOM_VALUE_CONTEXT_TEXT = 'text';

	/**
	 * Output context: the placeholder sits inside an HTML attribute value
	 * (e.g. `alt="{{custom_values.x}}"`). Resolved dynamically per
	 * placeholder by custom_value_attribute_context(); never passed in by a
	 * caller. Escaped with esc_attr() so a value containing a quote cannot
	 * terminate the attribute and start a new one.
	 */
	private const CUSTOM_VALUE_CONTEXT_ATTR = 'attr';

	/**
	 * Output context: the placeholder sits inside a URI-bearing HTML
	 * attribute (`href`, `src`, ...). Escaped with esc_url() so that
	 * `javascript:` / `data:` / `vbscript:` values cannot become an
	 * executable URI.
	 */
	private const CUSTOM_VALUE_CONTEXT_URI = 'uri';

	/**
	 * HTML attributes whose value is a URI and therefore must be escaped
	 * with esc_url() rather than esc_attr().
	 *
	 * @var string[]
	 */
	private const CUSTOM_VALUE_URI_ATTRIBUTES = array(
		'href',
		'src',
		'action',
		'formaction',
		'poster',
		'cite',
		'background',
		'longdesc',
		'usemap',
		'xlink:href',
	);

	/**
	 * Replace {{custom_values.*}} placeholders for HTML output contexts.
	 *
	 * Each substituted value is individually escaped before being spliced
	 * back into the content, so it is safe to render inside HTML markup
	 * (e.g. the_content, widget_text). Values that contain HTML markup are
	 * passed through wp_kses() with the "post" allowed-tag list (filterable
	 * via `leadconnector_custom_value_allowed_html`); values that do not
	 * contain markup are escaped with esc_html() as before. The surrounding
	 * content is left untouched — this method is intended for filters whose
	 * return value is rendered as HTML.
	 *
	 * @param mixed $content The content to process.
	 * @return mixed Content with placeholders replaced.
	 */
	public function replace_custom_value_placeholders( $content ) {
		return $this->replace_custom_value_placeholders_internal( $content, self::CUSTOM_VALUE_CONTEXT_HTML );
	}

	/**
	 * Replace custom value placeholders in title / plain-text filter callbacks.
	 *
	 * Each substituted custom value is stripped of any HTML tags and then
	 * escaped with esc_html() so titles and meta descriptions render as
	 * clean plain text even when a customer has stored HTML markup in the
	 * value. The surrounding content is left untouched, which prevents
	 * double-encoding of already-safe markup and avoids breaking third-party
	 * plugins (e.g. WPML) that legitimately inject HTML into title filters.
	 *
	 * @param mixed $content The content to process.
	 * @return mixed Content with placeholders replaced (values escaped).
	 */
	public function replace_custom_value_placeholders_in_text( $content ) {
		return $this->replace_custom_value_placeholders_internal( $content, self::CUSTOM_VALUE_CONTEXT_TEXT );
	}

	/**
	 * Shared placeholder substitution used by both the HTML- and text-context
	 * public wrappers.
	 *
	 * @param mixed  $content The content to process.
	 * @param string $context Output context for the substituted values. One
	 *                        of self::CUSTOM_VALUE_CONTEXT_HTML (rendered as
	 *                        HTML — HTML markup allowed via wp_kses) or
	 *                        self::CUSTOM_VALUE_CONTEXT_TEXT (rendered as
	 *                        plain text — HTML markup stripped).
	 * @return mixed Content with placeholders replaced and each substituted
	 *               value escaped according to $context.
	 */
	private function replace_custom_value_placeholders_internal( $content, $context ) {
		$sanitized_content = $this->sanitize_content( $content );
		if ( ! $sanitized_content->is_valid() ) {
			return $content;
		}

		$content_string = $sanitized_content->get_content();
		if ( ! is_string( $content_string ) ) {
			return $content;
		}

		if ( false === strpos( $content_string, '{{' ) || false === strpos( $content_string, 'custom_values.' ) ) {
			return $content;
		}

		$custom_values = $this->get_custom_values_instance();
		if ( null === $custom_values ) {
			return $content;
		}

		try {
			$leadconnector_substitution_count = 0;

			return preg_replace_callback(
				'/\{\{\s*custom_values\.(\w+)\s*\}\}/',
				function ( $matches ) use ( $custom_values, $context, $content_string ) {
					// PREG_OFFSET_CAPTURE is enabled below, so each group is
					// array( matched_string, byte_offset ).
					$placeholder = $matches[0][0];
					$offset      = (int) $matches[0][1];
					$field_key   = trim( $matches[1][0] );

					if ( '' === $field_key || ! preg_match( '/^[a-zA-Z_]\w{0,254}$/', $field_key ) ) {
						return $placeholder;
					}

					// LC-CV-01 / LC-CV-02: pick the escaper from where the
					// placeholder actually sits in the markup, not from the
					// shape of the resolved value. An empty resolved context
					// means the placeholder is inside an inline event-handler
					// attribute and must be left verbatim.
					$resolved_context = $context;
					if ( self::CUSTOM_VALUE_CONTEXT_TEXT !== $context ) {
						$resolved_context = $this->custom_value_attribute_context( $content_string, $offset );
						if ( '' === $resolved_context ) {
							return $placeholder;
						}
					}

					$value = $custom_values->get_value( $field_key );

					if ( null === $value ) {
						return $placeholder;
					}

					return $this->escape_custom_value( (string) $value, $resolved_context );
				},
				$content_string,
				-1,
				$leadconnector_substitution_count,
				PREG_OFFSET_CAPTURE
			);
		} catch ( Exception $e ) {
			LeadConnector_Logger::get_instance()->warning(
				'Error in replace_custom_value_placeholders_internal: ' . $e->getMessage()
			);
			return $content;
		}
	}

	/**
	 * Escape a resolved custom value for output.
	 *
	 * Some customers store HTML markup inside custom values in the
	 * LeadConnector CRM (for example a formatted signature or a small
	 * promotional block). Historically we always escaped substitutions with
	 * esc_html(), which caused that markup to render as visible tag text
	 * rather than styled HTML. When the substituted value contains HTML
	 * markup and it is being rendered into an HTML output context, we
	 * instead pass it through wp_kses() with the "post" allowed-tag list so
	 * safe markup renders while script/style/on* attributes and other
	 * unsafe constructs are stripped. Plain-text substitutions and any
	 * placeholder rendered into a plain-text context (titles, meta
	 * descriptions) continue to be treated as text.
	 *
	 * @param string $value   The resolved custom value.
	 * @param string $context One of self::CUSTOM_VALUE_CONTEXT_HTML or
	 *                        self::CUSTOM_VALUE_CONTEXT_TEXT.
	 * @return string Escaped value suitable for output in $context.
	 */
	private function escape_custom_value( $value, $context ) {
		if ( self::CUSTOM_VALUE_CONTEXT_TEXT === $context ) {
			return esc_html( wp_strip_all_tags( $value ) );
		}

		// LC-CV-01: the placeholder sits inside an HTML attribute value.
		// wp_kses() is the wrong escaper here — it sanitizes markup *within*
		// the value but leaves bare `"` characters untouched, because it
		// assumes it is producing a text node. A CRM value such as
		// `<b></b>" onerror="alert(1)` would therefore terminate the
		// attribute and inject a new event-handler attribute. esc_attr()
		// entity-encodes the quotes and closes that breakout.
		if ( self::CUSTOM_VALUE_CONTEXT_ATTR === $context ) {
			return esc_attr( wp_strip_all_tags( $value ) );
		}

		// LC-CV-02: URI-bearing attribute. esc_html() leaves a `javascript:`
		// scheme intact, so route through esc_url(), which drops any scheme
		// outside wp_allowed_protocols().
		if ( self::CUSTOM_VALUE_CONTEXT_URI === $context ) {
			return esc_url( wp_strip_all_tags( $value ) );
		}

		if ( ! $this->custom_value_contains_html( $value ) ) {
			return esc_html( $value );
		}

		/**
		 * Filters the allowed HTML tags used when substituting a custom value
		 * that contains HTML markup into an HTML output context.
		 *
		 * Defaults to a restrictive subset of post-allowed tags that excludes
		 * interactive, embedding, and form elements. This is intentionally
		 * tighter than wp_kses_post() because custom values originate from an
		 * external API (the LeadConnector CRM) — a different trust boundary
		 * than locally-authored post_content.
		 *
		 * @since 4.0.3
		 *
		 * @param array  $allowed_html Allowed HTML tags and attributes.
		 * @param string $value        The raw custom-value string being processed.
		 */
		$allowed_html = apply_filters(
			'leadconnector_custom_value_allowed_html',
			$this->get_custom_value_allowed_html(),
			$value
		);

		if ( ! is_array( $allowed_html ) ) {
			$allowed_html = $this->get_custom_value_allowed_html();
		}

		return wp_kses( $value, $allowed_html );
	}

	/**
	 * Resolve the HTML output context of a single placeholder occurrence.
	 *
	 * Substitution used to pick an escaper from the *shape of the value*
	 * (does it contain markup?) rather than from the *position of the
	 * placeholder*. That is unsound: the same value is safe in a text node
	 * and unsafe inside an attribute. This walks backwards from the
	 * placeholder offset to decide which HTML context it actually lands in.
	 *
	 * A placeholder is inside a tag when the nearest preceding `<` is not
	 * followed by a `>`. In that case the attribute name immediately before
	 * the placeholder decides between a URI escaper and a generic attribute
	 * escaper.
	 *
	 * @since 4.0.6
	 * @param string $content Full subject string being filtered.
	 * @param int    $offset  Byte offset of the placeholder within $content.
	 * @return string One of the self::CUSTOM_VALUE_CONTEXT_* constants, or an
	 *                empty string when the placeholder must not be substituted
	 *                at all (inline event-handler attribute).
	 */
	private function custom_value_attribute_context( $content, $offset ) {
		$before = substr( $content, 0, $offset );

		$last_lt = strrpos( $before, '<' );
		if ( false === $last_lt ) {
			return self::CUSTOM_VALUE_CONTEXT_HTML;
		}

		$last_gt = strrpos( $before, '>' );
		if ( false !== $last_gt && $last_gt > $last_lt ) {
			// The most recent tag was already closed — we are in a text node.
			return self::CUSTOM_VALUE_CONTEXT_HTML;
		}

		$fragment = substr( $before, $last_lt );

		if ( preg_match(
			'/([a-zA-Z_:][a-zA-Z0-9_.:-]*)\s*=\s*("[^"]*|\'[^\']*|[^\s"\'=<>`]*)$/',
			$fragment,
			$attr_matches
		) ) {
			$attribute = strtolower( $attr_matches[1] );

			// Never substitute into an inline event handler: the value would
			// land directly in a JavaScript execution context, where neither
			// esc_attr() nor esc_url() is a sufficient escaper.
			if ( 0 === strpos( $attribute, 'on' ) ) {
				return '';
			}

			if ( in_array( $attribute, self::CUSTOM_VALUE_URI_ATTRIBUTES, true ) ) {
				return self::CUSTOM_VALUE_CONTEXT_URI;
			}
		}

		return self::CUSTOM_VALUE_CONTEXT_ATTR;
	}

	/**
	 * Restrictive HTML tag allowlist for custom value substitutions.
	 *
	 * Uses an explicit positive allowlist of safe inline formatting and
	 * structural tags. Custom values originate from an external API (the
	 * LeadConnector CRM) — a different trust boundary than locally-authored
	 * post_content — so the allowlist is intentionally minimal: only tags
	 * needed for rich text formatting are permitted.
	 *
	 * @return array Allowed HTML tags and attributes for wp_kses().
	 */
	private function get_custom_value_allowed_html() {
		static $allowed = null;

		if ( null !== $allowed ) {
			return $allowed;
		}

		$allowed = array(
			'a'          => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
				'class'  => true,
			),
			'br'         => array(),
			'em'         => array( 'class' => true ),
			'strong'     => array( 'class' => true ),
			'b'          => array( 'class' => true ),
			'i'          => array( 'class' => true ),
			'u'          => array( 'class' => true ),
			'span'       => array(
				'class' => true,
				'style' => true,
			),
			'p'          => array(
				'class' => true,
				'style' => true,
			),
			'div'        => array(
				'class' => true,
				'style' => true,
			),
			'ul'         => array( 'class' => true ),
			'ol'         => array( 'class' => true ),
			'li'         => array( 'class' => true ),
			'h1'         => array( 'class' => true ),
			'h2'         => array( 'class' => true ),
			'h3'         => array( 'class' => true ),
			'h4'         => array( 'class' => true ),
			'h5'         => array( 'class' => true ),
			'h6'         => array( 'class' => true ),
			'img'        => array(
				'src'    => true,
				'alt'    => true,
				'width'  => true,
				'height' => true,
				'class'  => true,
			),
			'table'      => array( 'class' => true ),
			'thead'      => array(),
			'tbody'      => array(),
			'tr'         => array( 'class' => true ),
			'th'         => array( 'class' => true ),
			'td'         => array( 'class' => true ),
			'blockquote' => array( 'class' => true ),
			'hr'         => array(),
			'sup'        => array(),
			'sub'        => array(),
			'small'      => array(),
			'abbr'       => array( 'title' => true ),
		);

		return $allowed;
	}

	/**
	 * Whether a resolved custom value contains HTML markup.
	 *
	 * Uses a cheap character-presence pre-check before falling back to
	 * wp_strip_all_tags() so the common case (plain-text values) short
	 * circuits without allocating. wp_strip_all_tags() only removes strings
	 * that match its HTML tag pattern, so stray angle brackets in prose
	 * (for example "5 < 10") are correctly reported as non-HTML.
	 *
	 * @param string $value The resolved custom value.
	 * @return bool True when the value contains at least one HTML tag.
	 */
	private function custom_value_contains_html( $value ) {
		if ( false === strpos( $value, '<' ) || false === strpos( $value, '>' ) ) {
			return false;
		}

		return wp_strip_all_tags( $value ) !== $value;
	}

	/**
	 * Lazy, per-request shared LeadConnector_CustomValues instance (#C5).
	 *
	 * The 15+ placeholder filters registered in the constructor previously
	 * called `require_once` and `new LeadConnector_CustomValues()` on every
	 * filter pass, which adds dozens of redundant object constructions per
	 * request on a typical archive page. This helper memoizes the instance
	 * for the lifetime of the request and returns null only when the
	 * underlying class file cannot be loaded.
	 *
	 * @return LeadConnector_CustomValues|null
	 */
	private function get_custom_values_instance() {
		static $cached_instance = null;
		static $resolved        = false;

		if ( $resolved ) {
			return $cached_instance;
		}

		require_once plugin_dir_path( __DIR__ ) . 'includes/CustomValues/class-leadconnector-customvalues.php';
		if ( class_exists( 'LeadConnector_CustomValues' ) ) {
			$cached_instance = new LeadConnector_CustomValues();
		}

		$resolved = true;
		return $cached_instance;
	}

	/**
	 * Replace custom value placeholders in document title parts
	 *
	 * @param array $title_parts The title parts array.
	 * @return array The processed title parts.
	 */
	public function replace_custom_value_in_title_parts( $title_parts ) {
		if ( ! is_array( $title_parts ) ) {
			return $title_parts;
		}

		foreach ( $title_parts as $key => $part ) {
			if ( is_string( $part ) ) {
				$title_parts[ $key ] = $this->replace_custom_value_placeholders_in_text( $part );
			}
		}
		return $title_parts; // This is escaped and data type is of type array.
	}

	/**
	 * Replace custom value placeholders in block content.
	 *
	 * Runs for every rendered block via the `render_block` filter. This is
	 * required because block themes / Full Site Editing render the home and
	 * other front-end templates block-by-block — `core/post-content` calls
	 * `do_blocks( get_the_content() )` directly and never triggers the
	 * `the_content` filter, so a placeholder typed into a Paragraph or
	 * Heading block on the home page would otherwise never be substituted.
	 *
	 * Navigation blocks (`core/navigation*`, `core/page-list`) are excluded
	 * because placeholder substitution in menu markup breaks theme navigation.
	 *
	 * Performance: a cheap substring check skips the regex pass entirely for
	 * blocks that do not contain a placeholder, which is the vast majority of
	 * blocks on a typical page.
	 *
	 * @param string $block_content The block content about to be rendered.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string
	 */
	public function replace_custom_value_in_blocks( $block_content, $block ) {
		if ( $this->leadconnector_is_navigation_block( $block ) ) {
			return $block_content;
		}

		if ( ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}

		if ( false === strpos( $block_content, 'custom_values.' ) ) {
			return $block_content;
		}

		return $this->replace_custom_value_placeholders_internal_for_blocks( $block_content );
	}

	/**
	 * Block-specific placeholder substitution that neutralizes shortcode
	 * brackets in every substituted value.
	 *
	 * Unlike the generic replace_custom_value_placeholders_internal(), this
	 * variant escapes `[` and `]` in resolved values before splicing them
	 * into the block output. This prevents shortcode injection regardless of
	 * whether the original block content already contained square brackets.
	 *
	 * @param string $content Block HTML content containing placeholders.
	 * @return string Content with placeholders replaced and brackets neutralized.
	 */
	private function replace_custom_value_placeholders_internal_for_blocks( $content ) {
		$sanitized_content = $this->sanitize_content( $content );
		if ( ! $sanitized_content->is_valid() ) {
			return $content;
		}

		$content_string = $sanitized_content->get_content();
		if ( ! is_string( $content_string ) ) {
			return $content;
		}

		if ( false === strpos( $content_string, '{{' ) || false === strpos( $content_string, 'custom_values.' ) ) {
			return $content;
		}

		$custom_values = $this->get_custom_values_instance();
		if ( null === $custom_values ) {
			return $content;
		}

		try {
			$leadconnector_substitution_count = 0;

			return preg_replace_callback(
				'/\{\{\s*custom_values\.(\w+)\s*\}\}/',
				function ( $matches ) use ( $custom_values, $content_string ) {
					// PREG_OFFSET_CAPTURE is enabled below, so each group is
					// array( matched_string, byte_offset ).
					$placeholder = $matches[0][0];
					$offset      = (int) $matches[0][1];
					$field_key   = trim( $matches[1][0] );

					if ( '' === $field_key || ! preg_match( '/^[a-zA-Z_]\w{0,254}$/', $field_key ) ) {
						return $placeholder;
					}

					// LC-CV-01 / LC-CV-02: same context resolution as the
					// non-block path, so both paths escape identically.
					$resolved_context = $this->custom_value_attribute_context( $content_string, $offset );
					if ( '' === $resolved_context ) {
						return $placeholder;
					}

					$value = $custom_values->get_value( $field_key );

					if ( null === $value ) {
						return $placeholder;
					}

					$escaped = $this->escape_custom_value( (string) $value, $resolved_context );

					return str_replace(
						array( '[', ']' ),
						array( '&#91;', '&#93;' ),
						$escaped
					);
				},
				$content_string,
				-1,
				$leadconnector_substitution_count,
				PREG_OFFSET_CAPTURE
			);
		} catch ( Exception $e ) {
			LeadConnector_Logger::get_instance()->warning(
				'Error in replace_custom_value_placeholders_internal_for_blocks: ' . $e->getMessage()
			);
			return $content;
		}
	}

	/**
	 * Whether a rendered block belongs to WordPress navigation UI.
	 *
	 * Custom-value substitution is skipped for these blocks because themes
	 * (e.g. Astra) and the block editor pass structured markup or non-scalar
	 * titles through navigation filters; rewriting that output breaks menus.
	 *
	 * @param array $block Full block payload from the render_block filter.
	 * @return bool
	 */
	private function leadconnector_is_navigation_block( $block ) {
		if ( ! is_array( $block ) || empty( $block['blockName'] ) || ! is_string( $block['blockName'] ) ) {
			return false;
		}

		$block_name = $block['blockName'];

		if ( 0 === strpos( $block_name, 'core/navigation' ) ) {
			return true;
		}

		return in_array( $block_name, array( 'core/page-list' ), true );
	}

	/**
	 * Register and enqueue the public-facing JavaScript.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in LeadConnector_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The LeadConnector_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		$options             = get_option( LEAD_CONNECTOR_OPTION_NAME );
		$heading             = '';
		$sub_heading         = '';
		$enabled_text_widget = 0;
		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] ) ) {
			$enabled_text_widget = esc_attr( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_ENABLE_TEXT_WIDGET ] );
		}

		$text_widget_error = '';
		if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] ) ) {
			$text_widget_error = esc_attr( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_ERROR ] );
		}
		if ( get_query_var( 'elementor_highlight' ) === 'true' ) {
			wp_enqueue_script(
				'leadconnector-elementor-highlight',
				plugin_dir_url( __FILE__ ) . 'js/leadconnector-elementor-highlight.js',
				array(),
				$this->version,
				true // load in footer — DOM must be ready for element queries.
			);

			$ai_page_version = 1;
			$post_id         = get_queried_object_id();
			if ( $post_id ) {
				$raw_version = get_post_meta(
					$post_id,
					\lead_connector_constants\LEADCONNECTOR_AI_PAGE_VERSION_META_KEY,
					true
				);
				if ( is_numeric( $raw_version ) ) {
					$ai_page_version = (int) $raw_version;
				}
			}

			wp_localize_script(
				'leadconnector-elementor-highlight',
				'LeadConnectorAIPage',
				array(
					'version' => $ai_page_version,
				)
			);
		}

		if ( 1 === (int) $enabled_text_widget && isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ] ) ) {
			$location_id = $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_LOCATION_ID ];

			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_HEADING ] ) ) {
				$heading = esc_attr( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_HEADING ] );
			}
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SUB_HEADING ] ) ) {
				$sub_heading = esc_attr( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SUB_HEADING ] );
			}

			$use_email_field      = '0';
			$chat_widget_settings = null;
			$using_old_widget     = true;
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SETTINGS ] ) ) {
				$chat_widget_settings = json_decode( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_SETTINGS ] );
				if ( ! isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] ) ) {
					$widget_id = leadconnector_api_prop( $chat_widget_settings, 'widgetId' );
					if ( $chat_widget_settings && null !== $widget_id ) {
						$options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] = $widget_id;
						update_option( LEAD_CONNECTOR_OPTION_NAME, $options );
					}
				}
			}
			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_USE_EMAIL_FILED ] ) ) {
				$use_email_field = esc_attr( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_TEXT_WIDGET_USE_EMAIL_FILED ] );
			}

			if ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] ) ) {
				$using_old_widget = false;
			}

			if ( $using_old_widget ) {
				wp_enqueue_script( 'leadconnector-text-widget', LEAD_CONNECTOR_CDN_BASE_URL . 'loader.js', array(), $this->version, false );
				wp_enqueue_script( 'leadconnector-public', plugin_dir_url( __FILE__ ) . 'js/leadconnector-public.js', array( 'jquery' ), $this->version, false );

				$safe_widget_settings = array();
				if ( is_object( $chat_widget_settings ) || is_array( $chat_widget_settings ) ) {
					$settings_array = (array) $chat_widget_settings;
					foreach ( $settings_array as $key => $value ) {
						if ( is_scalar( $value ) ) {
							$safe_widget_settings[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
						}
					}
				}

				wp_localize_script(
					'leadconnector-public',
					'leadconnector_public_js',
					array(
						'text_widget_location_id'     => sanitize_text_field( $location_id ),
						'text_widget_heading'         => $heading,
						'text_widget_sub_heading'     => $sub_heading,
						'text_widget_error'           => $text_widget_error,
						'text_widget_use_email_field' => $use_email_field,
						'text_widget_settings'        => $safe_widget_settings,
						'text_widget_cdn_base_url'    => esc_url_raw( LEAD_CONNECTOR_CDN_BASE_URL ),
					)
				);
			} elseif ( isset( $options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ] ) ) {

				$widget_id = sanitize_text_field(
					$options[ lead_connector_constants\LEADCONNECTOR_OPTIONS_SELECTED_CHAT_WIDGET_ID ]
				);

				$widget_src      = defined( 'LEADCONNECTOR_CHAT_WIDGET_SRC' ) ? esc_url_raw( LEADCONNECTOR_CHAT_WIDGET_SRC ) : '';
				$resources_url   = defined( 'LEADCONNECTOR_CHAT_WIDGET_RESOURCES_URL' ) ? esc_url_raw( LEADCONNECTOR_CHAT_WIDGET_RESOURCES_URL ) : '';
				$server_url      = defined( 'LEADCONNECTOR_CHAT_WIDGET_SERVER_URL' ) ? esc_url_raw( LEADCONNECTOR_CHAT_WIDGET_SERVER_URL ) : '';
				$marketplace_url = defined( 'LEADCONNECTOR_CHAT_WIDGET_MARKETPLACE_URL' ) ? esc_url_raw( LEADCONNECTOR_CHAT_WIDGET_MARKETPLACE_URL ) : '';

				wp_enqueue_script(
					'leadconnector-chat-widget',
					$widget_src,
					array(),
					$this->version,
					true
				);

				// Inject the required data-* attributes via script_loader_tag filter.
				add_filter(
					'script_loader_tag',
					function ( $tag, $handle ) use ( $widget_id, $resources_url, $server_url, $marketplace_url ) {
						if ( 'leadconnector-chat-widget' !== $handle ) {
							return $tag;
						}
						// Replace only the first <script occurrence to avoid double-replacement.
						return preg_replace(
							'/(<script\b)/i',
							'$1'
							. ' data-resources-url="' . esc_attr( $resources_url ) . '"'
							. ' data-widget-id="' . esc_attr( $widget_id ) . '"'
							. ' data-server-u-r-l="' . esc_attr( $server_url ) . '"'
							. ' data-marketplace-u-r-l="' . esc_attr( $marketplace_url ) . '"'
							. ' data-no-optimize="1" data-no-minify="1"',
							$tag,
							1
						);
					},
					10,
					2
				);
			}
		}
	}

	/**
	 * Inject Elementor highlight styles/scripts (stub — assets now enqueued).
	 *
	 * @since 1.0.0
	 */
	public function inject_elementor_highlight_styles() {
		/*
		 * Assets are now delivered by enqueue_styles() / enqueue_scripts() via
		 * wp_enqueue_style( 'leadconnector-elementor-highlight' ) and
		 * wp_enqueue_script( 'leadconnector-elementor-highlight' ).
		 * Nothing to output here.
		 */
	}
}
