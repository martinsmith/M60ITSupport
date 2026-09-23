<?php
/**
 * UAEL Abilities Bootstrap.
 *
 * Detects whether HFE is active and routes to the correct mode:
 * - Scenario A (standalone): Own registry, 36 handlers, own Angie, own MCP server.
 * - Scenario B (both active): Hook 12 Pro-specific handlers into HFE's registry.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only proceed if Abilities API exists (WP 6.9+).
if ( ! function_exists( 'wp_register_ability' ) ) {
	return;
}

/**
 * Class UAEL_Abilities_Bootstrap
 *
 * @since 1.45.0
 */
class UAEL_Abilities_Bootstrap {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Whether HFE is active and has the handler-based registry.
	 *
	 * @var bool
	 */
	private $hfe_active = false;

	/**
	 * Registry instance (standalone mode only).
	 *
	 * @var UAEL_Ability_Registry|null
	 */
	private $registry = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the registry instance.
	 *
	 * @return UAEL_Ability_Registry|null
	 */
	public function get_registry() {
		return $this->registry;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->hfe_active = defined( 'HFE_VER' ) && class_exists( 'HFE_Abilities_Loader' );

		// Load the handler interface (needed by both modes).
		require_once UAEL_DIR . 'abilities/contracts/interface-uael-ability-handler.php';

		if ( $this->hfe_active ) {
			$this->init_dual_mode();
		} else {
			$this->init_standalone_mode();
		}
	}

	/**
	 * Scenario B: Both Pro + HFE active.
	 *
	 * Hook 12 Pro-specific handlers into HFE's registry via the
	 * uae_mcp_register_abilities action.
	 *
	 * @return void
	 */
	private function init_dual_mode() {
		// Register Pro-specific categories before abilities are discovered.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_pro_categories' ) );
		add_action( 'uae_mcp_register_abilities', array( $this, 'register_pro_handlers_into_hfe' ) );
	}

	/**
	 * Register Pro-specific ability categories.
	 *
	 * Needed in both modes — standalone registers all categories,
	 * dual mode only registers the Pro-specific ones.
	 *
	 * @return void
	 */
	public function register_pro_categories() {
		$categories = array(
			'uael-skins'    => array(
				'label'       => __( 'UAE Post Skins', 'uael' ),
				'description' => __( 'Manage post display skins.', 'uael' ),
			),
			'uael-settings' => array(
				'label'       => __( 'UAE Plugin Settings', 'uael' ),
				'description' => __( 'Manage integration API keys and branding settings.', 'uael' ),
			),
			'uael-info'     => array(
				'label'       => __( 'UAE Information', 'uael' ),
				'description' => __( 'Read-only plugin info: version, widgets, categories.', 'uael' ),
			),
		);

		foreach ( $categories as $slug => $args ) {
			wp_register_ability_category( $slug, $args );
		}
	}

	/**
	 * Scenario A: Pro standalone (no HFE).
	 *
	 * Own registry, all handlers, Angie bridge, MCP server.
	 *
	 * @return void
	 */
	private function init_standalone_mode() {
		// Framework + registry are loaded unconditionally so the settings UI
		// can introspect the ability catalogue (via the REST endpoint) even
		// when AI tools are turned off.
		$this->load_framework();

		$this->registry = new UAEL_Ability_Registry();

		// Master switch — opt-in, off by default (matches UAE Lite). Side
		// effects (registering abilities, MCP server, Angie) only attach when
		// the user enables them.
		$settings         = get_option( 'uae_mcp_settings', null );
		$enable_abilities = is_array( $settings ) ? ! empty( $settings['enable_abilities'] ) : false;

		if ( ! $enable_abilities ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );

		// Dedicated MCP server — only when the user explicitly opts in.
		if ( ! empty( $settings['dedicated_server'] ) ) {
			add_action( 'mcp_adapter_init', array( $this, 'register_mcp_server' ), 20 );
		}

		// Angie bridge — only when the user explicitly opts in.
		if ( ! empty( $settings['angie_enabled'] ) ) {
			$this->init_angie();
		}
	}

	/**
	 * Load framework files for standalone mode.
	 *
	 * @return void
	 */
	private function load_framework() {
		$base = UAEL_DIR . 'abilities/';

		require_once $base . 'registry/class-uael-ability-registry.php';
		require_once $base . 'trait-uael-abilities-helpers.php';
		require_once $base . 'class-uael-element-helpers.php';
	}

	/**
	 * Register all ability categories (standalone mode).
	 *
	 * @return void
	 */
	public function register_categories() {
		$branding    = \UltimateElementor\Classes\UAEL_Helper::get_white_labels();
		$plugin_name = ! empty( $branding['plugin']['name'] ) ? $branding['plugin']['name'] : __( 'Ultimate Addons for Elementor', 'uael' );

		$categories = array(
			'uael-widgets'     => array(
				'label'       => __( 'UAE Widget Management', 'uael' ),
				'description' => __( 'List, activate, and deactivate Elementor widgets.', 'uael' ),
			),
			'uael-skins'       => array(
				'label'       => __( 'UAE Post Skins', 'uael' ),
				'description' => __( 'Manage post display skins.', 'uael' ),
			),
			'uael-builder'     => array(
				'label'       => __( 'UAE Page Builder', 'uael' ),
				'description' => __( 'Build and manage page content with Elementor widgets.', 'uael' ),
			),
			'uael-pages'       => array(
				'label'       => __( 'UAE Page Management', 'uael' ),
				'description' => __( 'Create, update, and manage WordPress pages.', 'uael' ),
			),
			'uael-settings'    => array(
				'label'       => __( 'UAE Plugin Settings', 'uael' ),
				'description' => __( 'Manage integration API keys and branding settings.', 'uael' ),
			),
			'uael-info'        => array(
				'label'       => $plugin_name . ' ' . __( 'Information', 'uael' ),
				'description' => __( 'Read-only plugin info: version, widgets, categories.', 'uael' ),
			),
			'uael-design'      => array(
				'label'       => __( 'UAE Design System', 'uael' ),
				'description' => __( 'Read the site\'s global colors, fonts, and spacing.', 'uael' ),
			),
			'uael-maintenance' => array(
				'label'       => __( 'UAE Maintenance', 'uael' ),
				'description' => __( 'Cache clearing and maintenance tasks.', 'uael' ),
			),
		);

		foreach ( $categories as $slug => $args ) {
			wp_register_ability_category( $slug, $args );
		}
	}

	/**
	 * Ensure handler classes are loaded and registered in the registry.
	 *
	 * Safe to call multiple times — skips if handlers are already populated.
	 * Does NOT call discover() or wp_register_ability(); use register_abilities()
	 * for full registration with the WP Abilities API.
	 *
	 * @return void
	 */
	public function ensure_handlers_loaded() {
		if ( ! $this->registry || ! empty( $this->registry->get_handler_names() ) ) {
			return;
		}

		$this->load_pro_handlers();
		$this->load_standalone_handlers();
		$this->register_all_handlers();
	}

	/**
	 * Load and register all handlers (standalone mode).
	 *
	 * @return void
	 */
	public function register_abilities() {
		$this->load_pro_handlers();
		$this->load_standalone_handlers();
		$this->register_all_handlers();
		$this->registry->discover();
	}

	/**
	 * Load Pro-specific handler files (always loaded in both modes).
	 *
	 * @return void
	 */
	private function load_pro_handlers() {
		$base = UAEL_DIR . 'abilities/handlers/';

		// Skins.
		require_once $base . 'skins/class-uael-skins-list-handler.php';
		require_once $base . 'skins/class-uael-skins-activate-handler.php';
		require_once $base . 'skins/class-uael-skins-deactivate-handler.php';
		require_once $base . 'skins/class-uael-skins-bulk-toggle-handler.php';

		// Branding.
		require_once $base . 'branding/class-uael-branding-get-handler.php';
		require_once $base . 'branding/class-uael-branding-update-handler.php';

		// Integrations.
		require_once $base . 'integrations/class-uael-integrations-get-handler.php';
		require_once $base . 'integrations/class-uael-integrations-update-handler.php';
		require_once $base . 'integrations/class-uael-integrations-validate-handler.php';

		// Pro Info.
		require_once $base . 'pro-info/class-uael-pro-info-get-handler.php';
		require_once $base . 'pro-info/class-uael-pro-info-widget-handler.php';
		require_once $base . 'pro-info/class-uael-pro-info-categories-handler.php';
	}

	/**
	 * Load standalone handler files (only when HFE is NOT active).
	 *
	 * @return void
	 */
	private function load_standalone_handlers() {
		$base = UAEL_DIR . 'abilities/handlers/';

		// Widgets.
		require_once $base . 'widgets/class-uael-widget-list-handler.php';
		require_once $base . 'widgets/class-uael-widget-activate-handler.php';
		require_once $base . 'widgets/class-uael-widget-deactivate-handler.php';
		require_once $base . 'widgets/class-uael-widget-bulk-toggle-handler.php';
		require_once $base . 'widgets/class-uael-widget-deactivate-unused-handler.php';
		require_once $base . 'widgets/class-uael-widget-usage-handler.php';

		// Builder.
		require_once $base . 'builder/class-uael-build-handler.php';
		require_once $base . 'builder/class-uael-insert-widget-handler.php';
		require_once $base . 'builder/class-uael-update-widget-handler.php';
		require_once $base . 'builder/class-uael-remove-element-handler.php';
		require_once $base . 'builder/class-uael-move-element-handler.php';
		require_once $base . 'builder/class-uael-structure-handler.php';
		require_once $base . 'builder/class-uael-add-section-handler.php';
		require_once $base . 'builder/class-uael-add-column-handler.php';
		require_once $base . 'builder/class-uael-css-handler.php';
		require_once $base . 'builder/class-uael-widget-types-handler.php';
		require_once $base . 'builder/class-uael-schema-handler.php';
		require_once $base . 'builder/class-uael-undo-handler.php';

		// Pages.
		require_once $base . 'pages/class-uael-page-create-handler.php';
		require_once $base . 'pages/class-uael-page-list-handler.php';
		require_once $base . 'pages/class-uael-page-delete-handler.php';
		require_once $base . 'pages/class-uael-page-restore-handler.php';
		require_once $base . 'pages/class-uael-page-update-status-handler.php';
		require_once $base . 'pages/class-uael-page-update-meta-handler.php';

		// Design System.
		require_once $base . 'design-system/class-uael-design-tokens-handler.php';

		// Maintenance.
		require_once $base . 'maintenance/class-uael-maintenance-clear-cache-handler.php';
	}

	/**
	 * Register all handler instances with the registry (standalone mode).
	 *
	 * @return void
	 */
	private function register_all_handlers() {
		$handlers = array(
			// Pro-specific (12).
			new UAEL_Skins_List_Handler(),
			new UAEL_Skins_Activate_Handler(),
			new UAEL_Skins_Deactivate_Handler(),
			new UAEL_Skins_Bulk_Toggle_Handler(),
			new UAEL_Branding_Get_Handler(),
			new UAEL_Branding_Update_Handler(),
			new UAEL_Integrations_Get_Handler(),
			new UAEL_Integrations_Update_Handler(),
			new UAEL_Integrations_Validate_Handler(),
			new UAEL_Pro_Info_Get_Handler(),
			new UAEL_Pro_Info_Widget_Handler(),
			new UAEL_Pro_Info_Categories_Handler(),

			// Standalone (24).
			new UAEL_Widget_List_Handler(),
			new UAEL_Widget_Activate_Handler(),
			new UAEL_Widget_Deactivate_Handler(),
			new UAEL_Widget_Bulk_Toggle_Handler(),
			new UAEL_Widget_Deactivate_Unused_Handler(),
			new UAEL_Widget_Usage_Handler(),
			new UAEL_Build_Handler(),
			new UAEL_Insert_Widget_Handler(),
			new UAEL_Update_Widget_Handler(),
			new UAEL_Remove_Element_Handler(),
			new UAEL_Move_Element_Handler(),
			new UAEL_Structure_Handler(),
			new UAEL_Add_Section_Handler(),
			new UAEL_Add_Column_Handler(),
			new UAEL_CSS_Handler(),
			new UAEL_Widget_Types_Handler(),
			new UAEL_Schema_Handler(),
			new UAEL_Undo_Handler(),
			new UAEL_Page_Create_Handler(),
			new UAEL_Page_List_Handler(),
			new UAEL_Page_Delete_Handler(),
			new UAEL_Page_Restore_Handler(),
			new UAEL_Page_Update_Status_Handler(),
			new UAEL_Page_Update_Meta_Handler(),
			new UAEL_Design_Tokens_Handler(),
			new UAEL_Maintenance_Clear_Cache_Handler(),
		);

		foreach ( $handlers as $handler ) {
			$this->registry->register( $handler, 'uael' );
		}
	}

	/**
	 * Register Pro-specific handlers into HFE's registry (dual mode).
	 *
	 * Called via the uae_mcp_register_abilities hook when both plugins are active.
	 *
	 * @param object $hfe_registry HFE_Ability_Registry instance.
	 * @return void
	 */
	public function register_pro_handlers_into_hfe( $hfe_registry ) {
		// Load the handler interface if not already loaded by HFE.
		// HFE uses HFE_Ability_Handler but our handlers implement UAEL_Ability_Handler.
		// Since HFE's registry type-hints HFE_Ability_Handler, we need our handlers
		// to also implement that interface. For now, load Pro handlers and register
		// them — HFE's registry accepts any object with get_name(), get_registration_args(), execute().
		$this->load_pro_handlers();

		$pro_handlers = array(
			new UAEL_Skins_List_Handler(),
			new UAEL_Skins_Activate_Handler(),
			new UAEL_Skins_Deactivate_Handler(),
			new UAEL_Skins_Bulk_Toggle_Handler(),
			new UAEL_Branding_Get_Handler(),
			new UAEL_Branding_Update_Handler(),
			new UAEL_Integrations_Get_Handler(),
			new UAEL_Integrations_Update_Handler(),
			new UAEL_Integrations_Validate_Handler(),
			new UAEL_Pro_Info_Get_Handler(),
			new UAEL_Pro_Info_Widget_Handler(),
			new UAEL_Pro_Info_Categories_Handler(),
		);

		foreach ( $pro_handlers as $handler ) {
			$hfe_registry->register( $handler, 'uael' );
		}
	}

	/**
	 * Register dedicated MCP server (standalone mode).
	 *
	 * @param object $mcp_adapter The MCP Adapter instance.
	 * @return void
	 */
	public function register_mcp_server( $mcp_adapter ) {
		$ability_names = $this->registry->get_registered_names();

		if ( empty( $ability_names ) ) {
			return;
		}

		$settings = get_option( 'uae_mcp_settings', array() );

		// Strict opt-in — the server is only created when explicitly enabled.
		if ( empty( $settings['dedicated_server'] ) ) {
			return;
		}

		$branding    = \UltimateElementor\Classes\UAEL_Helper::get_white_labels();
		$plugin_name = ! empty( $branding['plugin']['name'] ) ? $branding['plugin']['name'] : __( 'UAE Pro', 'uael' );

		// Transport class fallback — older MCP Adapter builds expose
		// HttpTransport at a different namespace.
		$transport_class = class_exists( '\WP\MCP\Transport\HttpTransport' )
			? \WP\MCP\Transport\HttpTransport::class
			: '\WP\MCP\Transport\Http\RestTransport';

		// Explicit error + observability handlers — newer MCP Adapter releases
		// require concrete classes rather than null.
		$error_handler = class_exists( '\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler' )
			? \WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class
			: null;

		$observability = class_exists( '\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler' )
			? \WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class
			: null;

		$mcp_adapter->create_server(
			'uae-mcp-server',
			'uae',
			'mcp',
			$plugin_name . ' ' . __( 'MCP Server', 'uael' ),
			__( 'AI tools for Ultimate Addons for Elementor — manage widgets, build pages, and configure settings.', 'uael' ),
			'v' . ( defined( 'UAEL_VER' ) ? UAEL_VER : '0.0.0' ),
			array( $transport_class ),
			$error_handler,
			$observability,
			$ability_names,
			array(),
			array()
		);
	}

	/**
	 * Initialize Angie integration (standalone mode).
	 *
	 * @return void
	 */
	public function init_angie() {
		// Loaded here (not in load_framework) so the Angie class is only required
		// when the Angie integration is actually enabled.
		require_once UAEL_DIR . 'angie/class-uael-angie.php';
		new UAEL_Angie( $this->registry );
	}
}

// Boot.
UAEL_Abilities_Bootstrap::instance();
