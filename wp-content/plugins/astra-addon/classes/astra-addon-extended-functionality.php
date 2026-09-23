<?php
/**
 * Astra Addon BSF & WP-Com package extended functionality.
 *
 * In this file as per WooCommerce.com standards we manipulated following things -
 * 1. Deprecation of Code editor due to usage of
 *      i) eval()
 *      ii) echo $php_snippet;
 * 2. Removed modern checkout layout's easy login due to $_POST['password'] sanitization case.
 *
 * @package Astra Addon
 * @since 4.1.1
 */

/**
 * Check if code editor custom layout enabled.
 *
 * @param  int $post_id Post Id.
 * @return bool
 * @since 4.1.5
 */
function astra_addon_is_code_editor_layout( $post_id ) {
	$post_id = is_scalar( $post_id ) ? absint( $post_id ) : 0;
	if ( ! $post_id ) {
		return false;
	}

	if ( 'code_editor' !== get_post_meta( $post_id, 'editor_type', true ) ) {
		return false;
	}

	// Bail if the post type is not the one that can have a code editor layout.
	return defined( 'ASTRA_ADVANCED_HOOKS_POST_TYPE' ) && ASTRA_ADVANCED_HOOKS_POST_TYPE === get_post_type( $post_id );
}

/**
 * Sanitize a code-editor snippet that is NOT going to be executed.
 *
 * PHP blocks are removed before sanitizing: wp_kses_post() only discards `<?php ... ?>`
 * when the block body contains no `>`, so a snippet containing `>`, `=>` or `->` would
 * otherwise have its opening tag consumed as a malformed HTML tag and the remaining
 * source code — potentially including credentials — printed to visitors.
 *
 * Script bodies are left visible on purpose: wp_kses_post() drops the `<script>` tag but keeps
 * its text, and that visible JavaScript is the signal that a snippet has stopped running.
 *
 * @param  string $code Stored snippet.
 * @return string Safe markup, with any PHP source removed.
 * @since 4.13.7
 */
function astra_addon_sanitize_unexecuted_snippet( $code ) {
	if ( ! is_string( $code ) || '' === $code ) {
		return '';
	}

	// Remove PHP blocks entirely; __return_empty_string() replaces each match with ''.
	$html_only = preg_replace_callback( '/<\?(?:php|=)?.*?(?:\?>|$)/s', '__return_empty_string', $code );
	return wp_kses_post( (string) $html_only );
}

/**
 * Whether stored snippets are executed on the front end.
 *
 * Only administrators can create or edit these layouts, so stored code is trusted and the check is
 * site-wide rather than per layout. Shared with the editor screen so the notice cannot disagree
 * with what actually runs.
 *
 * @return bool
 * @since 4.13.8
 */
function astra_addon_is_snippet_executable() {
	return ! defined( 'ASTRA_ADVANCED_HOOKS_DISABLE_PHP' );
}

/**
 * Get PHP snippet if enabled.
 *
 * The returned value is either the output of an executed snippet or sanitized markup — never raw
 * stored source.
 *
 * @param  int $post_id Post Id.
 * @return string|false Snippet output, or false when the layout is not a code editor layout.
 * @since 4.1.1
 */
function astra_addon_get_php_snippet( $post_id ) {
	if ( ! astra_addon_is_code_editor_layout( $post_id ) ) {
		return false;
	}

	$code = get_post_meta( $post_id, 'ast-advanced-hook-php-code', true );
	if ( ! is_string( $code ) ) {
		return '';
	}

	if ( ! astra_addon_is_snippet_executable() ) {
		return astra_addon_sanitize_unexecuted_snippet( $code );
	}

	ob_start();
	// @codingStandardsIgnoreStart
	eval( '?>' . $code . '<?php ' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Ignored PHP standards to execute PHP code snipett.
	// @codingStandardsIgnoreEnd
	return ob_get_clean();
}

/**
 * Echo PHP snippet if enabled.
 *
 * @param  int $post_id Post Id.
 * @since 4.1.1
 */
function astra_addon_echo_php_snippet( $post_id ) {
	if ( astra_addon_is_code_editor_layout( $post_id ) ) {
		$php_snippet = astra_addon_get_php_snippet( $post_id );
		echo $php_snippet; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Value is either eval() output from an admin-authored snippet (trusted) or markup already sanitized in astra_addon_get_php_snippet(); it must stay raw here.
	}
}

/**
 * Check email exist.
 *
 * @since 3.9.0
 */
function astra_addon_woocommerce_login_user() {

	check_ajax_referer( 'woocommerce-login', 'security' );

	$response = array(
		'success' => false,
	);

	$user_name_email          = isset( $_POST['user_name_email'] ) ? sanitize_text_field( wp_unslash( $_POST['user_name_email'] ) ) : false;
	$password                 = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : false; // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$selected_user_name_email = '';

	if ( filter_var( $user_name_email, FILTER_VALIDATE_EMAIL ) ) {
		$selected_user_name_email = sanitize_email( $user_name_email );
	} else {
		$selected_user_name_email = $user_name_email;
	}

	$creds = array(
		'user_login'    => $selected_user_name_email,
		'user_password' => $password,
		'remember'      => false,
	);

	$user = wp_signon( $creds, false );

	if ( ! is_wp_error( $user ) ) {

		$response = array(
			'success' => true,
		);
	} else {
		$response['error'] = wp_kses_post( $user->get_error_message() );
	}

	wp_send_json_success( $response );
}

// Login user on modern checkout layout.
add_action( 'wp_ajax_astra_woocommerce_login_user', 'astra_addon_woocommerce_login_user' );
add_action( 'wp_ajax_nopriv_astra_woocommerce_login_user', 'astra_addon_woocommerce_login_user' );

/**
 * Function to filter input of Custom Layout's code editor.
 *
 * @param  string $output Output.
 * @param  string $key Key.
 * @return string
 * @since 4.5.0
 */
function astra_addon_filter_code_editor( $output, $key ) {
	return filter_input( INPUT_POST, $key, FILTER_DEFAULT ); // phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions.RestrictedFilter -- Default filter after all other cases, Keeping this filter for backward compatibility.
}

add_filter( 'astra_addon_php_default_filter_input', 'astra_addon_filter_code_editor', 10, 2 );
