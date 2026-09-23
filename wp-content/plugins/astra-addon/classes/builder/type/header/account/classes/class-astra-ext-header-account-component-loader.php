<?php
/**
 * Account Styling Loader for Astra theme.
 *
 * @package     Astra Builder
 * @link        https://www.brainstormforce.com
 * @since       Astra 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Customizer Initialization
 *
 * @since 3.0.0
 */
// @codingStandardsIgnoreStart
class Astra_Ext_Header_Account_Component_Loader {
 // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	// @codingStandardsIgnoreEnd

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		add_filter( 'astra_theme_defaults', array( $this, 'theme_defaults' ) );
		add_filter( 'astra_get_option_header-account-type', array( $this, 'maybe_fallback_account_type' ) );
	}

	/**
	 * Fallback to the default account type when the plugin backing the saved account type is no longer active.
	 *
	 * A stale 'woocommerce'/'lifterlms' value leaves the account element unable to resolve the My Account
	 * page URL (anchor with no href); returning 'default' restores the custom account link fallback.
	 *
	 * @param mixed $account_type Saved account type option value.
	 *
	 * @since 4.13.7
	 * @return mixed Account type to use.
	 */
	public function maybe_fallback_account_type( $account_type ) {
		if (
			( 'woocommerce' === $account_type && ! class_exists( 'WooCommerce' ) ) ||
			( 'lifterlms' === $account_type && ! class_exists( 'LifterLMS' ) )
		) {
			return 'default';
		}

		return $account_type;
	}

	/**
	 * Default customizer configs.
	 *
	 * @param  array $defaults  Astra options default value array.
	 *
	 * @since 3.0.0
	 */
	public function theme_defaults( $defaults ) {
		// Account header defaults.
		$defaults['header-account-icon-type']              = 'account-1';
		$defaults['header-account-action-menu-display-on'] = 'hover';

		return $defaults;
	}

}

/**
 *  Kicking this off by creating the object of the class.
 */
new Astra_Ext_Header_Account_Component_Loader();
