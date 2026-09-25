<?php
/**
 * The event post type (German UI: "Termin").
 *
 * Events are the one kind of content that needs its own type: they carry a
 * start and end, a location and a meeting point, schema.org Event markup,
 * and they sort by date (inc/event-meta.php). Everything else — activities,
 * protected areas, excursions — is a page with child pages, ordered and
 * nested in the page tree where the editors maintain the site anyway.
 *
 * The public address is German and configurable in the group settings
 * ("termine" by default); single events add their year to it
 * (inc/event-permalinks.php).
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the event post type.
 */
function nabu_content_register_post_types() {
	$slug = nabu_group_get( 'events_slug' );

	register_post_type(
		'nabu_event',
		array(
			'labels'        => array(
				'name'               => __( 'Termine', 'nabu-unofficial-content' ),
				'singular_name'      => __( 'Termin', 'nabu-unofficial-content' ),
				'add_new'            => __( 'Neuen Termin anlegen', 'nabu-unofficial-content' ),
				'add_new_item'       => __( 'Neuen Termin anlegen', 'nabu-unofficial-content' ),
				'edit_item'          => __( 'Termin bearbeiten', 'nabu-unofficial-content' ),
				'search_items'       => __( 'Termine durchsuchen', 'nabu-unofficial-content' ),
				'not_found'          => __( 'Keine Termine gefunden.', 'nabu-unofficial-content' ),
				'not_found_in_trash' => __( 'Keine Termine im Papierkorb.', 'nabu-unofficial-content' ),
				'all_items'          => __( 'Alle Termine', 'nabu-unofficial-content' ),
			),
			'description'   => __( 'Exkursionen, Pflegeeinsätze und Treffen der Gruppe.', 'nabu-unofficial-content' ),
			'public'        => true,
			'has_archive'   => $slug,
			'rewrite'       => array(
				'slug'       => $slug,
				'with_front' => false,
			),
			'menu_icon'     => 'dashicons-calendar-alt',
			'menu_position' => 21,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'show_in_rest'  => true,
			'rest_base'     => 'events',
		)
	);
}
add_action( 'init', 'nabu_content_register_post_types' );
