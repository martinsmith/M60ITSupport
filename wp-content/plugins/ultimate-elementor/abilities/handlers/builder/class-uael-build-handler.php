<?php
/**
 * Build Handler.
 *
 * Builds complete Elementor layouts from a declarative JSON structure.
 * Unified handler replacing template-builder/build-template and page-builder/build-page.
 * Works on any Elementor-enabled post (pages, posts, templates).
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Build_Handler
 *
 * Implements UAEL_Ability_Handler for the builder/build ability.
 *
 * @since 1.45.0
 */
class UAEL_Build_Handler implements UAEL_Ability_Handler {

	use UAEL_Abilities_Helpers;

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'builder-build';
	}

	/**
	 * Get the wp_register_ability() args array.
	 *
	 * @since 1.45.0
	 *
	 * @return array Ability registration args.
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Build Complete Layout', 'uael' ),
			'description'         => __( 'Builds a complete layout with sections, columns, and widgets in one call. Replaces all content on the post. The structure parameter is a JSON string describing rows and widgets.', 'uael' ),
			'category'            => 'uael-builder',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id', 'structure' ),
				'properties' => array(
					'post_id'   => array(
						'type'        => 'integer',
						'description' => __( 'Any Elementor-enabled post ID (page, post, or template).', 'uael' ),
					),
					'structure' => array(
						'type'        => 'string',
						'description' => self::get_structure_prompt(),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'layout_mode'   => array( 'type' => 'string' ),
					'element_count' => array( 'type' => 'integer' ),
					'structure'     => array( 'type' => 'array' ),
					'message'       => array( 'type' => 'string' ),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => false,
					'destructive'  => true,
					'idempotent'   => true,
					'instructions' => self::get_build_instructions(),
				),
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @since 1.45.0
	 *
	 * @param array $input Validated input parameters.
	 * @return array|WP_Error Result data or error.
	 */
	public function execute( $input ) {
		$allowed = $this->check_modifications_allowed();

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		// Accept both post_id and template_id (Angie's AI sometimes uses the old name).
		$post_id = $input['post_id'] ?? $input['template_id'] ?? $input['id'] ?? 0;
		$loaded  = $this->load_post( $post_id );

		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		$structure = $input['structure'] ?? array();

		// Accept structure as a JSON string (WS Form pattern for Angie compatibility).
		if ( is_string( $structure ) ) {
			$decoded = json_decode( $structure, true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$structure = $decoded;
			} else {
				return new \WP_Error(
					'uael_invalid_json',
					__( 'structure is not valid JSON. It must be a minified JSON array string.', 'uael' ),
					array( 'status' => 400 )
				);
			}
		}

		if ( empty( $structure ) || ! is_array( $structure ) ) {
			return new \WP_Error(
				'uael_empty_structure',
				__( 'structure must be a JSON array of section objects. Example: [{"columns":[20,60,20],"children":[{"type":"widget","widget_type":"site-logo","settings":{}}]}]', 'uael' ),
				array( 'status' => 400 )
			);
		}

		// Validate structure items are objects, not plain numbers.
		foreach ( $structure as $idx => $item ) {
			if ( ! is_array( $item ) ) {
				return new \WP_Error(
					'uael_invalid_structure',
					__( 'Each item in structure must be a section object with "children". Example: {"columns":[20,60,20],"children":[{"type":"widget","widget_type":"site-logo","settings":{}}]}', 'uael' ),
					array( 'status' => 400 )
				);
			}
		}

		$elements      = array();
		$element_count = 0;

		foreach ( $structure as $section_def ) {
			$result = UAEL_Element_Helpers::build_section_from_definition( $section_def );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$elements[]     = $result['element'];
			$element_count += $result['count'];
		}

		$saved = UAEL_Element_Helpers::save_elementor_data( $loaded['post']->ID, $elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'success'       => true,
			'layout_mode'   => UAEL_Element_Helpers::is_container_active() ? 'container' : 'section',
			'element_count' => $element_count,
			'structure'     => UAEL_Element_Helpers::simplify_tree( $elements ),
			'message'       => sprintf(
				/* translators: 1: Number of elements, 2: Post title */
				__( 'Built "%2$s" with %1$d elements.', 'uael' ),
				$element_count,
				$loaded['post']->post_title
			),
		);
	}

	/**
	 * Validate and load an Elementor-enabled post.
	 *
	 * @since 1.45.0
	 *
	 * @param int $post_id Post ID.
	 * @return array|WP_Error Array with 'post' and 'elements', or error.
	 */
	private function load_post( $post_id ) {
		$post = $this->validate_elementor_post( absint( $post_id ) );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$elements = UAEL_Element_Helpers::parse_elementor_data( $post->ID );

		if ( is_wp_error( $elements ) ) {
			return $elements;
		}

		return array(
			'post'     => $post,
			'elements' => $elements,
		);
	}

	/**
	 * Get the structure parameter prompt (WS Form pattern).
	 *
	 * This goes in the input_schema description for the structure field.
	 * Angie reads this to construct the JSON string.
	 *
	 * @return string Prompt text.
	 */
	private static function get_structure_prompt() {
		// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found,WordPressVIPMinimum.Security.Mustache.OutputNotation
		return 'A minified JSON string describing the layout. '
			. "\n\n"
			. '= Example JSON =' . "\n"
			. 'A hero section with a full-width Info Box (heading, description):' . "\n"
			. '[{"columns":[100],"settings":{"padding":{"unit":"px","top":"60","right":"20","bottom":"60","left":"20"}},"children":[{"type":"widget","widget_type":"uael-infobox","settings":{"heading":"Welcome to our site","description":"A short, punchy hero description."}}]}]'
			. "\n\n"
			. 'A pricing section with three Price Box widgets:' . "\n"
			. '[{"columns":[33,34,33],"children":[{"type":"widget","widget_type":"uael-price-table","settings":{}},{"type":"widget","widget_type":"uael-price-table","settings":{}},{"type":"widget","widget_type":"uael-price-table","settings":{}}]}]'
			. "\n\n"
			. '= Format =' . "\n"
			. 'The JSON is an array of section objects. Each section:' . "\n"
			. '  columns (array of integers): Width percentages summing to 100. e.g. [20,60,20] for 3 columns.' . "\n"
			. '  children (array): Widgets distributed across columns in order. 1st child → 1st column, 2nd → 2nd, etc.' . "\n"
			. '  settings (object, optional): Section styling.' . "\n"
			. '  column_settings (array, optional): Per-column styling, one object per column.' . "\n"
			. "\n"
			. 'Each child widget:' . "\n"
			. '  {"type":"widget","widget_type":"<slug>","settings":{...}}' . "\n"
			. "\n"
			. '= Available Widget Slugs =' . "\n"
			. 'Core: heading, text-editor, image, button, spacer, divider, icon-list' . "\n"
			. 'UAE Pro: uael-infobox (Info Box), uael-price-table (Price Box), uael-price-list, uael-advanced-heading, uael-fancy-heading, uael-posts, uael-business-reviews, uael-table, uael-buttons (Multi Buttons)' . "\n"
			. 'Call list-widget-types for the full set and get-schema for each widget\'s settings.' . "\n"
			. "\n"
			. '= Common Widget Settings =' . "\n"
			. 'uael-infobox: heading (string), description (string). Call get-schema for icon, button, and style keys.' . "\n"
			. 'heading: title (string), header_size ("h1"-"h6"), align ("left"|"center"|"right"), title_color (hex)' . "\n"
			. 'button: text (string), link ({"url":"#"}), size ("xs"|"sm"|"md"|"lg"|"xl"), button_text_color (hex), background_color (hex)' . "\n"
			. 'image: image ({"url":"...","id":0}), image_size (string)' . "\n"
			. 'text-editor: editor (HTML string)' . "\n"
			. 'Other UAE Pro widgets: call get-schema with type=widget for the exact setting keys.' . "\n"
			. "\n"
			. '= Section Settings =' . "\n"
			. 'background_color (hex), padding ({"unit":"px","top":"15","right":"30","bottom":"15","left":"30"})' . "\n"
			. "\n"
			. '= Column Settings =' . "\n"
			. 'content_position ("top"|"center"|"bottom"), align ("start"|"center"|"end"), background_color (hex), padding (same format)' . "\n"
			. "\n"
			. '= Rules =' . "\n"
			. '1. Minify the JSON. No newlines or indentation.' . "\n"
			. '2. columns must sum to 100.' . "\n"
			. '3. Number of children should match number of columns (extras go in last column).' . "\n"
			. '4. Use {} for empty settings, not [].' . "\n"
			. '5. Only output the JSON string, nothing else.' . "\n"
			. '6. The system auto-detects section vs container mode. Do not worry about this.';
		// phpcs:enable Generic.Strings.UnnecessaryStringConcat.Found
	}

	/**
	 * Get comprehensive instructions for the build ability.
	 *
	 * Used by MCP Adapter (Claude Desktop/Code) which reads instructions metadata.
	 *
	 * @since 1.45.0
	 *
	 * @return string Instructions text.
	 */
	private static function get_build_instructions() {
		// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found,WordPressVIPMinimum.Security.Mustache.OutputNotation
		return 'PREFERRED: Always use this to create or rebuild layouts for any Elementor post (pages, posts, templates). '
			. 'Do NOT use individual insert/add-section/add-column calls to build step by step. '
			. 'One call replaces all content.'
			. "\n\n"
			. 'WORKFLOW (follow this order): '
			. '1) Call design-system/get-tokens to get the site\'s color palette, fonts, and spacing -- use these instead of hardcoded colors. '
			. '2) Call builder/list-widget-types to see available widgets. '
			. '3) Call builder/get-schema with type=widget for each widget you will style (returns content + style settings). '
			. '4) Call builder/get-schema with type=section or type=container to discover layout settings. '
			. '5) Call builder/build with your complete structure as a JSON string, using colors and fonts from the design tokens. '
			. '6) ALWAYS call builder/regenerate-css afterward so the frontend renders correctly. '
			. '7) Optionally call builder/get-structure with full=true to verify the result.'
			. "\n\n"
			. 'TOGGLE DEPENDENCIES (auto-handled -- just provide the value): '
			. 'background_color auto-sets background_background:"classic". '
			. 'border_width auto-sets border_border:"solid". '
			. 'Font keys (e.g., *_font_family, *_font_size) auto-set *_typography:"custom". '
			. 'You do NOT need to set these toggles manually.'
			. "\n\n"
			. 'EXAMPLE -- Hero section (full-width Info Box with heading and description): '
			. '[{"type":"section","columns":[100],'
			. '"settings":{"padding":{"unit":"px","top":"60","right":"20","bottom":"60","left":"20"}},'
			. '"children":['
			. '{"type":"widget","widget_type":"uael-infobox","settings":{"heading":"Welcome to our site","description":"A short, punchy hero description."}}'
			. ']}]';
		// phpcs:enable Generic.Strings.UnnecessaryStringConcat.Found
	}
}
