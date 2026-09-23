<?php
/**
 * Ability Handler Interface.
 *
 * Contract for all ability handler classes. Each handler encapsulates
 * one logical ability: its WP Abilities API registration and execution.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable Generic.Classes.DuplicateClassName.Found, Generic.Files.OneObjectStructurePerFile.MultipleFound
if ( interface_exists( 'HFE_Ability_Handler' ) ) {
	/**
	 * Interface UAEL_Ability_Handler — extends HFE for dual-plugin compatibility.
	 *
	 * @since 1.45.0
	 */
	interface UAEL_Ability_Handler extends HFE_Ability_Handler {}
} else {
	/**
	 * Interface UAEL_Ability_Handler — standalone.
	 *
	 * @since 1.45.0
	 */
	interface UAEL_Ability_Handler {

		/**
		 * Get the ability name.
		 *
		 * @return string Ability name without plugin prefix.
		 */
		public function get_name();

		/**
		 * Get the full wp_register_ability() args array.
		 *
		 * @return array Ability registration args.
		 */
		public function get_registration_args();

		/**
		 * Execute the ability.
		 *
		 * @param array $input Validated input parameters.
		 * @return array|WP_Error Result data or error.
		 */
		public function execute( $input );
	}
}
// phpcs:enable
