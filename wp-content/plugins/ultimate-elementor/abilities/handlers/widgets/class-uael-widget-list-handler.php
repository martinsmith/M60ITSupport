<?php
/**
 * Widget List Handler.
 *
 * Returns all available widgets with their status and metadata.
 *
 * @package UAEL
 * @since 1.45.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UAEL_Widget_List_Handler
 *
 * Implements UAEL_Ability_Handler for the widgets/list ability.
 *
 * @since 1.45.0
 */
class UAEL_Widget_List_Handler implements UAEL_Ability_Handler {

	/**
	 * Get the ability name.
	 *
	 * @since 1.45.0
	 *
	 * @return string Ability name without plugin prefix.
	 */
	public function get_name() {
		return 'widgets-list';
	}

	/**
	 * Get the wp_register_ability() args array.
	 *
	 * Does NOT include execute_callback -- the registry sets that automatically.
	 *
	 * @since 1.45.0
	 *
	 * @return array Ability registration args.
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'List Widgets', 'uael' ),
			'description'         => __( 'Lists all available widgets with their enabled/disabled status, slug, title, and category.', 'uael' ),
			'category'            => 'uael-widgets',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'        => array( 'type' => 'string' ),
						'class_name'  => array( 'type' => 'string' ),
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'is_active'   => array( 'type' => 'boolean' ),
						'is_pro'      => array( 'type' => 'boolean' ),
						'category'    => array( 'type' => 'string' ),
						'icon'        => array( 'type' => 'string' ),
						'doc_url'     => array( 'type' => 'string' ),
						'demo_url'    => array( 'type' => 'string' ),
					),
				),
			),
			'meta'                => array(
				'annotations'  => array(
					'readonly'     => true,
					'destructive'  => false,
					'idempotent'   => true,
					'instructions' => 'Use this to show the user all available widgets and their activation status. Helpful before activating or deactivating widgets.',
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
	 * @param array $input Unused input parameters.
	 * @return array Array of widget data objects.
	 */
	public function execute( $input ) {
		$widgets = \UltimateElementor\Classes\UAEL_Helper::get_widget_options();
		$result  = array();

		if ( ! is_array( $widgets ) ) {
			return $result;
		}

		foreach ( $widgets as $class_name => $data ) {
			$result[] = array(
				'slug'        => isset( $data['slug'] ) ? $data['slug'] : '',
				'class_name'  => $class_name,
				'title'       => isset( $data['title'] ) ? $data['title'] : '',
				'description' => isset( $data['description'] ) ? $data['description'] : '',
				'is_active'   => ! empty( $data['is_activate'] ),
				'is_pro'      => isset( $data['is_pro'] ) ? (bool) $data['is_pro'] : false,
				'category'    => isset( $data['category'] ) ? $data['category'] : '',
				'icon'        => isset( $data['icon'] ) ? $data['icon'] : '',
				'doc_url'     => isset( $data['doc_url'] ) ? $data['doc_url'] : '',
				'demo_url'    => isset( $data['demo_url'] ) ? $data['demo_url'] : '',
			);
		}

		return $result;
	}
}
