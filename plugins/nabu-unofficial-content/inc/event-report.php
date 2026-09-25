<?php
/**
 * Reports on events.
 *
 * An event has one URL for its whole life: before the day it is the
 * announcement, afterwards it carries the report. The report is still an
 * ordinary post — written like any other, with its own date, listed under
 * "Aktuelles" — it only names the event it belongs to. Its link then leads to
 * the event page, which shows the report above the announcement (the theme
 * takes care of that part).
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the link from a post to its event.
 */
function nabu_report_register_meta() {
	register_post_meta(
		'post',
		'_nabu_report_event',
		array(
			'type'          => 'integer',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => 0,
			'auth_callback' => static function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'nabu_report_register_meta' );

/**
 * The event a post reports on, if that event is published.
 *
 * An unpublished or deleted event makes the post an ordinary post again, with
 * its own URL — nothing has to be tidied up by hand.
 *
 * @param int|WP_Post $post Post.
 * @return WP_Post|null
 */
function nabu_report_event( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'post' !== $post->post_type ) {
		return null;
	}

	$event_id = (int) get_post_meta( $post->ID, '_nabu_report_event', true );
	$event    = $event_id ? get_post( $event_id ) : null;

	if ( ! $event || 'nabu_event' !== $event->post_type || 'publish' !== $event->post_status ) {
		return null;
	}

	return $event;
}

// The theme ships a fallback that returns nothing; see nabu_event_timestamp().
if ( ! function_exists( 'nabu_event_reports' ) ) {
	/**
	 * Published reports on an event, oldest first.
	 *
	 * @param int $event_id Event ID.
	 * @return WP_Post[]
	 */
	function nabu_event_reports( $event_id ) {
		if ( ! $event_id ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'orderby'          => 'date',
				'order'            => 'ASC',
				'meta_key'         => '_nabu_report_event', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => (int) $event_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => false,
			)
		);
	}
}

/**
 * Published reports mapped to their published events, one query per request.
 *
 * @return array<int, int> Report ID => event ID.
 */
function nabu_report_map() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.post_id AS report, e.ID AS event
			FROM {$wpdb->postmeta} pm
			JOIN {$wpdb->posts} r ON r.ID = pm.post_id
			JOIN {$wpdb->posts} e ON e.ID = pm.meta_value
			WHERE pm.meta_key = %s
				AND r.post_type = 'post' AND r.post_status = 'publish'
				AND e.post_type = 'nabu_event' AND e.post_status = 'publish'",
			'_nabu_report_event'
		)
	);

	$map = array();
	foreach ( (array) $rows as $row ) {
		$map[ (int) $row->report ] = (int) $row->event;
	}

	return $map;
}

/* ------------------------------------------------------------------ *
 * Editing: "Bericht zu Termin" on the post, "Bericht" on the event
 * ------------------------------------------------------------------ */

/**
 * Register the meta boxes.
 */
function nabu_report_meta_boxes() {
	add_meta_box(
		'nabu-report-event',
		__( 'Bericht zu Termin', 'nabu-unofficial-content' ),
		'nabu_report_meta_box_render',
		'post',
		'side',
		'default',
		array( '__block_editor_compatible_meta_box' => true )
	);
	add_meta_box(
		'nabu-event-report',
		__( 'Bericht', 'nabu-unofficial-content' ),
		'nabu_event_report_box_render',
		'nabu_event',
		'side',
		'default',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes', 'nabu_report_meta_boxes' );

/**
 * Dropdown of events on the post.
 *
 * @param WP_Post $post Current post.
 */
function nabu_report_meta_box_render( $post ) {
	wp_nonce_field( 'nabu_report_save', 'nabu_report_nonce' );

	$current = (int) get_post_meta( $post->ID, '_nabu_report_event', true );

	// "Bericht zu diesem Termin schreiben" on the event opens a new post with
	// the event already chosen.
	if ( ! $current && 'auto-draft' === $post->post_status && isset( $_GET['nabu_report_for'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = absint( $_GET['nabu_report_for'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$events = get_posts(
		array(
			'post_type'      => 'nabu_event',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => 200,
			'meta_key'       => '_nabu_event_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
		)
	);

	echo '<p style="margin-top:0"><select name="nabu_report_event" id="nabu-report-event-select" aria-label="' . esc_attr__( 'Termin', 'nabu-unofficial-content' ) . '" style="width:100%;max-width:100%;box-sizing:border-box">';
	printf( '<option value="0">%s</option>', esc_html__( '— kein Termin —', 'nabu-unofficial-content' ) );
	foreach ( $events as $event ) {
		$ts = nabu_event_timestamp( (string) get_post_meta( $event->ID, '_nabu_event_start', true ) );
		printf(
			'<option value="%1$d" %2$s>%3$s</option>',
			(int) $event->ID,
			selected( $current, $event->ID, false ),
			esc_html( ( $ts ? wp_date( 'd.m.Y', $ts ) . ' – ' : '' ) . get_the_title( $event ) )
		);
	}
	echo '</select></p>';

	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Ist dieser Beitrag ein Bericht über einen Termin? Dann erscheint er auf der Seite des Termins über der Ankündigung, und alle Links führen dorthin.', 'nabu-unofficial-content' )
	);
}

/**
 * Save the dropdown.
 *
 * @param int $post_id Post ID.
 */
function nabu_report_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['nabu_report_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nabu_report_nonce'] ) ), 'nabu_report_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$event_id = isset( $_POST['nabu_report_event'] ) ? absint( $_POST['nabu_report_event'] ) : 0;

	if ( $event_id && 'nabu_event' === get_post_type( $event_id ) ) {
		update_post_meta( $post_id, '_nabu_report_event', $event_id );
	} else {
		delete_post_meta( $post_id, '_nabu_report_event' );
	}
}
add_action( 'save_post_post', 'nabu_report_meta_box_save' );

/**
 * The reports on the event, or a link to write one.
 *
 * @param WP_Post $post Current event.
 */
function nabu_event_report_box_render( $post ) {
	$reports = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => -1,
			'meta_key'       => '_nabu_report_event', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => (int) $post->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	if ( $reports ) {
		echo '<ul style="margin-top:0">';
		foreach ( $reports as $report ) {
			$status = 'publish' === $report->post_status ? '' : ' (' . get_post_status_object( $report->post_status )->label . ')';
			printf(
				'<li><a href="%1$s">%2$s</a>%3$s</li>',
				esc_url( (string) get_edit_post_link( $report->ID ) ),
				esc_html( get_the_title( $report ) ?: __( '(ohne Titel)', 'nabu-unofficial-content' ) ),
				esc_html( $status )
			);
		}
		echo '</ul>';
	} else {
		printf(
			'<p style="margin-top:0">%s</p>',
			esc_html__( 'Nach dem Termin einfach einen normalen Beitrag schreiben und dort rechts „Bericht zu Termin“ auswählen. Der Bericht erscheint dann hier auf der Terminseite über der Ankündigung.', 'nabu-unofficial-content' )
		);
	}

	if ( 'auto-draft' !== $post->post_status ) {
		printf(
			'<p><a class="button" href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'post-new.php?nabu_report_for=' . (int) $post->ID ) ),
			esc_html__( 'Bericht zu diesem Termin schreiben', 'nabu-unofficial-content' )
		);
	}
}

/* ------------------------------------------------------------------ *
 * One URL: the report lives on the event page
 * ------------------------------------------------------------------ */

/**
 * Links to a report lead to the event page.
 *
 * @param string  $permalink Permalink.
 * @param WP_Post $post      Post.
 * @param bool    $leavename Whether the slug is kept as a placeholder.
 * @return string
 */
function nabu_report_permalink( $permalink, $post, $leavename ) {
	// The slug editor asks for the placeholder form; leave that alone.
	if ( $leavename ) {
		return $permalink;
	}

	$event = nabu_report_event( $post );

	return $event ? get_permalink( $event ) . '#bericht' : $permalink;
}
add_filter( 'post_link', 'nabu_report_permalink', 10, 3 );

/**
 * The report's own address forwards to the event page.
 *
 * Search engines and old bookmarks end up where the text actually is. The
 * editor preview stays on the post itself.
 */
function nabu_report_redirect() {
	if ( ! is_singular( 'post' ) || is_preview() ) {
		return;
	}

	$event = nabu_report_event( get_queried_object_id() );

	if ( $event ) {
		wp_safe_redirect( get_permalink( $event ) . '#bericht', 301 );
		exit;
	}
}
add_action( 'template_redirect', 'nabu_report_redirect', 1 );

/**
 * Keep reports out of the sitemap: their address is the event's.
 *
 * @param array  $args      Query args.
 * @param string $post_type Post type.
 * @return array
 */
function nabu_report_sitemap( $args, $post_type ) {
	if ( 'post' !== $post_type || ! nabu_report_map() ) {
		return $args;
	}

	$args['post__not_in'] = array_merge(
		isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(),
		array_keys( nabu_report_map() )
	);

	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'nabu_report_sitemap', 10, 2 );
