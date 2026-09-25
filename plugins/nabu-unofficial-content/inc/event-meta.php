<?php
/**
 * Event details: start, optional end, location with address, meeting point,
 * map position and a further link.
 *
 * A classic meta box: it shows up in the block editor too and needs no
 * JavaScript that would have to keep pace with every WordPress release.
 * Dates are stored as "Y-m-d H:i" in site-local time, which MySQL sorts and
 * compares correctly as strings.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field list. The key doubles as the meta key without prefix.
 *
 * @return array<string, array{label:string, type:string, help?:string}>
 */
function nabu_event_fields() {
	return array(
		'start'         => array(
			'label' => __( 'Beginn', 'nabu-unofficial-content' ),
			'type'  => 'datetime',
			'help'  => __( 'Datum ist Pflicht – ohne Datum erscheint der Termin nirgends. Uhrzeit leer lassen, wenn sie noch nicht feststeht oder der Termin den ganzen Tag dauert.', 'nabu-unofficial-content' ),
		),
		'end'           => array(
			'label' => __( 'Ende (optional)', 'nabu-unofficial-content' ),
			'type'  => 'datetime',
		),
		'all_day'       => array(
			'label' => __( 'Keine Uhrzeit anzeigen', 'nabu-unofficial-content' ),
			'type'  => 'checkbox',
			'help'  => __( 'Für ganztägige Termine oder wenn die Uhrzeit noch nicht feststeht. Wird von selbst gesetzt, solange beim Beginn keine Uhrzeit steht.', 'nabu-unofficial-content' ),
		),
		'location'      => array(
			'label' => __( 'Ort', 'nabu-unofficial-content' ),
			'type'  => 'text',
			'help'  => __( 'Zum Beispiel „Streuobstwiese am Ortsrand“.', 'nabu-unofficial-content' ),
		),
		'address'       => array(
			'label' => __( 'Anschrift (optional)', 'nabu-unofficial-content' ),
			'type'  => 'text',
		),
		'meeting_point' => array(
			'label' => __( 'Treffpunkt', 'nabu-unofficial-content' ),
			'type'  => 'text',
			'help'  => __( 'Wo genau man sich trifft, wenn das nicht der Ort selbst ist.', 'nabu-unofficial-content' ),
		),
		'map'           => array(
			'label' => __( 'Kartenposition (optional)', 'nabu-unofficial-content' ),
			'type'  => 'text',
			'help'  => __( 'Leer: Die Karte zeigt die Anschrift. Für Treffpunkte ohne Anschrift, etwa im Wald, Koordinaten eintragen („51.1657, 10.4515“) oder einen Link aus Google Maps bzw. OpenStreetMap einfügen – die Koordinaten werden daraus übernommen.', 'nabu-unofficial-content' ),
		),
		'link'          => array(
			'label' => __( 'Weiterführender Link (optional)', 'nabu-unofficial-content' ),
			'type'  => 'url',
		),
	);
}

/**
 * Full meta key for a field.
 *
 * @param string $key Field key.
 * @return string
 */
function nabu_event_meta_key( $key ) {
	return '_nabu_event_' . $key;
}

/**
 * Register the meta for REST and WP-CLI.
 */
function nabu_event_register_meta() {
	foreach ( nabu_event_fields() as $key => $field ) {
		register_post_meta(
			'nabu_event',
			nabu_event_meta_key( $key ),
			array(
				'type'          => 'checkbox' === $field['type'] ? 'boolean' : 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => 'checkbox' === $field['type'] ? false : '',
				'auth_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'nabu_event_register_meta' );

/**
 * Register the meta box — in the sidebar, where the mandatory start date is
 * in view; the area below the content starts collapsed.
 */
function nabu_event_meta_box() {
	add_meta_box(
		'nabu-event-details',
		__( 'Termindetails', 'nabu-unofficial-content' ),
		'nabu_event_meta_box_render',
		'nabu_event',
		'side',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes', 'nabu_event_meta_box' );

/**
 * Render the meta box.
 *
 * @param WP_Post $post Current event.
 */
function nabu_event_meta_box_render( $post ) {
	wp_nonce_field( 'nabu_event_save', 'nabu_event_nonce' );

	// An event without a start is left out of every list; say so where it is fixed.
	if ( 'auto-draft' !== $post->post_status && '' === (string) get_post_meta( $post->ID, '_nabu_event_start', true ) ) {
		printf(
			'<div class="notice notice-error inline" style="margin:0 0 1rem"><p><strong>%s</strong></p></div>',
			esc_html__( 'Beginn fehlt: Ohne Datum erscheint dieser Termin nicht auf der Website. Bitte das Datum eintragen und speichern.', 'nabu-unofficial-content' )
		);
	}

	echo '<div class="nabu-event-fields" style="display:grid;gap:1rem;max-width:44rem">';

	foreach ( nabu_event_fields() as $key => $field ) {
		$name  = nabu_event_meta_key( $key );
		$value = get_post_meta( $post->ID, $name, true );

		echo '<p style="margin:0">';

		if ( 'checkbox' === $field['type'] ) {
			printf(
				'<label for="%1$s"><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> <strong>%3$s</strong></label>',
				esc_attr( $name ),
				checked( $value, '1', false ),
				esc_html( $field['label'] )
			);
		} elseif ( 'datetime' === $field['type'] ) {
			// Date and time as two inputs: a combined datetime-local input
			// silently drops the whole value when only the date is filled in.
			$date    = substr( (string) $value, 0, 10 );
			$time    = substr( (string) $value, 11, 5 );
			$all_day = '1' === (string) get_post_meta( $post->ID, nabu_event_meta_key( 'all_day' ), true );
			if ( $all_day && '00:00' === $time ) {
				$time = '';
			}
			printf(
				'<label for="%1$s-date"><strong>%2$s</strong></label><br>',
				esc_attr( $name ),
				esc_html( $field['label'] )
			);
			printf(
				'<input type="date" id="%1$s-date" name="%1$s[date]" value="%2$s"%4$s> <label for="%1$s-time" class="screen-reader-text">%5$s</label><input type="time" id="%1$s-time" name="%1$s[time]" value="%3$s"> <span class="description">%6$s</span>',
				esc_attr( $name ),
				esc_attr( $date ),
				esc_attr( $time ),
				'start' === $key ? ' required' : '',
				/* translators: %s: field label. */
				esc_html( sprintf( __( '%s: Uhrzeit', 'nabu-unofficial-content' ), $field['label'] ) ),
				esc_html__( 'Uhr', 'nabu-unofficial-content' )
			);
		} else {
			printf(
				'<label for="%1$s"><strong>%2$s</strong></label><br>',
				esc_attr( $name ),
				esc_html( $field['label'] )
			);
			printf(
				'<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" class="widefat" style="max-width:28rem">',
				esc_attr( $field['type'] ),
				esc_attr( $name ),
				esc_attr( (string) $value )
			);
		}

		if ( ! empty( $field['help'] ) ) {
			printf( '<br><span class="description">%s</span>', esc_html( $field['help'] ) );
		}

		echo '</p>';
	}

	echo '</div>';
}

/**
 * Save the meta box.
 *
 * @param int $post_id Post ID.
 */
function nabu_event_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['nabu_event_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nabu_event_nonce'] ) ), 'nabu_event_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$values     = array();
	$no_time    = false;
	$time_added = false;

	// Before the save: was the start without a time, and so flagged all day?
	$was_timeless = '1' === (string) get_post_meta( $post_id, nabu_event_meta_key( 'all_day' ), true )
		&& '00:00' === substr( (string) get_post_meta( $post_id, nabu_event_meta_key( 'start' ), true ), 11, 5 );

	foreach ( nabu_event_fields() as $key => $field ) {
		$name = nabu_event_meta_key( $key );

		if ( 'checkbox' === $field['type'] ) {
			$values[ $key ] = isset( $_POST[ $name ] ) ? '1' : '';
		} elseif ( 'url' === $field['type'] ) {
			$values[ $key ] = isset( $_POST[ $name ] ) ? esc_url_raw( wp_unslash( $_POST[ $name ] ) ) : '';
		} elseif ( 'datetime' === $field['type'] ) {
			$parts = isset( $_POST[ $name ] ) && is_array( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : array();
			$date  = sanitize_text_field( (string) ( $parts['date'] ?? '' ) );
			$time  = sanitize_text_field( (string) ( $parts['time'] ?? '' ) );

			$values[ $key ] = '' === $date ? '' : nabu_event_normalize_datetime( $date . ' ' . ( '' === $time ? '00:00' : $time ) );

			// A start without a time shows no time: all day, or not fixed yet.
			// Once a time is added, it shows.
			if ( 'start' === $key && '' !== $date ) {
				$no_time    = '' === $time;
				$time_added = '' !== $time && $was_timeless;
			}
		} else {
			$values[ $key ] = isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
		}
	}

	if ( $no_time ) {
		$values['all_day'] = '1';
	} elseif ( $time_added ) {
		$values['all_day'] = '';
	}

	foreach ( $values as $key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, nabu_event_meta_key( $key ) );
		} else {
			update_post_meta( $post_id, nabu_event_meta_key( $key ), $value );
		}
	}
}
add_action( 'save_post_nabu_event', 'nabu_event_meta_box_save' );

/**
 * Normalize date input to "Y-m-d H:i".
 *
 * @param string $raw Raw form value.
 * @return string Empty if unreadable.
 */
function nabu_event_normalize_datetime( $raw ) {
	$date = date_create_immutable( str_replace( 'T', ' ', trim( (string) $raw ) ) );

	return $date ? $date->format( 'Y-m-d H:i' ) : '';
}

/*
 * The theme ships the same function so it runs without this plugin. Plugins
 * load first, except in the request that activates the plugin — hence the
 * guard on both sides.
 */
if ( ! function_exists( 'nabu_event_timestamp' ) ) {
	/**
	 * Convert a meta value to a Unix timestamp. strtotime() would read the
	 * value as UTC and shift it by the site's offset.
	 *
	 * @param string $value Meta value ("Y-m-d H:i" in site-local time).
	 * @return int Zero if unreadable.
	 */
	function nabu_event_timestamp( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		try {
			return ( new DateTimeImmutable( $value, wp_timezone() ) )->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}
}

/**
 * "Beginn" and "Ort" columns in the events list.
 *
 * @param array $columns Columns.
 * @return array
 */
function nabu_event_columns( $columns ) {
	$result = array();
	foreach ( $columns as $key => $label ) {
		$result[ $key ] = $label;
		if ( 'title' === $key ) {
			$result['nabu_event_start']    = __( 'Beginn', 'nabu-unofficial-content' );
			$result['nabu_event_location'] = __( 'Ort', 'nabu-unofficial-content' );
		}
	}
	return $result;
}
add_filter( 'manage_nabu_event_posts_columns', 'nabu_event_columns' );

/**
 * Content of the custom columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function nabu_event_column_content( $column, $post_id ) {
	if ( 'nabu_event_start' === $column ) {
		$start   = (string) get_post_meta( $post_id, '_nabu_event_start', true );
		$all_day = '1' === (string) get_post_meta( $post_id, '_nabu_event_all_day', true );
		echo $start
			? esc_html( wp_date( $all_day ? 'j. M Y' : 'j. M Y, H:i', nabu_event_timestamp( $start ) ) )
			: '<strong style="color:#b32d2e">' . esc_html__( 'Beginn fehlt', 'nabu-unofficial-content' ) . '</strong>';
	}
	if ( 'nabu_event_location' === $column ) {
		echo esc_html( (string) get_post_meta( $post_id, '_nabu_event_location', true ) ?: '—' );
	}
}
add_action( 'manage_nabu_event_posts_custom_column', 'nabu_event_column_content', 10, 2 );

/**
 * Make the "Beginn" column sortable.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function nabu_event_sortable_columns( $columns ) {
	$columns['nabu_event_start'] = 'nabu_event_start';
	return $columns;
}
add_filter( 'manage_edit-nabu_event_sortable_columns', 'nabu_event_sortable_columns' );

/**
 * Sort the admin list by start date by default.
 *
 * @param WP_Query $query Query.
 */
function nabu_event_admin_order( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'nabu_event' !== $query->get( 'post_type' ) ) {
		return;
	}

	$orderby = $query->get( 'orderby' );

	if ( ! $orderby || 'nabu_event_start' === $orderby ) {
		// Not via meta_key: that would drop every event without a start from
		// the list — exactly the ones that need fixing.
		$query->set(
			'meta_query',
			array(
				'relation'   => 'OR',
				'nabu_start' => array(
					'key'     => '_nabu_event_start',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_nabu_event_start',
					'compare' => 'NOT EXISTS',
				),
			)
		);
		$query->set( 'orderby', array( 'nabu_start' => $query->get( 'order' ) ? $query->get( 'order' ) : 'ASC' ) );
	}
}
add_action( 'pre_get_posts', 'nabu_event_admin_order' );

/**
 * The front-end archive (and its feed): upcoming events, soonest first.
 *
 * @param WP_Query $query Query.
 */
function nabu_event_archive_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'nabu_event' ) ) {
		return;
	}

	$query->set( 'meta_key', '_nabu_event_start' );
	$query->set( 'orderby', 'meta_value' );
	$query->set( 'order', 'ASC' );
	$query->set(
		'meta_query',
		array(
			array(
				'key'     => '_nabu_event_start',
				'value'   => current_time( 'Y-m-d' ) . ' 00:00',
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		)
	);
}
add_action( 'pre_get_posts', 'nabu_event_archive_query' );
