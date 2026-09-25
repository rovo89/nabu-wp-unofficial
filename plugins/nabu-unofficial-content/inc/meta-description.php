<?php
/**
 * Description for search engines (<meta name="description">).
 *
 * Without one, a search engine picks some text off the page — on this theme
 * often the navigation or a date. The source is what the editors already
 * write: the excerpt ("Textauszug") where one is set, otherwise the opening
 * paragraphs. Pages get the excerpt field for that, so an editor can word
 * the description of any page by hand.
 *
 * - Front page: its excerpt, else "<group name> – <tagline>". Its content is
 *   layout (hero, query loops), not prose.
 * - Posts, events, pages: excerpt, else the first paragraphs.
 * - Posts page and events overview: excerpt or text of the page standing in
 *   for them (Settings → Reading).
 * - Anything else (search, 404, archives): no description.
 *
 * An SEO plugin writes its own; then this stays silent.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pages get the excerpt field.
 */
function nabu_meta_description_page_excerpt() {
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'nabu_meta_description_page_excerpt' );

/**
 * Plain text of the paragraphs and list items in some block content.
 *
 * Headings are left out — "Angaben gemäß § 5 DDG" is no description — and
 * blocks are rendered, so a paragraph bound to the group data contributes
 * its value.
 *
 * @param array $blocks Parsed blocks.
 * @param int   $limit  Stop once this many characters are collected.
 * @return string
 */
function nabu_meta_description_text( $blocks, $limit ) {
	$text = '';

	foreach ( $blocks as $block ) {
		if ( mb_strlen( $text ) >= $limit ) {
			break;
		}

		if ( in_array( $block['blockName'], array( 'core/paragraph', 'core/list' ), true ) ) {
			$html  = str_ireplace( array( '<br>', '<br/>', '<br />', '</li>' ), ' ', render_block( $block ) );
			$text .= ' ' . wp_strip_all_tags( $html );
		} elseif ( in_array( $block['blockName'], array( 'core/group', 'core/columns', 'core/column' ), true ) ) {
			$text .= ' ' . nabu_meta_description_text( $block['innerBlocks'], $limit - mb_strlen( $text ) );
		}
	}

	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
}

/**
 * Shorten to about 155 characters, at a word boundary.
 *
 * @param string $text Plain text.
 * @return string
 */
function nabu_meta_description_trim( $text ) {
	$max = 155;

	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}

	$cut   = mb_substr( $text, 0, $max );
	$space = mb_strrpos( $cut, ' ' );

	return rtrim( false === $space ? $cut : mb_substr( $cut, 0, $space ), ' ,;:–-' ) . ' …';
}

/**
 * Description of one post or page.
 *
 * @param WP_Post $post      The post.
 * @param bool    $from_text Fall back to the opening paragraphs.
 * @return string
 */
function nabu_meta_description_of( $post, $from_text = true ) {
	if ( has_excerpt( $post ) ) {
		return nabu_meta_description_trim( trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $post->post_excerpt ) ) ) );
	}

	if ( ! $from_text || post_password_required( $post ) ) {
		return '';
	}

	return nabu_meta_description_trim( nabu_meta_description_text( parse_blocks( $post->post_content ), 400 ) );
}

/**
 * Print the description.
 */
function nabu_meta_description() {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
		return;
	}

	$description = '';

	if ( is_front_page() ) {
		$front = is_page() ? get_queried_object() : null;
		if ( $front instanceof WP_Post ) {
			$description = nabu_meta_description_of( $front, false );
		}
		if ( '' === $description ) {
			$tagline     = get_bloginfo( 'description' );
			$description = $tagline ? nabu_group_get( 'name' ) . ' – ' . $tagline : '';
		}
	} elseif ( is_singular() ) {
		$description = nabu_meta_description_of( get_queried_object() );
	} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
		$description = nabu_meta_description_of( get_post( (int) get_option( 'page_for_posts' ) ) );
	} elseif ( is_post_type_archive( 'nabu_event' ) && nabu_events_page_id() ) {
		$description = nabu_meta_description_of( get_post( nabu_events_page_id() ) );
	}

	/**
	 * Filters the description. Empty prints nothing.
	 *
	 * @param string $description Plain text.
	 */
	$description = (string) apply_filters( 'nabu_meta_description', $description );

	// Same typography as on the page: " - " becomes a dash, quotes curl.
	if ( '' !== $description ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( wptexturize( $description ) ) );
	}
}
add_action( 'wp_head', 'nabu_meta_description', 1 );
