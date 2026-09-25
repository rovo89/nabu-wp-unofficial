<?php
/**
 * Mark the current section in the header navigation.
 *
 * Core marks a navigation link as current only when it points at a page by
 * ID and that very page is on screen. That misses a typed address such as
 * /termine/, a link whose address comes from a binding, and the section a
 * page belongs to: a page below "Über uns", a single event below "Termine".
 * Comparing paths covers all of them: a link is current when its path is the
 * path on screen, and marks its section when the path on screen lies below.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Path of a URL relative to the site root, with a trailing slash.
 *
 * @param string $url Absolute or root-relative URL.
 * @return string|null Null for other hosts and for anything unreadable.
 */
function nabu_nav_path( $url ) {
	$parts = wp_parse_url( $url );
	if ( false === $parts ) {
		return null;
	}

	if ( ! empty( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
		return null;
	}

	$path = $parts['path'] ?? '/';
	$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

	if ( '' !== $base && '/' !== $base && 0 === strpos( $path, $base ) ) {
		$path = '/' . substr( $path, strlen( $base ) );
	}

	return trailingslashit( '/' . ltrim( rawurldecode( $path ), '/' ) );
}

/**
 * Add the current marker to navigation links that match the page on screen.
 *
 * @param string $block_content Rendered markup.
 * @param array  $block         Block data.
 * @return string
 */
function nabu_nav_mark_current( $block_content, $block ) {
	if ( ! in_array( $block['blockName'] ?? '', array( 'core/navigation-link', 'core/navigation-submenu' ), true ) || is_admin() || wp_is_serving_rest_request() ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $processor->next_tag( 'li' ) ) {
		return $block_content;
	}
	$processor->set_bookmark( 'item' );
	if ( ! $processor->next_tag( 'a' ) ) {
		return $block_content;
	}

	$href = trim( (string) $processor->get_attribute( 'href' ) );
	if ( '' === $href || '#' === $href[0] ) {
		return $block_content;
	}

	$link = nabu_nav_path( $href );
	$here = nabu_nav_path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared, never printed.

	if ( null === $link || null === $here ) {
		return $block_content;
	}

	$exact   = $link === $here;
	$section = ! $exact && '/' !== $link && 0 === strpos( $here, $link );

	if ( ! $exact && ! $section ) {
		return $block_content;
	}

	// "page" for the page itself, "true" for the section it belongs to.
	$processor->set_attribute( 'aria-current', $exact ? 'page' : 'true' );
	$processor->seek( 'item' );
	$processor->add_class( 'current-menu-item' );
	$processor->release_bookmark( 'item' );

	return $processor->get_updated_html();
}
add_filter( 'render_block', 'nabu_nav_mark_current', 10, 2 );
