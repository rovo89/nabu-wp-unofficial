<?php
/**
 * The page for events ("Terminseite").
 *
 * The events overview is the archive of `nabu_event`, not a page: the link
 * picker cannot find it and the page tree has no place for it. WordPress has
 * the same problem with posts and solves it with the posts page; this is the
 * same for events, set right beside it under Settings → Reading. The chosen
 * page
 *
 * - links to the archive wherever WordPress builds its address — link
 *   picker, page tree, sidebar navigation, breadcrumbs;
 * - places the events in the page tree where it hangs, e.g. below
 *   "Mitmachen" (theme: inc/section-anchors.php);
 * - supplies heading and introduction of the overview (theme block
 *   nabu/section-intro).
 *
 * Its own address redirects to the archive.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ID of the page for events, 0 without (or when it is not published).
 *
 * @return int
 */
function nabu_events_page_id() {
	$id = (int) get_option( 'nabu_page_for_events', 0 );

	if ( ! $id || 'page' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
		return 0;
	}

	return $id;
}

/**
 * Setting under Settings → Reading, right beside the posts page.
 */
function nabu_events_page_register_setting() {
	register_setting(
		'reading',
		'nabu_page_for_events',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		)
	);

	add_settings_field(
		'nabu_page_for_events',
		__( 'Terminseite', 'nabu-unofficial-content' ),
		'nabu_events_page_render_field',
		'reading',
		'default',
		array( 'label_for' => 'nabu_page_for_events' )
	);
}
add_action( 'admin_init', 'nabu_events_page_register_setting' );

/**
 * Render the page dropdown.
 */
function nabu_events_page_render_field() {
	wp_dropdown_pages(
		array(
			'name'              => 'nabu_page_for_events',
			'id'                => 'nabu_page_for_events',
			'selected'          => (int) get_option( 'nabu_page_for_events', 0 ),
			'show_option_none'  => __( '— Auswählen —', 'nabu-unofficial-content' ),
			'option_none_value' => '0',
		)
	);
	echo '<p class="description">' . esc_html__( 'Die Seite steht für die Terminübersicht, wie die Beitragsseite für „Aktuelles“: Im Menü und im Seitenbaum führt sie zu den Terminen, ihr Titel und Text stehen über der Terminliste.', 'nabu-unofficial-content' ) . '</p>';
}

/**
 * The page links to the archive.
 *
 * @param string $link    Permalink.
 * @param int    $post_id Page ID.
 * @return string
 */
function nabu_events_page_link( $link, $post_id ) {
	if ( (int) $post_id !== nabu_events_page_id() ) {
		return $link;
	}

	$archive = get_post_type_archive_link( 'nabu_event' );

	return $archive ? $archive : $link;
}
add_filter( 'page_link', 'nabu_events_page_link', 10, 2 );

/**
 * The page's own address leads to the archive.
 */
function nabu_events_page_redirect() {
	$id = nabu_events_page_id();

	if ( ! $id || ! is_page( $id ) ) {
		return;
	}

	$archive = get_post_type_archive_link( 'nabu_event' );
	if ( $archive ) {
		wp_safe_redirect( $archive, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'nabu_events_page_redirect' );

/**
 * Label the page in the page list, as WordPress does for "Beitragsseite".
 *
 * @param string[] $states Post states.
 * @param WP_Post  $post   Post.
 * @return string[]
 */
function nabu_events_page_state( $states, $post ) {
	if ( (int) $post->ID === nabu_events_page_id() ) {
		$states['nabu_page_for_events'] = __( 'Terminseite', 'nabu-unofficial-content' );
	}

	return $states;
}
add_filter( 'display_post_states', 'nabu_events_page_state', 10, 2 );

/**
 * Hand the page to the theme as the anchor of the events section.
 *
 * @param array<string, int> $pages Post type => page ID.
 * @return array<string, int>
 */
function nabu_events_page_section( $pages ) {
	$id = nabu_events_page_id();

	if ( $id ) {
		$pages['nabu_event'] = $id;
	}

	return $pages;
}
add_filter( 'nabu_section_pages', 'nabu_events_page_section' );
