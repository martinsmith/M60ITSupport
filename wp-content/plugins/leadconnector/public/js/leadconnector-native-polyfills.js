/**
 * Polyfill: window.crypto.randomUUID().
 *
 * Modern browsers (Safari 15.4+, Chrome 92+, Firefox 95+) ship the API
 * natively. The LeadConnector funnel templates that we render in "native"
 * display mode rely on it for upstream tracking IDs, so older Safari/iOS and
 * legacy embedded WebViews need a CSPRNG-backed shim. Loaded only on
 * LeadConnector native funnel pages via wp_enqueue_script(); see
 * setup_native_display_with_wp_headers() in admin/class-leadconnector-admin.php.
 *
 * @package LeadConnector
 * @since   3.0.31
 */
( function () {
	if ( typeof crypto === 'undefined' || typeof crypto.randomUUID === 'function' ) {
		return;
	}

	crypto.randomUUID = function () {
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c ) {
			var r = ( Math.random() * 16 ) | 0;
			var v = c === 'x' ? r : ( r & 0x3 ) | 0x8;
			return v.toString( 16 );
		} );
	};
} )();
