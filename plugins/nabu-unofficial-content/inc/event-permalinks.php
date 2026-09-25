<?php
/**
 * Event permalinks with the year: /termine/2026/mahd-fruehjahr/.
 *
 * The year is the event's own, taken from its start, not the day it was
 * entered. A name only has to be unique within its year, so an event that
 * comes back every year keeps its name: /termine/2027/mahd-fruehjahr/.
 * A published event remembers every permalink it had, so one from before a
 * move to another year or a new name leads to the current one. (Not to be
 * confused with the event's postal address, _nabu_event_address.)
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The year an event belongs to: its start, else the day it was entered.
 *
 * @param WP_Post $post  Event.
 * @param string  $start Start as stored ("Y-m-d H:i"), if already known.
 * @return string Four digits.
 */
function nabu_event_year( $post, $start = null ) {
	$start = null === $start ? (string) get_post_meta( $post->ID, '_nabu_event_start', true ) : $start;
	if ( preg_match( '/^\d{4}/', $start, $match ) ) {
		return $match[0];
	}

	$year = substr( (string) $post->post_date, 0, 4 );
	return ( '' === $year || '0000' === $year ) ? wp_date( 'Y' ) : $year;
}

/**
 * The permalink pattern for single events, below the events archive.
 */
function nabu_event_register_permalinks() {
	add_rewrite_tag( '%nabu_event_year%', '([0-9]{4})' );
	add_permastruct(
		'nabu_event',
		nabu_group_get( 'events_slug' ) . '/%nabu_event_year%/%nabu_event%',
		array(
			'with_front' => false,
			'ep_mask'    => EP_PERMALINK,
			'walk_dirs'  => false,
		)
	);
}
add_action( 'init', 'nabu_event_register_permalinks', 11 );

/**
 * Put the year into an event's permalink.
 *
 * @param string  $link Address with placeholders.
 * @param WP_Post $post Post.
 * @return string
 */
function nabu_event_permalink( $link, $post ) {
	if ( 'nabu_event' !== $post->post_type || false === strpos( $link, '%nabu_event_year%' ) ) {
		return $link;
	}
	return str_replace( '%nabu_event_year%', nabu_event_year( $post ), $link );
}
add_filter( 'post_type_link', 'nabu_event_permalink', 10, 2 );

/**
 * Published events with a given name.
 *
 * @param string $name URL name.
 * @return WP_Post[]
 */
function nabu_events_named( $name ) {
	return get_posts(
		array(
			'post_type'        => 'nabu_event',
			'name'             => $name,
			'post_status'      => array( 'publish', 'private' ),
			'posts_per_page'   => -1,
			'suppress_filters' => false,
		)
	);
}

/**
 * Unique names per year instead of across all events.
 *
 * WordPress numbers a name that another event already has; within events
 * the name only has to be free in the event's own year.
 *
 * @param string $slug          Name WordPress settled on.
 * @param int    $post_id       Post ID.
 * @param string $post_status   Status.
 * @param string $post_type     Post type.
 * @param int    $post_parent   Parent.
 * @param string $original_slug Name asked for.
 * @return string
 */
function nabu_event_unique_name( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
	if ( 'nabu_event' !== $post_type || in_array( $original_slug, array( 'feed', 'rdf', 'rss', 'rss2', 'atom', 'embed' ), true ) ) {
		return $slug;
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return $slug;
	}

	// While the event form is being saved, its start is not stored yet.
	$start = null;
	$field = '_nabu_event_start';
	if ( isset( $_POST[ $field ]['date'] ) && is_string( $_POST[ $field ]['date'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only, the save verifies the nonce.
		$start = sanitize_text_field( wp_unslash( $_POST[ $field ]['date'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
	$year = nabu_event_year( $post, $start );

	$taken = array();
	foreach ( nabu_events_named_like( $original_slug, $post_id ) as $other ) {
		if ( nabu_event_year( $other ) === $year ) {
			$taken[ $other->post_name ] = true;
		}
	}

	$candidate = $original_slug;
	for ( $n = 2; isset( $taken[ $candidate ] ); $n++ ) {
		$candidate = $original_slug . '-' . $n;
	}
	return $candidate;
}
add_filter( 'wp_unique_post_slug', 'nabu_event_unique_name', 10, 6 );

/**
 * Events whose name is the given one or a numbered form of it.
 *
 * @param string $name       URL name.
 * @param int    $exclude_id Event to leave out.
 * @return WP_Post[]
 */
function nabu_events_named_like( $name, $exclude_id ) {
	global $wpdb;

	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a uniqueness check has to see the current state.
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'nabu_event' AND ID != %d AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND ( post_name = %s OR post_name LIKE %s )",
			$exclude_id,
			$name,
			$wpdb->esc_like( $name ) . '-%'
		)
	);

	return array_filter( array_map( 'get_post', $ids ) );
}

/**
 * The name again once the start is stored: the block editor saves the
 * event before the form with its start, and the REST API stores the start
 * after the post, so WordPress may have numbered the name against another
 * year's event, and a moved event may now share its name with one in the
 * new year. Then the permalink is recorded (nabu_event_remember_permalink()).
 *
 * @param int $post_id Post ID.
 */
function nabu_event_settle_permalink( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'nabu_event' !== $post->post_type || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}

	if ( '' !== $post->post_name ) {
		// A number WordPress added to the name from the title goes back to that name.
		$wanted = $post->post_name;
		if ( preg_match( '/^(.+)-\d+$/', $wanted, $match ) && sanitize_title( $post->post_title ) === $match[1] ) {
			$wanted = $match[1];
		}

		$name = wp_unique_post_slug( $wanted, $post_id, $post->post_status, $post->post_type, $post->post_parent );
		if ( $name !== $post->post_name ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_name' => $name ), array( 'ID' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- wp_update_post() would save the event again.
			clean_post_cache( $post_id );
			$post = get_post( $post_id );
		}
	}

	nabu_event_remember_permalink( $post );
}
add_action( 'save_post_nabu_event', 'nabu_event_settle_permalink', 20 );
add_action(
	'rest_after_insert_nabu_event',
	static function ( $post ) {
		nabu_event_settle_permalink( $post->ID );
	}
);

/**
 * Keep the permalink a published event has, and every one it had before.
 *
 * @param WP_Post $post Event.
 */
function nabu_event_remember_permalink( $post ) {
	if ( 'publish' !== $post->post_status || '' === $post->post_name ) {
		return;
	}

	$permalink = nabu_event_year( $post ) . '/' . $post->post_name;
	$previous  = (string) get_post_meta( $post->ID, '_nabu_event_permalink', true );

	if ( $previous === $permalink ) {
		return;
	}
	if ( '' !== $previous && ! in_array( $previous, get_post_meta( $post->ID, '_nabu_event_old_permalink' ), true ) ) {
		add_post_meta( $post->ID, '_nabu_event_old_permalink', $previous );
	}
	update_post_meta( $post->ID, '_nabu_event_permalink', $permalink );
}

/**
 * Before an update, while name and start are still the old ones: an event
 * published before it was ever saved here gets its permalink recorded, so a
 * move or a new name can refer back to it.
 *
 * @param int $post_id Post ID.
 */
function nabu_event_remember_permalink_before_update( $post_id ) {
	$post = get_post( $post_id );
	if ( $post && 'nabu_event' === $post->post_type && '' === (string) get_post_meta( $post_id, '_nabu_event_permalink', true ) ) {
		nabu_event_remember_permalink( $post );
	}
}
add_action( 'pre_post_update', 'nabu_event_remember_permalink_before_update' );

/**
 * Resolve /termine/<year>/<name>/: the published event with that name in
 * that year, else the one that had this permalink before, else nothing.
 *
 * @param array $vars Query variables.
 * @return array
 */
function nabu_event_resolve_permalink( $vars ) {
	if ( empty( $vars['nabu_event'] ) || empty( $vars['nabu_event_year'] ) || ! empty( $vars['p'] ) ) {
		return $vars;
	}

	$year  = (string) $vars['nabu_event_year'];
	$name  = (string) $vars['nabu_event'];
	$event = null;

	foreach ( nabu_events_named( $name ) as $candidate ) {
		if ( nabu_event_year( $candidate ) === $year ) {
			$event = $candidate;
			break;
		}
	}

	if ( ! $event ) {
		$moved = get_posts(
			array(
				'post_type'      => 'nabu_event',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_nabu_event_old_permalink', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- only for permalinks that match no event.
				'meta_value'     => $year . '/' . $name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			)
		);
		$event = $moved ? $moved[0] : null;
	}

	// Without a match the name alone must not find an event of another year.
	if ( ! $event ) {
		return array( 'error' => '404' );
	}

	unset( $vars['nabu_event'], $vars['name'] );
	$vars['p']                    = $event->ID;
	$vars['post_type']            = 'nabu_event';
	$vars['nabu_event_requested'] = $year . '/' . $name;

	return $vars;
}
add_filter( 'request', 'nabu_event_resolve_permalink' );

/**
 * An old permalink leads to the current one.
 */
function nabu_event_redirect_permalink() {
	$requested = (string) get_query_var( 'nabu_event_requested' );
	if ( '' === $requested || ! is_singular( 'nabu_event' ) ) {
		return;
	}

	$event = get_queried_object();
	if ( nabu_event_year( $event ) . '/' . $event->post_name !== $requested ) {
		wp_safe_redirect( get_permalink( $event ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'nabu_event_redirect_permalink', 5 );
