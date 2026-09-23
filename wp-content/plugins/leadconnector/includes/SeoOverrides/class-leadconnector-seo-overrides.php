<?php
/**
 *
 * LeadConnector Plugin
 * Copyright (C) 2020-2026 LeadConnector
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package LeadConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option prefix & fields
 */
if ( ! defined( 'LEADCONNECTOR_SEO_OPT_PREFIX' ) ) {
	define( 'LEADCONNECTOR_SEO_OPT_PREFIX', 'leadconnector_seo_overrides_by_path-' );
}
if ( ! defined( 'LEADCONNECTOR_SEO_FIELDS' ) ) {
	define( 'LEADCONNECTOR_SEO_FIELDS', array( 'page_title', 'meta_description', 'meta_keywords' ) );
}

/**
 * Current request path in your storage format:
 *  - lowercase
 *  - no leading/trailing slash
 *  Examples: 'book-appointment', 'category/uncategorized', '' (home)
 */
function leadconnector_seo_current_path_key(): string {
	// H9: Sanitize REQUEST_URI; wp_parse_url for consistent path parsing.
	$raw      = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$req_path = wp_parse_url( $raw, PHP_URL_PATH );
	if ( ! is_string( $req_path ) || '' === $req_path ) {
		$req_path = '/';
	}
	$path = trim( strtolower( preg_replace( '#/+#', '/', $req_path ) ), '/' );
	return $path; // '' = home
}

/**
 * Build option_name => leadconnector_seo_overrides_by_path-{path}-{field}.
 *
 * @param string $path  URL path key.
 * @param string $field SEO field name.
 * @return string
 */
function leadconnector_seo_option_name( string $path, string $field ): string {
	return LEADCONNECTOR_SEO_OPT_PREFIX . $path . '-' . $field;
}

/**
 * Decode stored value: URL-decode + underscores to spaces + trim.
 *
 * @param string $v Raw stored value.
 * @return string
 */
function leadconnector_seo_decode( string $v ): string {
	$v = rawurldecode( $v );
	$v = str_replace( '_', ' ', $v );
	return trim( $v );
}

/**
 * Read one field override for a given path.
 *
 * @param string $path  URL path key.
 * @param string $field SEO field name.
 * @return string|null
 */
function leadconnector_seo_get_field_for_path( string $path, string $field ): ?string {
	if ( ! in_array( $field, LEADCONNECTOR_SEO_FIELDS, true ) ) {
		return null;
	}
	$raw = get_option( leadconnector_seo_option_name( $path, $field ), '' );
	if ( ! is_string( $raw ) || '' === $raw ) {
		return null;
	}
	$val = leadconnector_seo_decode( $raw );
	return '' !== $val ? $val : null;
}

/** Gather all overrides for current path (with basic home fallback) */
function leadconnector_seo_get_overrides_for_current(): array {
	$path = leadconnector_seo_current_path_key();
	$vals = array();
	foreach ( LEADCONNECTOR_SEO_FIELDS as $f ) {
		$v = leadconnector_seo_get_field_for_path( $path, $f );
		if ( null !== $v && '' !== $v ) {
			$vals[ $f ] = $v;
		}
	}

	if ( empty( $vals ) && '' === $path ) {
		foreach ( array( 'home', '/' ) as $home_key ) {
			foreach ( LEADCONNECTOR_SEO_FIELDS as $f ) {
				$v = leadconnector_seo_get_field_for_path( $home_key, $f );
				if ( null !== $v && '' !== $v ) {
					$vals[ $f ] = $v;
				}
			}
			if ( ! empty( $vals ) ) {
				break;
			}
		}
	}
	return $vals;
}

/* -------------------- Title override -------------------- */
add_filter(
	'document_title_parts',
	function ( array $parts ) {
		$vals = leadconnector_seo_get_overrides_for_current();
		if ( ! empty( $vals['page_title'] ) ) {
			$parts['title'] = sanitize_text_field( $vals['page_title'] );
		}
		return $parts;
	},
	40
);

/* -------------------- Meta tags (description, keywords + social parity) -------------------- */
add_action(
	'wp_head',
	function () {
		$vals = leadconnector_seo_get_overrides_for_current();
		if ( empty( $vals ) ) {
			return;
		}

		$meta_allowed = array(
			'meta' => array(
				'name'     => true,
				'property' => true,
				'content'  => true,
			),
		);

		$tags = '';
		if ( ! empty( $vals['meta_description'] ) ) {
			$d     = $vals['meta_description'];
			$tags .= '<meta name="description" content="' . esc_attr( $d ) . '">' . "\n";
			$tags .= '<meta property="og:description" content="' . esc_attr( $d ) . '">' . "\n";
			$tags .= '<meta name="twitter:description" content="' . esc_attr( $d ) . '">' . "\n";
		}
		if ( ! empty( $vals['page_title'] ) ) {
			$t     = $vals['page_title'];
			$tags .= '<meta property="og:title" content="' . esc_attr( $t ) . '">' . "\n";
			$tags .= '<meta name="twitter:title" content="' . esc_attr( $t ) . '">' . "\n";
		}
		if ( ! empty( $vals['meta_keywords'] ) ) {
			$tags .= '<meta name="keywords" content="' . esc_attr( $vals['meta_keywords'] ) . '">' . "\n";
		}
		if ( '' !== $tags ) {
			echo wp_kses( "\n" . $tags, $meta_allowed );
		}
	},
	5
);
