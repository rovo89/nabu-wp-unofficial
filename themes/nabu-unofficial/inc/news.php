<?php
/**
 * Posts on screen and in cards.
 *
 * The date line of a post and of its cards names its topic, the category:
 * "14. August 2026 · Biotoppflege" — unless the list is that topic's anyway.
 * The featured image heads the article unless the post opts out, and wherever
 * it is cropped, its chosen point stays in view.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The query loops being rendered, innermost last: for each, the one category
 * it is limited to, or 0.
 *
 * @param string $action 'enter', 'topic' or 'leave'; empty reads.
 * @param int    $topic  Category for 'topic'.
 * @return int[]
 */
function nabu_loops( $action = '', $topic = 0 ) {
	static $loops = array();

	if ( 'enter' === $action ) {
		$loops[] = 0;
	} elseif ( 'topic' === $action && $loops ) {
		$loops[ count( $loops ) - 1 ] = (int) $topic;
	} elseif ( 'leave' === $action ) {
		array_pop( $loops );
	}

	return $loops;
}

/**
 * Enter a loop: its post template is about to render the posts. The block
 * context of a card does not tell, it carries only the post.
 *
 * @param string|null $pre_render   Short-circuit markup.
 * @param array       $parsed_block Block data.
 * @return string|null
 */
function nabu_enter_loop( $pre_render, $parsed_block ) {
	if ( null === $pre_render && 'core/post-template' === ( $parsed_block['blockName'] ?? '' ) ) {
		nabu_loops( 'enter' );
	}
	return $pre_render;
}
// Last, so no other filter replaces the loop after it was entered.
add_filter( 'pre_render_block', 'nabu_enter_loop', PHP_INT_MAX, 2 );

/**
 * Note the one category a loop asks for. An inheriting loop, such as a
 * category archive, has no query of its own and never gets here.
 *
 * @param array $query Query vars.
 * @return array
 */
function nabu_note_loop_topic( $query ) {
	foreach ( (array) ( $query['tax_query'] ?? array() ) as $clause ) {
		if ( is_array( $clause ) && 'category' === ( $clause['taxonomy'] ?? '' ) && 'IN' === ( $clause['operator'] ?? 'IN' ) && 1 === count( (array) $clause['terms'] ) ) {
			nabu_loops( 'topic', (int) current( (array) $clause['terms'] ) );
		}
	}
	return $query;
}
add_filter( 'query_loop_block_query_vars', 'nabu_note_loop_topic', 20 );

/**
 * Leave a loop after its posts.
 *
 * @param string $block_content Rendered markup.
 * @return string
 */
function nabu_leave_loop( $block_content ) {
	nabu_loops( 'leave' );
	return $block_content;
}
add_filter( 'render_block_core/post-template', 'nabu_leave_loop' );

/**
 * The topic to name with the date: the post's first category, not the
 * default one and not the one the list is about.
 *
 * @param int $post_id Post ID.
 * @return WP_Term|null
 */
function nabu_post_topic( $post_id ) {
	$terms = get_the_terms( $post_id, 'category' );

	if ( ! is_array( $terms ) ) {
		return null;
	}

	$loops   = nabu_loops();
	$implied = array( (int) get_option( 'default_category' ), (int) end( $loops ) );
	if ( is_category() ) {
		$implied[] = get_queried_object_id();
	}

	foreach ( $terms as $term ) {
		if ( ! in_array( (int) $term->term_id, $implied, true ) ) {
			return $term;
		}
	}

	return null;
}

/**
 * The dot between the parts of a date line, with room of its own.
 *
 * @return string
 */
function nabu_kicker_separator() {
	return ' <span class="nabu-kicker__separator">·</span> ';
}

/**
 * Put the topic after the date: in a card as text, since the whole card is
 * the link, above the article as a link to the topic's posts.
 *
 * @param string   $block_content Rendered markup.
 * @param array    $block         Block data.
 * @param WP_Block $instance      Block instance with context.
 * @return string
 */
function nabu_date_line_topic( $block_content, $block, $instance ) {
	$post_id = (int) ( $instance->context['postId'] ?? 0 );

	if ( ! $post_id || '' === $block_content || 'post' !== get_post_type( $post_id ) ) {
		return $block_content;
	}

	$topic = nabu_post_topic( $post_id );
	if ( ! $topic ) {
		return $block_content;
	}

	$name = nabu_loops()
		? esc_html( $topic->name )
		: '<a href="' . esc_url( get_term_link( $topic ) ) . '">' . esc_html( $topic->name ) . '</a>';

	return (string) preg_replace( '#(</[a-z0-9]+>\s*)$#i', nabu_kicker_separator() . $name . '$1', $block_content, 1 );
}
add_filter( 'render_block_core/post-date', 'nabu_date_line_topic', 20, 3 );

/**
 * Leave out the featured image above the text where the post says so (plugin
 * field "Über dem Text zeigen"), for instance when a gallery shows the same
 * photo. Cards keep it.
 *
 * @param string   $block_content Rendered markup.
 * @param array    $block         Block data.
 * @param WP_Block $instance      Block instance with context.
 * @return string
 */
function nabu_featured_image_hidden( $block_content, $block, $instance ) {
	if ( ! empty( $block['attrs']['isLink'] ) || nabu_loops() || ! function_exists( 'nabu_image_hidden' ) ) {
		return $block_content;
	}

	return nabu_image_hidden( (int) ( $instance->context['postId'] ?? get_the_ID() ) ) ? '' : $block_content;
}
add_filter( 'render_block_core/post-featured-image', 'nabu_featured_image_hidden', 10, 3 );

/**
 * Keep the chosen point of a featured image in view where it is cropped
 * (plugin field "Bildausschnitt").
 *
 * @param string $html    Image markup.
 * @param int    $post_id Post ID.
 * @return string
 */
function nabu_featured_image_focus( $html, $post_id ) {
	$focus = function_exists( 'nabu_image_focus' ) ? nabu_image_focus( $post_id ) : '';

	if ( '' === $focus || '' === $html ) {
		return $html;
	}

	$tags = new WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( 'img' ) ) {
		$style = trim( (string) $tags->get_attribute( 'style' ) );
		$tags->set_attribute( 'style', ( '' === $style ? '' : rtrim( $style, ';' ) . ';' ) . 'object-position:' . $focus . ';' );
	}

	return $tags->get_updated_html();
}
add_filter( 'post_thumbnail_html', 'nabu_featured_image_focus', 10, 2 );
