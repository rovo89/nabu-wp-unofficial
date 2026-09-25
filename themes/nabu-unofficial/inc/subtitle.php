<?php
/**
 * A post's subtitle (meta box "Unterüberschrift" of the companion plugin),
 * printed under the post title in the theme's subtitle style.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Insert the subtitle after the title of the post on screen — not after the
 * linked titles of cards, such as in "Weitere Meldungen".
 *
 * @param string $block_content Rendered markup.
 * @param array  $block         Block data.
 * @return string
 */
function nabu_render_subtitle( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/post-title' !== $block['blockName'] ) {
		return $block_content;
	}

	if ( ! empty( $block['attrs']['isLink'] ) || ! is_singular( 'post' ) ) {
		return $block_content;
	}

	$subtitle = trim( (string) get_post_meta( get_the_ID(), '_nabu_subtitle', true ) );

	if ( '' === $subtitle ) {
		return $block_content;
	}

	return $block_content . '<p class="is-style-nabu-subtitle has-lead-font-size">' . esc_html( $subtitle ) . '</p>';
}
add_filter( 'render_block', 'nabu_render_subtitle', 10, 2 );
