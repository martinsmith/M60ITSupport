<?php
/**
 * UAEL Posts Helper.
 *
 * @package UAEL
 */

namespace UltimateElementor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class UAEL_Posts_Helper.
 */
class UAEL_Posts_Helper {

	/**
	 * Transient key for the cached Author Filter dropdown list.
	 *
	 * Deliberately not identical to the `uael_post_loop_user_list` filter tag, so
	 * a grep for either lands on one thing.
	 *
	 * @since 1.45.4
	 * @var string
	 */
	const USER_LIST_TRANSIENT = 'uael_post_loop_user_list_cache';

	/**
	 * Per-request memo for the Author Filter dropdown list.
	 *
	 * @since 1.45.4
	 * @var array|null
	 */
	private static $user_list_memo = null;

	/**
	 * Whether the current request is an Elementor editor context.
	 *
	 * Control `options` arrays are only ever read when the editor panel draws a
	 * control. Elementor still registers the whole control stack while rendering
	 * on the frontend, so any query run purely to populate `options` is wasted
	 * work there.
	 *
	 * AJAX needs care. The editor fetches control config via `get_widgets_config`
	 * over admin-ajax, where is_edit_mode() and is_preview_mode() both return
	 * false (see #1395). A bare is_admin() check would also match the Posts
	 * widget's frontend pagination handler (wp_ajax_nopriv_uael_get_post), which
	 * would keep re-running these queries for every visitor "load more" click, so
	 * AJAX only counts as editor context when the action is `elementor_ajax`.
	 *
	 * @since 1.45.4
	 * @access public
	 * @return bool True when the request may draw the editor panel.
	 */
	public static function is_editor_context() {

		// The is_string() check is deliberate: it keeps `?action[]=x` from reaching
		// sanitize_key() and raising a TypeError, without relying on the is_scalar
		// guard in admin-ajax.php.
		$action         = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for Elementor's editor AJAX action; Elementor verifies its own nonce for it.
		$is_editor_ajax = wp_doing_ajax() && 'elementor_ajax' === $action;

		if ( $is_editor_ajax || ( is_admin() && ! wp_doing_ajax() ) ) {
			return true;
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$elementor = \Elementor\Plugin::$instance;

		if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}

		return isset( $elementor->preview ) && $elementor->preview->is_preview_mode();
	}

	/**
	 * Get Post Types.
	 *
	 * @since 1.5.2
	 * @access public
	 */
	public static function get_post_types() {

		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$options = array();

		foreach ( $post_types as $post_type ) {
			$options[ $post_type->name ] = $post_type->label;
		}

		// Deprecated 'Media' post type.
		$key = array_search( 'Media', $options, true );
		if ( 'attachment' === $key ) {
			unset( $options[ $key ] );
		}

		return apply_filters( 'uael_loop_post_types', $options );
	}

	/**
	 * Get Post Taxonomies.
	 *
	 * @since 1.5.2
	 * @param string $post_type Post type.
	 * @access public
	 */
	public static function get_taxonomy( $post_type ) {

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$data       = array();

		foreach ( $taxonomies as $tax_slug => $tax ) {
			if ( ! $tax->public || ! $tax->show_ui ) {
				continue;
			}

			$data[ $tax_slug ] = $tax;
		}

		return apply_filters( 'uael_post_loop_taxonomies', $data, $taxonomies, $post_type );
	}

	/**
	 * Get size information for all currently-registered image sizes.
	 *
	 * @global $_wp_additional_image_sizes
	 * @uses   get_intermediate_image_sizes()
	 * @link   https://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes
	 * @since 1.5.2
	 * @return array $sizes Data for all currently-registered image sizes.
	 */
	public static function get_image_sizes() {

		global $_wp_additional_image_sizes;

		$sizes  = get_intermediate_image_sizes(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_intermediate_image_sizes_get_intermediate_image_sizes
		$result = array();

		foreach ( $sizes as $size ) {
			if ( in_array( $size, array( 'thumbnail', 'medium', 'medium_large', 'large' ), true ) ) {
				$result[ $size ] = ucwords( trim( str_replace( array( '-', '_' ), array( ' ', ' ' ), $size ) ) );
			} else {
				$result[ $size ] = sprintf(
					'%1$s (%2$sx%3$s)',
					ucwords( trim( str_replace( array( '-', '_' ), array( ' ', ' ' ), $size ) ) ),
					$_wp_additional_image_sizes[ $size ]['width'],
					$_wp_additional_image_sizes[ $size ]['height']
				);
			}
		}

		$result = array_merge(
			array(
				'full' => esc_html__( 'Full', 'uael' ),
			),
			$result
		);

		$result['custom'] = esc_html__( 'Custom', 'uael' );

		$result = apply_filters( 'uael_post_featured_image_sizes', $result );

		return $result;
	}

	/**
	 * Get list of users.
	 *
	 * Populates the Author Filter dropdown. Memoised per request and cached in a
	 * transient, since the result only changes when users or roles change.
	 *
	 * @uses   get_users()
	 * @link   https://codex.wordpress.org/Function_Reference/get_users
	 * @since 1.5.2
	 * @since 1.45.4 Added the pre-query short-circuit filter, memoisation and transient cache.
	 * @return array $users Data for all users.
	 */
	public static function get_users() {

		/**
		 * Short-circuit the Author Filter list before any database query runs.
		 *
		 * Return an array to use it verbatim and skip the user lookup entirely.
		 * Unlike `uael_post_loop_user_list`, which runs after the query, this lets
		 * a site avoid the cost of building the list at all.
		 *
		 * @since 1.45.4
		 * @param array|null $pre_user_list Null to run the default lookup.
		 */
		$pre_user_list = apply_filters( 'uael_post_loop_pre_user_list', null );

		if ( is_array( $pre_user_list ) ) {
			return $pre_user_list;
		}

		if ( is_array( self::$user_list_memo ) ) {
			return self::$user_list_memo;
		}

		$user_list = get_transient( self::USER_LIST_TRANSIENT );

		if ( ! is_array( $user_list ) ) {
			$user_list = self::query_users();
			set_transient( self::USER_LIST_TRANSIENT, $user_list, DAY_IN_SECONDS );
		}

		self::$user_list_memo = apply_filters( 'uael_post_loop_user_list', $user_list );

		return self::$user_list_memo;
	}

	/**
	 * Query the users eligible for the Author Filter dropdown.
	 *
	 * Queried one role at a time on purpose.
	 *
	 * A single `role__in` query with four roles builds an OR-relation clause set on
	 * one meta key, so stock WordPress emits one usermeta join and four leading
	 * wildcard LIKE scans. Those LIKEs are unindexable, so the whole usermeta table
	 * is scanned and then filesorted by user_login on every call. On a site with
	 * hundreds of thousands of usermeta rows that alone can run for minutes and
	 * pile up across concurrent requests.
	 *
	 * Note that stock WordPress does NOT add DISTINCT here, despite the OR relation:
	 * WP_User_Query assigns the role clauses straight onto WP_Meta_Query::$queries
	 * and then calls parse_query_vars() with them, which finds no meta_query key,
	 * builds an empty array and returns early out of __construct(). sanitize_query()
	 * therefore never runs over the OR group and has_or_relation() stays false. The
	 * four-join plus DISTINCT plan seen on the reporting site comes from Index WP
	 * Users For Speed rewriting roles to distinct per-role meta keys, which makes
	 * the clauses mutually incompatible.
	 *
	 * Four single-role lookups return an identical set of users and avoid the OR
	 * relation entirely. Combined with the transient in get_users(), this runs about
	 * once a day rather than on every editor load.
	 *
	 * No result cap is applied on purpose. Elementor's SELECT2 template renders only
	 * the entries present in `options`, so a truncated list would make an already
	 * saved author silently disappear from the control.
	 *
	 * @since 1.45.4
	 * @access private
	 * @return array User IDs mapped to user logins.
	 */
	private static function query_users() {

		$roles     = array( 'administrator', 'editor', 'author', 'contributor' );
		$user_list = array();

		foreach ( $roles as $role ) {

			$users = get_users(
				array(
					'role'    => $role,
					'fields'  => array( 'ID', 'user_login' ),
					'orderby' => 'login',
				)
			);

			foreach ( $users as $user ) {
				$user_list[ $user->ID ] = $user->user_login;
			}
		}

		natcasesort( $user_list );

		return $user_list;
	}

	/**
	 * Clear the cached Author Filter list.
	 *
	 * Hooked to the user and role lifecycle actions so the dropdown stays current
	 * without waiting for the transient to expire.
	 *
	 * @since 1.45.4
	 * @access public
	 * @return void
	 */
	public static function flush_user_list_cache() {
		self::$user_list_memo = null;
		delete_transient( self::USER_LIST_TRANSIENT );
	}

	/**
	 * Get list of categories.
	 *
	 * @since 1.36.0
	 * @param array $args Optional. Arguments to pass to get_categories().
	 * @access public
	 * @return array Categories array with term_id as key and name as value.
	 */
	public static function get_categories( $args = array() ) {
		$default_args = array(
			'hide_empty' => false,
		);

		$args       = wp_parse_args( $args, $default_args );
		$categories = get_categories( $args );
		$options    = array();

		if ( empty( $categories ) ) {
			return $options;
		}

		foreach ( $categories as $category ) {
			$options[ $category->term_id ] = $category->name;
		}

		return apply_filters( 'uael_categories_list', $options );
	}

	/**
	 * Get list of tags.
	 *
	 * @since 1.36.0
	 * @param array $args Optional. Arguments to pass to get_tags().
	 * @access public
	 * @return array Tags array with term_id as key and name as value.
	 */
	public static function get_tags( $args = array() ) {
		$default_args = array(
			'hide_empty' => false,
		);

		$args    = wp_parse_args( $args, $default_args );
		$tags    = get_tags( $args );
		$options = array();

		if ( empty( $tags ) ) {
			return $options;
		}

		foreach ( $tags as $tag ) {
			$options[ $tag->term_id ] = $tag->name;
		}

		return apply_filters( 'uael_tags_list', $options );
	}

	/**
	 * Get terms for a specific taxonomy.
	 *
	 * @since 1.36.0
	 * @param string $taxonomy The taxonomy name.
	 * @param array  $args Optional. Arguments to pass to get_terms().
	 * @access public
	 * @return array Terms array with term_id as key and name as value.
	 */
	public static function get_terms_by_taxonomy( $taxonomy, $args = array() ) {
		$default_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		);

		$args    = wp_parse_args( $args, $default_args );
		$terms   = get_terms( $args );
		$options = array();

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $options;
		}

		foreach ( $terms as $term ) {
			$options[ $term->term_id ] = $term->name;
		}

		return apply_filters( 'uael_terms_list_' . $taxonomy, $options );
	}
}
