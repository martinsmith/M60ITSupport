<?php
/**
 * Ability Registry.
 *
 * Central registry for all UAE ability handlers. Manages handler storage,
 * disabled-ability filtering, and WP Abilities API registration.
 *
 * Feeds all three platforms:
 * 1. WP Abilities API (wp_register_ability)
 * 2. MCP Adapter ($adapter->create_server)
 * 3. Angie (REST routes)
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Ability_Registry
 *
 * @since 1.45.0
 */
class UAEL_Ability_Registry {

	/**
	 * Plugin prefix for ability names.
	 *
	 * @var string
	 */
	const PREFIX = 'uae/';

	/**
	 * Registered handlers keyed by ability name (without prefix).
	 *
	 * @var array<string, array{handler: UAEL_Ability_Handler, source: string}>
	 */
	private $handlers = array();

	/**
	 * Registered WP ability names (full, with prefix) after discover().
	 *
	 * @var string[]
	 */
	private $registered_names = array();

	/**
	 * Register a handler.
	 *
	 * @param UAEL_Ability_Handler $handler Handler instance.
	 * @param string               $source  Source identifier (e.g., 'uael', 'hfe').
	 * @return void
	 */
	public function register( UAEL_Ability_Handler $handler, $source = 'uael' ) {
		$name = $handler->get_name();

		// Defensive: reject duplicate ability names so a later registration
		// can't silently shadow an existing one. The first source wins.
		if ( isset( $this->handlers[ $name ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: 1: ability name, 2: source attempting to register, 3: existing source */
					esc_html__( 'Ability "%1$s" already registered by "%3$s"; ignoring duplicate registration from "%2$s".', 'uael' ),
					esc_html( $name ),
					esc_html( $source ),
					esc_html( $this->handlers[ $name ]['source'] )
				),
				'1.46.0'
			);
			return;
		}

		$this->handlers[ $name ] = array(
			'handler' => $handler,
			'source'  => $source,
		);
	}

	/**
	 * Discover all enabled handlers and register them with WP Abilities API.
	 *
	 * Respects:
	 * - disabled_abilities setting (per-ability toggles from settings page)
	 * - allow_modifications setting (gates write abilities)
	 *
	 * @return string[] Array of registered ability names (with prefix).
	 */
	public function discover() {
		$settings            = get_option( 'uae_mcp_settings', array() );
		$allow_modifications = ! empty( $settings['allow_modifications'] );
		$disabled_abilities  = ! empty( $settings['disabled_abilities'] ) && is_array( $settings['disabled_abilities'] )
			? $settings['disabled_abilities']
			: array();

		$this->registered_names = array();

		foreach ( $this->handlers as $name => $entry ) {
			
			$full_name = self::PREFIX . $name;

			// Check per-ability toggle.
			if ( in_array( $name, $disabled_abilities, true ) || in_array( $full_name, $disabled_abilities, true ) ) {
				continue;
			}

			$handler = $entry['handler'];
			$args    = $handler->get_registration_args();

			// Gate write abilities behind allow_modifications.
			if ( ! $allow_modifications ) {
				$is_readonly = ! empty( $args['meta']['annotations']['readonly'] );

				if ( ! $is_readonly ) {
					continue;
				}
			}

			// Set execute callback to route through handler.
			$args['execute_callback'] = array( $handler, 'execute' );

			wp_register_ability( $full_name, $args );
			$this->registered_names[] = $full_name;
		}

		return $this->registered_names;
	}

	/**
	 * Execute an ability by name.
	 *
	 * @param string $name   Ability name (with or without prefix).
	 * @param array  $params Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public function execute( $name, $params = array() ) {
		$short_name = 0 === strpos( $name, self::PREFIX )
			? substr( $name, strlen( self::PREFIX ) )
			: $name;

		// Verify the ability passed discover() gating.
		if ( ! $this->is_registered( $short_name ) ) {
			$reason       = $this->get_gate_reason( $short_name );
			$settings_url = admin_url( 'admin.php?page=uaepro#settings' );

			if ( 'allow_modifications' === $reason ) {
				$message = sprintf(
					'BLOCKED: Ability "%1$s" requires "Allow Modifications" to be enabled. Do NOT attempt to enable this yourself. Tell the user: turn on "Allow Modifications" in UAE Settings → AI Tools at %2$s',
					$name,
					$settings_url
				);
			} elseif ( 'disabled' === $reason ) {
				$message = sprintf(
					'BLOCKED: Ability "%1$s" is disabled by the site administrator. Do NOT retry. Tell the user to enable it in AI Tools settings: %2$s',
					$name,
					$settings_url
				);
			} else {
				$message = sprintf(
					/* translators: 1: ability name, 2: settings URL */
					__( 'Ability "%1$s" is not available. Check settings: %2$s', 'uael' ),
					$name,
					$settings_url
				);
			}

			return new \WP_Error(
				'uael_ability_disabled',
				$message,
				array(
					'status'       => 403,
					'reason'       => $reason,
					'settings_url' => $settings_url,
				)
			);
		}

		if ( isset( $this->handlers[ $short_name ] ) ) {
			return $this->handlers[ $short_name ]['handler']->execute( $params );
		}

		return new \WP_Error(
			'uael_ability_not_found',
			sprintf(
				/* translators: %s: ability name */
				__( 'Ability "%s" not found.', 'uael' ),
				$name
			),
			array( 'status' => 404 )
		);
	}

	/**
	 * Get all registered handler names (without prefix).
	 *
	 * @return string[]
	 */
	public function get_handler_names() {
		return array_keys( $this->handlers );
	}

	/**
	 * Get all registered WP ability names (with prefix) after discover().
	 *
	 * @return string[]
	 */
	public function get_registered_names() {
		return $this->registered_names;
	}

	/**
	 * Check if an ability is registered (passed discover() gating).
	 *
	 * @param string $name Handler name (without prefix).
	 * @return bool
	 */
	public function is_registered( $name ) {
		
		$full_name = self::PREFIX . $name;
		return in_array( $full_name, $this->registered_names, true );
	}

	/**
	 * Get the reason an ability was gated by discover().
	 *
	 * @param string $name Handler name (without prefix).
	 * @return string 'allow_modifications'|'disabled'|'unknown'
	 */
	public function get_gate_reason( $name ) {
		if ( ! isset( $this->handlers[ $name ] ) ) {
			return 'unknown';
		}

		$settings           = get_option( 'uae_mcp_settings', array() );
		$disabled_abilities = ! empty( $settings['disabled_abilities'] ) && is_array( $settings['disabled_abilities'] )
			? $settings['disabled_abilities']
			: array();

		
		$full_name = self::PREFIX . $name;

		if ( in_array( $name, $disabled_abilities, true ) || in_array( $full_name, $disabled_abilities, true ) ) {
			return 'disabled';
		}

		$allow_modifications = ! empty( $settings['allow_modifications'] );

		if ( ! $allow_modifications ) {
			$args        = $this->handlers[ $name ]['handler']->get_registration_args();
			$is_readonly = ! empty( $args['meta']['annotations']['readonly'] );

			if ( ! $is_readonly ) {
				return 'allow_modifications';
			}
		}

		return 'unknown';
	}

	/**
	 * Get a handler by name.
	 *
	 * @param string $name Handler name (without prefix).
	 * @return UAEL_Ability_Handler|null
	 */
	public function get_handler( $name ) {
		return isset( $this->handlers[ $name ] ) ? $this->handlers[ $name ]['handler'] : null;
	}

	/**
	 * Get all handlers with their registration args (for settings UI / REST).
	 *
	 * @return array Array of ability info objects.
	 */
	public function get_abilities_info() {
		$abilities = array();

		foreach ( $this->handlers as $name => $entry ) {
			$handler = $entry['handler'];
			$args    = $handler->get_registration_args();

			$abilities[] = array(
				'name'        => $name,
				'full_name'   => self::PREFIX . $name,
				'label'       => $args['label'] ?? '',
				'description' => $args['description'] ?? '',
				'category'    => $args['category'] ?? '',
				'source'      => $entry['source'],
				'readonly'    => ! empty( $args['meta']['annotations']['readonly'] ),
				'destructive' => ! empty( $args['meta']['annotations']['destructive'] ),
			);
		}

		return $abilities;
	}
}
