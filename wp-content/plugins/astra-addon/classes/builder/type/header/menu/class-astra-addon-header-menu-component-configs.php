<?php
/**
 * Astra Addon Customizer Configuration for Menu.
 *
 * @package     Astra Addon
 * @link        https://wpastra.com/
 * @since       3.3.0
 */

// No direct access, please.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Builder Customizer Configurations.
 *
 * @since 3.3.0
 */
class Astra_Addon_Header_Menu_Component_Configs extends Astra_Customizer_Config_Base {
	/**
	 * Register Builder Customizer Configurations.
	 *
	 * @param Array                $configurations Astra Customizer Configurations.
	 * @param WP_Customize_Manager $wp_customize instance of WP_Customize_Manager.
	 * @since 3.3.0
	 * @return Array Astra Customizer Configurations with updated configurations.
	 */
	public function register_configuration( $configurations, $wp_customize ) {

		$html_config     = array();
		$component_limit = astra_addon_builder_helper()->component_limit;

		for ( $index = 1; $index <= $component_limit; $index++ ) {

			$_section = 'section-hb-menu-' . $index;
			$_prefix  = 'menu' . $index;

			$html_config[] = Astra_Addon_Base_Configs::prepare_box_shadow_tab( $_section, 'header-' . $_prefix, 100 );

			// Mega menu / custom dropdown discoverability nudge (Pro informational). Only when the Nav Menu module is active.
			if ( Astra_Ext_Extension::is_active( 'nav-menu' ) ) {
				$html_config[] = array(
					array(
						'name'     => ASTRA_THEME_SETTINGS . '[header-' . $_prefix . '-mega-menu-info]',
						'type'     => 'control',
						'control'  => 'ast-description',
						'section'  => $_section,
						'priority' => 999,
						'context'  => Astra_Builder_Helper::$general_tab,
						'help'     => sprintf(
							/* translators: 1: Appearance admin link, 2: Menus admin link, 3: documentation link. */
							__( 'Turn any menu item into a large, multi-column dropdown (a mega menu) from %1$s → %2$s. %3$s', 'astra-addon' ),
							'<a href="' . esc_url( admin_url( 'themes.php' ) ) . '">' . esc_html__( 'Appearance', 'astra-addon' ) . '</a>',
							'<a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '">' . esc_html__( 'Menus', 'astra-addon' ) . '</a>',
							'<a href="' . esc_url( astra_get_pro_url( '/docs/nav-menu-addon/', 'astra-pro', 'header-builder-menu', 'mega-menu-docs' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn how', 'astra-addon' ) . '</a>'
						),
						'divider'  => array( 'ast_class' => 'ast-top-section-divider' ),
					),
				);
			} else {
				// Nav Menu module inactive — nudge the user to enable it from the Astra dashboard.
				$html_config[] = array(
					array(
						'name'     => ASTRA_THEME_SETTINGS . '[header-' . $_prefix . '-mega-menu-enable]',
						'type'     => 'control',
						'control'  => 'ast-description',
						'section'  => $_section,
						'priority' => 999,
						'context'  => Astra_Builder_Helper::$general_tab,
						'help'     => sprintf(
							/* translators: 1: Astra Dashboard link, 2: Learn more link. */
							__( 'Enable the Nav Menu module from the %1$s to turn any menu item into a large, multi-column dropdown (a mega menu). %2$s', 'astra-addon' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=astra' ) ) . '">' . esc_html__( 'Astra Dashboard', 'astra-addon' ) . '</a>',
							'<a href="' . esc_url( astra_get_pro_url( '/mega-menu/', 'astra-pro', 'header-builder-menu', 'mega-menu-learn' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more', 'astra-addon' ) . '</a>'
						),
						'divider'  => array( 'ast_class' => 'ast-top-section-divider' ),
					),
				);
			}
		}

		$html_config = call_user_func_array( 'array_merge', $html_config + array( array() ) );
		return array_merge( $configurations, $html_config );
	}
}

/**
 * Kicking this off by creating object of this class.
 */

new Astra_Addon_Header_Menu_Component_Configs();
