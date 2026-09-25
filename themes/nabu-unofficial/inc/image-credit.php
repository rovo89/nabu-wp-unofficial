<?php
/**
 * Image credits, printed automatically.
 *
 * The credit ("Foto: NABU/F. Mustermann") is kept on the media file by the
 * companion plugin; the theme appends it to the caption of every image and
 * featured image, so it cannot be forgotten. Without the plugin there is no
 * credit to print.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read the credit of a media file.
 *
 * @param int $attachment_id Attachment ID.
 * @return string Empty string if none is stored.
 */
function nabu_get_image_credit( $attachment_id ) {
	if ( ! $attachment_id ) {
		return '';
	}
	return trim( (string) get_post_meta( (int) $attachment_id, '_nabu_image_credit', true ) );
}

/**
 * Append the credit to an existing caption or create one.
 *
 * @param string $html          Figure markup.
 * @param int    $attachment_id Attachment ID.
 * @return string
 */
function nabu_append_image_credit( $html, $attachment_id ) {
	$credit = nabu_get_image_credit( $attachment_id );

	if ( '' === $credit ) {
		return $html;
	}

	// Already named in the caption? Then do not duplicate.
	if ( false !== strpos( $html, $credit ) ) {
		return $html;
	}

	$markup = '<span class="nabu-credit">' . esc_html( $credit ) . '</span>';

	if ( false !== strpos( $html, '</figcaption>' ) ) {
		return (string) preg_replace(
			'#</figcaption>#',
			' ' . $markup . '</figcaption>',
			$html,
			1
		);
	}

	if ( false !== strpos( $html, '</figure>' ) ) {
		return (string) preg_replace(
			'#</figure>#',
			'<figcaption class="wp-element-caption nabu-credit">' . esc_html( $credit ) . '</figcaption></figure>',
			$html,
			1
		);
	}

	return $html;
}

/**
 * Add the credit to image and featured-image blocks.
 *
 * @param string $block_content Rendered markup.
 * @param array  $block         Block data.
 * @return string
 */
function nabu_render_image_credit( $block_content, $block ) {
	if ( empty( $block['blockName'] ) ) {
		return $block_content;
	}

	if ( 'core/image' === $block['blockName'] ) {
		$id = isset( $block['attrs']['id'] ) ? (int) $block['attrs']['id'] : 0;
		return nabu_append_image_credit( $block_content, $id );
	}

	// A linked featured image is a card thumbnail; the credit stands on the post.
	if ( 'core/post-featured-image' === $block['blockName'] && empty( $block['attrs']['isLink'] ) ) {
		return nabu_append_image_credit( $block_content, (int) get_post_thumbnail_id() );
	}

	return $block_content;
}
add_filter( 'render_block', 'nabu_render_image_credit', 10, 2 );
