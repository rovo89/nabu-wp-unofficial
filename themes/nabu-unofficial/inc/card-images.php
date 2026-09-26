<?php
/**
 * Card images: the featured image cut to the card's 16:9, around its focus.
 *
 * Cards show their image at 16:9 whatever the photo's format (style.css).
 * WordPress's sizes keep the photo's proportions, so a portrait photo arrives
 * more than twice as tall as the card shows it, and the steps of its srcset
 * (300, 768, 1024, 1536) fit the card's widths badly. So a card gets 16:9 cuts
 * of its own, taken around the focus point of the plugin field
 * "Bildausschnitt" the way object-position would show it, and a sizes
 * attribute that follows the grid instead of assuming the full window.
 *
 * The cuts are made the first time a card needs them, and when a post is
 * saved. They lie beside the upload; the attachment lists them per focus
 * point and takes them along when it is deleted. Cuts for a focus point no
 * post uses any more are removed when the next ones are made.
 *
 * A linked featured image counts as a card (see also image-credit.php); the
 * theme uses the unlinked one only above the text.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widths of the cuts. A card is at most about 300px wide next to others and
 * about 360px alone on a phone: 400 covers the first at single density, 640
 * at double, 960 a phone at two and three times. A 1280 step for the third
 * would cost phones three times the bytes of 960 for no visible gain.
 */
const NABU_CARD_WIDTHS = array( 400, 640, 960 );

/**
 * Width of a card, from the grid in style.css: minmax(280px, 1fr) in at most
 * 940px, with the page's side padding of clamp(1rem, 4vw, 2.5rem). Three
 * columns from 965px of window, two from 635px, one below. The event cards
 * (minmax(240px)) and the cards beside the sidebar are narrower at the same
 * window width; a slightly high estimate only costs one step.
 */
const NABU_CARD_SIZES = '(min-width: 965px) 300px, (min-width: 635px) 45vw, calc(100vw - 2rem)';

/**
 * Width of a small card (.nabu-grid--small, minmax(190px, 1fr)): four
 * columns from 891px, three from 663px, two from 435px.
 */
const NABU_CARD_SIZES_SMALL = '(min-width: 891px) 220px, (min-width: 663px) 33vw, (min-width: 435px) 50vw, calc(100vw - 2rem)';

/**
 * Focus point of a post's featured image, in whole percent.
 *
 * @param int $post_id Post ID.
 * @return array{0:int, 1:int} Horizontal and vertical; 50/50 without a focus.
 */
function nabu_card_focus( $post_id ) {
	$focus = function_exists( 'nabu_image_focus' ) ? nabu_image_focus( $post_id ) : '';

	if ( '' === $focus || 2 !== sscanf( $focus, '%d%% %d%%', $x, $y ) ) {
		return array( 50, 50 );
	}

	return array( (int) $x, (int) $y );
}

/**
 * The 16:9 cuts of an image around a focus point, made if missing.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $x             Focus, horizontal, in percent.
 * @param int $y             Focus, vertical, in percent.
 * @return array<int, array{url:string, width:int, height:int}> Narrowest first; empty where the image cannot be cut (SVG, missing file).
 */
function nabu_card_crops( $attachment_id, $x = 50, $y = 50 ) {
	$file = (string) get_attached_file( $attachment_id );
	$mime = (string) get_post_mime_type( $attachment_id );

	if ( '' === $file || ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) || ! is_readable( $file ) ) {
		return array();
	}

	$key    = $x . '-' . $y;
	$stored = get_post_meta( $attachment_id, '_nabu_card_crops', true );

	// An edited image (rotated, cropped in the media library) has a new file;
	// the cuts of the old one are of no more use.
	if ( ! is_array( $stored ) || ( $stored['source'] ?? '' ) !== basename( $file ) ) {
		nabu_card_crops_delete( $attachment_id );
		$stored = array(
			'source' => basename( $file ),
			'crops'  => array(),
		);
	}

	$dir  = dirname( $file );
	$crop = $stored['crops'][ $key ] ?? array();
	$have = $crop && array_reduce( $crop, static fn( $ok, $c ) => $ok && file_exists( $dir . '/' . $c['file'] ), true );

	if ( ! $have ) {
		// A cut that failed (an image too large for the memory, say) is not
		// tried again on every page view; the card keeps WordPress's sizes.
		$failed = 'nabu_card_crop_failed_' . $attachment_id . '_' . $key;
		if ( get_transient( $failed ) ) {
			return array();
		}

		$crop = nabu_card_crops_make( $file, $x, $y );
		if ( ! $crop ) {
			set_transient( $failed, 1, DAY_IN_SECONDS );
			return array();
		}

		// Keep the cuts for the focus points still in use, drop the rest.
		$used = array( $key => true );
		foreach ( nabu_card_crops_users( $attachment_id ) as $post_id ) {
			$used[ implode( '-', nabu_card_focus( $post_id ) ) ] = true;
		}
		foreach ( $stored['crops'] as $old => $files ) {
			if ( ! isset( $used[ $old ] ) ) {
				foreach ( $files as $c ) {
					wp_delete_file( $dir . '/' . $c['file'] );
				}
				unset( $stored['crops'][ $old ] );
			}
		}

		$stored['crops'][ $key ] = $crop;
		update_post_meta( $attachment_id, '_nabu_card_crops', $stored );
	}

	$base = trailingslashit( dirname( (string) wp_get_attachment_url( $attachment_id ) ) );

	return array_map(
		static fn( $c ) => array(
			'url'    => $base . $c['file'],
			'width'  => (int) $c['width'],
			'height' => (int) $c['height'],
		),
		$crop
	);
}

/**
 * Cut an image file to 16:9 in the card widths.
 *
 * The window sits where object-position at the focus would put it, so a card
 * shows the same part of the photo as the article head.
 *
 * @param string $file Image file.
 * @param int    $x    Focus, horizontal, in percent.
 * @param int    $y    Focus, vertical, in percent.
 * @return array<int, array{file:string, width:int, height:int}> Narrowest first; empty on failure.
 */
function nabu_card_crops_make( $file, $x, $y ) {
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return array();
	}
	$editor->maybe_exif_rotate();

	$size = $editor->get_size();
	$w    = (int) $size['width'];
	$h    = (int) $size['height'];
	if ( ! $w || ! $h ) {
		return array();
	}

	if ( $w * 9 > $h * 16 ) {
		$cut_w = (int) round( $h * 16 / 9 );
		$cut_h = $h;
	} else {
		$cut_w = $w;
		$cut_h = (int) round( $w * 9 / 16 );
	}
	$cut_x = (int) round( ( $w - $cut_w ) * $x / 100 );
	$cut_y = (int) round( ( $h - $cut_h ) * $y / 100 );

	// No enlargement: a small photo gets one cut at its own width.
	$widths = array_values( array_filter( NABU_CARD_WIDTHS, static fn( $width ) => $width <= $cut_w ) );
	if ( ! $widths ) {
		$widths = array( $cut_w );
	}
	rsort( $widths );

	// Named up front: after a save the editor derives names from the new file.
	$names = array();
	foreach ( $widths as $width ) {
		$names[ $width ] = $editor->generate_filename( sprintf( 'card-%dx%d-%d-%d', $width, (int) round( $width * 9 / 16 ), $x, $y ) );
	}

	$made = array();
	foreach ( $widths as $i => $width ) {
		$height = (int) round( $width * 9 / 16 );
		$result = 0 === $i
			? $editor->crop( $cut_x, $cut_y, $cut_w, $cut_h, $width, $height )
			: $editor->resize( $width, $height, true );
		if ( is_wp_error( $result ) ) {
			break;
		}

		$saved = $editor->save( $names[ $width ] );
		if ( is_wp_error( $saved ) ) {
			break;
		}

		$made[] = array(
			'file'   => $saved['file'],
			'width'  => (int) $saved['width'],
			'height' => (int) $saved['height'],
		);
	}

	return array_reverse( $made );
}

/**
 * Posts that show an attachment as their featured image.
 *
 * @param int $attachment_id Attachment ID.
 * @return int[]
 */
function nabu_card_crops_users( $attachment_id ) {
	return get_posts(
		array(
			'post_type'      => array_filter( get_post_types_by_support( 'thumbnail' ), 'post_type_exists' ),
			'post_status'    => 'any',
			'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => (string) $attachment_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);
}

/**
 * Delete all cuts of an attachment, with it or when its file changed.
 *
 * @param int $attachment_id Attachment ID.
 */
function nabu_card_crops_delete( $attachment_id ) {
	$stored = get_post_meta( $attachment_id, '_nabu_card_crops', true );
	$file   = (string) get_attached_file( $attachment_id );

	if ( is_array( $stored ) && '' !== $file ) {
		foreach ( (array) ( $stored['crops'] ?? array() ) as $files ) {
			foreach ( $files as $c ) {
				wp_delete_file( dirname( $file ) . '/' . $c['file'] );
			}
		}
	}

	delete_post_meta( $attachment_id, '_nabu_card_crops' );
}
add_action( 'delete_attachment', 'nabu_card_crops_delete' );

/**
 * Put the cuts and the grid's sizes on a card image.
 *
 * Without cuts (SVG, or the cut failed) the image keeps its own srcset and
 * only gets the sizes. An "auto" that WordPress put first for a lazy image
 * stays: browsers that know it measure the card themselves.
 *
 * @param string $html    Markup containing the image.
 * @param int    $post_id Post whose featured image it is.
 * @param string $sizes   Sizes attribute for the grid the card sits in.
 * @return string
 */
function nabu_card_image( $html, $post_id, $sizes = NABU_CARD_SIZES ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag( 'img' ) ) {
		return $html;
	}

	$crops = nabu_card_crops( (int) get_post_thumbnail_id( $post_id ), ...nabu_card_focus( $post_id ) );

	if ( $crops ) {
		// The src for browsers without srcset: the 640 cut, or the widest there is.
		$src = $crops[ min( 1, count( $crops ) - 1 ) ];
		$tags->set_attribute( 'src', $src['url'] );
		$tags->set_attribute( 'width', (string) $src['width'] );
		$tags->set_attribute( 'height', (string) $src['height'] );
		$tags->set_attribute( 'srcset', implode( ', ', array_map( static fn( $c ) => $c['url'] . ' ' . $c['width'] . 'w', $crops ) ) );
	}

	if ( null !== $tags->get_attribute( 'srcset' ) ) {
		$auto = str_starts_with( ltrim( (string) $tags->get_attribute( 'sizes' ) ), 'auto' );
		$tags->set_attribute( 'sizes', ( $auto ? 'auto, ' : '' ) . $sizes );
	}

	return $tags->get_updated_html();
}

/**
 * Card images from the featured-image block.
 *
 * @param string   $block_content Rendered markup.
 * @param array    $block         Block data.
 * @param WP_Block $instance      Block instance with context.
 * @return string
 */
function nabu_card_featured_image( $block_content, $block, $instance ) {
	if ( '' === $block_content || empty( $block['attrs']['isLink'] ) ) {
		return $block_content;
	}

	return nabu_card_image( $block_content, (int) ( $instance->context['postId'] ?? get_the_ID() ) );
}
add_filter( 'render_block_core/post-featured-image', 'nabu_card_featured_image', 10, 3 );

/**
 * Make the cuts when a post is saved, so the first visitor does not wait for
 * them.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function nabu_card_crops_on_save( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! has_post_thumbnail( $post ) ) {
		return;
	}

	nabu_card_crops( (int) get_post_thumbnail_id( $post ), ...nabu_card_focus( $post_id ) );
}
add_action( 'wp_after_insert_post', 'nabu_card_crops_on_save', 10, 2 );
