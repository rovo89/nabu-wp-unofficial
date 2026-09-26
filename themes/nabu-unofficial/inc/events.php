<?php
/**
 * Event blocks.
 *
 * nabu/events         — upcoming or past events of the group, as a list or
 *                       as cards.
 * nabu/event-details  — the details of the event on screen, for its page.
 *
 * Events are maintained on the site itself (post type `nabu_event` of the
 * companion plugin), not pulled from NABU's central event database.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the event list.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function nabu_render_events( $attributes ) {
	$attributes = wp_parse_args(
		$attributes,
		array(
			'count'        => 4,
			'range'        => 'upcoming',
			'showExcerpt'  => true,
			'emptyText'    => '',
			'headingLevel' => 3,
			'layout'       => 'list',
		)
	);

	if ( ! post_type_exists( 'nabu_event' ) ) {
		return nabu_events_notice(
			__( 'Das Plugin „NABU Unofficial: Inhalte“ ist nicht aktiv – ohne den Inhaltstyp „Termin“ kann hier nichts angezeigt werden.', 'nabu-unofficial' )
		);
	}

	$today = current_time( 'Y-m-d' ) . ' 00:00';

	$args = array(
		'post_type'              => 'nabu_event',
		'post_status'            => 'publish',
		'posts_per_page'         => max( 1, (int) $attributes['count'] ),
		'meta_key'               => '_nabu_event_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'                => 'meta_value',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
	);

	if ( 'past' === $attributes['range'] ) {
		$args['order']      = 'DESC';
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_nabu_event_start',
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATETIME',
			),
		);
	} elseif ( 'upcoming' === $attributes['range'] ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_nabu_event_start',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		);
	}

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		// An empty look back prints nothing, so a surrounding nabu-optional
		// section ("Was war") disappears with it.
		if ( 'past' === $attributes['range'] && ! $attributes['emptyText'] ) {
			return ( defined( 'REST_REQUEST' ) && REST_REQUEST )
				? nabu_events_notice( __( 'Noch keine vergangenen Termine – auf der Website bleibt die Liste leer.', 'nabu-unofficial' ) )
				: '';
		}

		$empty = $attributes['emptyText']
			? $attributes['emptyText']
			: __( 'Zurzeit sind keine Termine eingetragen.', 'nabu-unofficial' );
		return nabu_events_notice( $empty );
	}

	$cards = 'cards' === $attributes['layout'];
	$items = array();

	foreach ( $query->posts as $post ) {
		$items[] = $cards
			? nabu_render_event_card( $post, (bool) $attributes['showExcerpt'], (int) $attributes['headingLevel'] )
			: nabu_render_event_item(
				$post,
				(bool) $attributes['showExcerpt'],
				'past' === $attributes['range'],
				(int) $attributes['headingLevel']
			);
	}

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'nabu-events-wrap' ) );

	return sprintf(
		'<div %1$s><ul class="nabu-events%3$s">%2$s</ul></div>',
		$wrapper,
		implode( '', $items ),
		$cards ? ' nabu-events--cards' : ''
	);
}

/**
 * Notice box when there is nothing to show.
 *
 * @param string $text Message.
 * @return string
 */
function nabu_events_notice( $text ) {
	$wrapper = get_block_wrapper_attributes( array( 'class' => 'nabu-events-wrap' ) );
	return sprintf( '<div %1$s><p class="nabu-events__empty">%2$s</p></div>', $wrapper, esc_html( $text ) );
}

// The plugin owns the date format and provides this; the copy keeps the
// theme running on its own.
if ( ! function_exists( 'nabu_event_timestamp' ) ) {
	/**
	 * Convert a meta value to a Unix timestamp.
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
 * Collect what list item and card both need: timestamp, time of day,
 * location lines and the schema.org Event markup.
 *
 * @param WP_Post $post Event.
 * @return array{ts:int,start:string,time:string,meta:string[],ld:string}
 */
function nabu_event_data( $post ) {
	$start    = (string) get_post_meta( $post->ID, '_nabu_event_start', true );
	$end      = (string) get_post_meta( $post->ID, '_nabu_event_end', true );
	$location = (string) get_post_meta( $post->ID, '_nabu_event_location', true );
	$address  = (string) get_post_meta( $post->ID, '_nabu_event_address', true );
	$meeting  = (string) get_post_meta( $post->ID, '_nabu_event_meeting_point', true );
	$all_day  = (bool) get_post_meta( $post->ID, '_nabu_event_all_day', true );

	$ts = $start ? nabu_event_timestamp( $start ) : get_post_time( 'U', true, $post );

	// The time belongs to the date line. "All day" also means "time not
	// fixed yet", so it shows no time rather than a word that may be wrong.
	$time = '';
	if ( $ts && ! $all_day ) {
		$time = sprintf(
			/* translators: %s: time of day. */
			esc_html__( '%s Uhr', 'nabu-unofficial' ),
			esc_html( wp_date( 'H:i', $ts ) )
		);
	}

	$meta = array();
	if ( $location ) {
		$meta[] = '<span class="nabu-event__location">' . esc_html( $location ) . '</span>';
	}
	if ( $meeting ) {
		$meta[] = sprintf(
			/* translators: 1: opening label tag, 2: closing label tag, 3: meeting point. */
			esc_html__( '%1$sTreffpunkt:%2$s %3$s', 'nabu-unofficial' ),
			'<span class="nabu-event__label">',
			'</span>',
			esc_html( $meeting )
		);
	}

	$map = $location ? nabu_map_embed( (string) get_post_meta( $post->ID, '_nabu_event_map', true ), $address ) : null;

	$ld = wp_json_encode(
		array_filter(
			array(
				'@context'    => 'https://schema.org',
				'@type'       => 'Event',
				'name'        => get_the_title( $post ),
				'startDate'   => $start ? wp_date( $all_day ? 'Y-m-d' : 'c', $ts ) : null,
				'endDate'     => $end ? wp_date( $all_day ? 'Y-m-d' : 'c', nabu_event_timestamp( $end ) ) : null,
				'url'         => get_permalink( $post ),
				'description' => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'location'    => $location ? array_filter(
					array(
						'@type'   => 'Place',
						'name'    => $location,
						'address' => $address ? $address : null,
						'hasMap'  => $map ? $map['link'] : null,
					)
				) : null,
			)
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	return array(
		'ts'    => (int) $ts,
		'start' => $start,
		'time'  => $time,
		'meta'  => $meta,
		'ld'    => (string) $ld,
	);
}

/**
 * Location lines as a paragraph.
 *
 * @param string[] $meta Already escaped lines, possibly with markup.
 * @return string Empty if there is nothing to say.
 */
function nabu_event_meta_html( $meta ) {
	if ( ! $meta ) {
		return '';
	}

	return '<p class="nabu-event__meta">' . implode( '', array_map( static fn( $m ) => '<span>' . $m . '</span>', $meta ) ) . '</p>';
}

/**
 * Render an event as a list item with Event markup.
 *
 * @param WP_Post $post         Event.
 * @param bool    $show_excerpt Print the excerpt.
 * @param bool    $past         Mark as past.
 * @param int     $level        Heading level of the title (2–4).
 * @return string
 */
function nabu_render_event_item( $post, $show_excerpt, $past, $level = 3 ) {
	$level = min( 4, max( 2, (int) $level ) );
	$d     = nabu_event_data( $post );
	$ts    = $d['ts'];

	if ( $past && nabu_event_reports( $post->ID ) ) {
		$d['meta'][] = '<a class="nabu-event__report" href="' . esc_url( get_permalink( $post ) . '#bericht' ) . '">' . esc_html__( 'mit Bericht', 'nabu-unofficial' ) . '</a>';
	}

	return sprintf(
		'<li class="nabu-event%1$s">
			<div class="nabu-event__date"><time datetime="%2$s"><span class="nabu-event__day">%3$s</span><span class="nabu-event__month">%4$s</span><span class="nabu-event__year">%5$s</span>%12$s</time></div>
			<div class="nabu-event__content">
				<h%11$d class="nabu-event__title"><a href="%6$s">%7$s</a></h%11$d>
				%8$s
				%9$s
			</div>
			<script type="application/ld+json">%10$s</script>
		</li>',
		$past ? ' nabu-event--past' : '',
		esc_attr( $ts ? wp_date( 'Y-m-d', $ts ) : '' ),
		esc_html( $ts ? wp_date( 'j', $ts ) : '–' ),
		esc_html( $ts ? wp_date( 'M', $ts ) : '' ),
		esc_html( $ts ? wp_date( 'Y', $ts ) : '' ),
		esc_url( (string) get_permalink( $post ) ),
		esc_html( get_the_title( $post ) ),
		nabu_event_meta_html( $d['meta'] ),
		$show_excerpt && get_the_excerpt( $post ) ? '<p class="nabu-event__excerpt">' . esc_html( wp_trim_words( get_the_excerpt( $post ), 28 ) ) . '</p>' : '',
		$d['ld'],
		$level,
		$d['time'] ? '<span class="nabu-event__time">' . $d['time'] . '</span>' : ''
	);
}

/**
 * Render an event as a card, like the news cards.
 *
 * The featured image on top, with the date as a badge; without an image a
 * blue head with the large date in the same proportions, so a row of cards
 * stays flush. The image is the card cut (card-images.php), loaded lazily:
 * on the front page it sits below the hero, which comes first.
 *
 * @param WP_Post $post         Event.
 * @param bool    $show_excerpt Print the excerpt.
 * @param int     $level        Heading level of the title (2–4).
 * @return string
 */
function nabu_render_event_card( $post, $show_excerpt, $level = 3 ) {
	$level = min( 4, max( 2, (int) $level ) );
	$d     = nabu_event_data( $post );
	$ts    = $d['ts'];
	$url   = esc_url( (string) get_permalink( $post ) );

	if ( has_post_thumbnail( $post ) ) {
		// Badge and date head are decorative; the full date follows in the text.
		$head = sprintf(
			'<figure class="nabu-event-card__image"><a href="%1$s" tabindex="-1" aria-hidden="true">%2$s</a><span class="nabu-event-card__badge" aria-hidden="true"><span class="nabu-event__day">%3$s</span><span class="nabu-event__month">%4$s</span></span></figure>',
			$url,
			nabu_card_image( get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy' ) ), $post->ID ),
			esc_html( $ts ? wp_date( 'j', $ts ) : '–' ),
			esc_html( $ts ? wp_date( 'M', $ts ) : '' )
		);
	} else {
		$head = sprintf(
			'<div class="nabu-event-card__date-head" aria-hidden="true"><span class="nabu-event__day">%1$s</span><span class="nabu-event__month">%2$s</span></div>',
			esc_html( $ts ? wp_date( 'j.', $ts ) : '–' ),
			esc_html( $ts ? wp_date( 'F', $ts ) : '' )
		);
	}

	$date_line = $ts
		? '<time datetime="' . esc_attr( wp_date( 'Y-m-d', $ts ) ) . '">' . esc_html( wp_date( 'l, d.m.Y', $ts ) ) . '</time>'
		: '';
	if ( $d['time'] ) {
		$date_line .= ( $date_line ? ' · ' : '' ) . '<span class="nabu-event__time">' . $d['time'] . '</span>';
	}

	return sprintf(
		'<li class="nabu-card nabu-card--sand nabu-event-card">
			%1$s
			<div class="nabu-card__body">
				<p class="nabu-event-card__date-line">%2$s</p>
				<h%3$d class="nabu-event__title"><a href="%4$s">%5$s</a></h%3$d>
				%6$s
				%7$s
			</div>
			<script type="application/ld+json">%8$s</script>
		</li>',
		$head,
		$date_line,
		$level,
		$url,
		esc_html( get_the_title( $post ) ),
		nabu_event_meta_html( $d['meta'] ),
		$show_excerpt && get_the_excerpt( $post ) ? '<p class="nabu-event__excerpt">' . esc_html( wp_trim_words( get_the_excerpt( $post ), 22 ) ) . '</p>' : '',
		$d['ld']
	);
}

/**
 * Render the details of the event on screen (nabu/event-details).
 *
 * Two layouts: the date line of the cards, or the box with everything —
 * start and end, location and address, meeting point, map, further link —
 * which also carries the page's Event markup.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Unused.
 * @param WP_Block $block      Block with context (postId).
 * @return string
 */
function nabu_render_event_details( $attributes, $content, $block ) {
	$attributes = wp_parse_args( $attributes, array( 'layout' => 'box' ) );
	$post_id    = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
	$post       = $post_id ? get_post( $post_id ) : null;

	if ( ! $post instanceof WP_Post || 'nabu_event' !== $post->post_type ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="nabu-events__empty">' . esc_html__( 'Termindetails – werden auf der Seite eines Termins gefüllt.', 'nabu-unofficial' ) . '</p>';
		}
		return '';
	}

	$d       = nabu_event_data( $post );
	$ts      = $d['ts'];
	$end     = (string) get_post_meta( $post->ID, '_nabu_event_end', true );
	$te      = $end ? nabu_event_timestamp( $end ) : 0;
	$all_day = (bool) get_post_meta( $post->ID, '_nabu_event_all_day', true );

	if ( 'line' === $attributes['layout'] ) {
		if ( ! $ts ) {
			return '';
		}
		$line = '<time datetime="' . esc_attr( wp_date( 'Y-m-d', $ts ) ) . '">' . esc_html( wp_date( 'l, d.m.Y', $ts ) ) . '</time>';
		if ( $d['time'] ) {
			$line .= ' · <span class="nabu-event__time">' . $d['time'] . '</span>';
		}
		return '<p class="nabu-event-card__date-line nabu-event-details__line">' . $line . '</p>';
	}

	// When: start; an end on the same day becomes a span, otherwise two lines.
	$when = array();
	if ( $ts ) {
		$begin = esc_html( wp_date( 'l, d.m.Y', $ts ) );
		if ( $all_day ) {
			// No time — and an end on the same day adds nothing.
			if ( $te && wp_date( 'Y-m-d', $te ) === wp_date( 'Y-m-d', $ts ) ) {
				$te = 0;
			}
		} else {
			$begin .= ', ' . esc_html( wp_date( 'H:i', $ts ) );
			if ( $te && wp_date( 'Y-m-d', $te ) === wp_date( 'Y-m-d', $ts ) ) {
				$begin .= '–' . esc_html( wp_date( 'H:i', $te ) );
				$te     = 0;
			}
			$begin .= ' ' . esc_html__( 'Uhr', 'nabu-unofficial' );
		}
		$when[] = $begin;
		if ( $te ) {
			$when[] = sprintf(
				/* translators: %s: date and time of the end. */
				esc_html__( 'bis %s', 'nabu-unofficial' ),
				esc_html( wp_date( 'l, d.m.Y', $te ) ) . ( $all_day ? '' : ', ' . esc_html( wp_date( 'H:i', $te ) ) . ' ' . esc_html__( 'Uhr', 'nabu-unofficial' ) )
			);
		}
	}

	$location = (string) get_post_meta( $post->ID, '_nabu_event_location', true );
	$address  = (string) get_post_meta( $post->ID, '_nabu_event_address', true );
	$meeting  = (string) get_post_meta( $post->ID, '_nabu_event_meeting_point', true );
	$map_pos  = (string) get_post_meta( $post->ID, '_nabu_event_map', true );
	$link     = (string) get_post_meta( $post->ID, '_nabu_event_link', true );

	$where = array();
	if ( $location ) {
		$where[] = esc_html( $location );
	}
	if ( $address ) {
		$where[] = esc_html( $address );
	}

	$rows = array();
	if ( $when ) {
		$rows[] = array( __( 'Wann', 'nabu-unofficial' ), implode( '<br>', $when ) );
	}
	if ( $where ) {
		$rows[] = array( __( 'Wo', 'nabu-unofficial' ), implode( '<br>', $where ) );
	}
	if ( $meeting ) {
		$rows[] = array( __( 'Treffpunkt', 'nabu-unofficial' ), esc_html( $meeting ) );
	}
	// The map as a link for everyone, and as an embed behind the consent.
	$map = nabu_map_embed( $map_pos, $address );
	if ( $map ) {
		$rows[] = array(
			__( 'Karte', 'nabu-unofficial' ),
			'<a href="' . esc_url( $map['link'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'In Google Maps öffnen', 'nabu-unofficial' ) . '</a>',
		);
	}
	if ( $link ) {
		$rows[] = array(
			__( 'Mehr dazu', 'nabu-unofficial' ),
			'<a href="' . esc_url( $link ) . '">' . esc_html( wp_parse_url( $link, PHP_URL_HOST ) ?: $link ) . '</a>',
		);
	}

	if ( ! $rows ) {
		return '';
	}

	$html = '';
	foreach ( $rows as $row ) {
		$html .= '<div class="nabu-event-details__row"><dt>' . esc_html( $row[0] ) . '</dt><dd>' . $row[1] . '</dd></div>';
	}

	$map_html = $map
		? nabu_third_party_embed(
			$map['src'],
			/* translators: %s: event title. */
			sprintf( __( 'Karte zum Treffpunkt: %s', 'nabu-unofficial' ), get_the_title( $post ) )
		)
		: '';

	// The page lives on after the event; whoever arrives from an old link
	// learns first that it is over.
	$past = $ts && nabu_event_is_past( $post )
		? '<p class="nabu-event-details__past">' . sprintf(
			/* translators: %s: date of the event. */
			esc_html__( 'Dieser Termin hat am %s stattgefunden.', 'nabu-unofficial' ),
			esc_html( wp_date( 'j. F Y', $ts ) )
		) . '</p>'
		: '';

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'nabu-event-details' ) );

	return sprintf(
		'<div %1$s>%5$s<dl class="nabu-event-details__list">%2$s</dl>%4$s<script type="application/ld+json">%3$s</script></div>',
		$wrapper,
		$html,
		$d['ld'],
		$map_html,
		$past
	);
}

/**
 * Register the event blocks.
 */
function nabu_register_event_blocks() {
	register_block_type(
		'nabu/events',
		array(
			'api_version'     => 3,
			'title'           => __( 'NABU Termine', 'nabu-unofficial' ),
			'category'        => 'theme',
			'icon'            => 'calendar-alt',
			'description'     => __( 'Liste der nächsten Termine der Gruppe, sortiert nach Beginn.', 'nabu-unofficial' ),
			'editor_script'   => 'nabu-blocks-editor',
			'attributes'      => array(
				'count'        => array(
					'type'    => 'number',
					'default' => 4,
				),
				'headingLevel' => array(
					'type'    => 'number',
					'default' => 3,
				),
				'range'        => array(
					'type'    => 'string',
					'default' => 'upcoming',
				),
				'showExcerpt'  => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'emptyText'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'layout'       => array(
					'type'    => 'string',
					'default' => 'list',
				),
			),
			'supports'        => array(
				'html'   => false,
				'align'  => array( 'wide' ),
				'anchor' => true,
			),
			'render_callback' => 'nabu_render_events',
		)
	);

	register_block_type(
		'nabu/event-details',
		array(
			'api_version'     => 3,
			'title'           => __( 'NABU Termindetails', 'nabu-unofficial' ),
			'category'        => 'theme',
			'icon'            => 'calendar',
			'description'     => __( 'Beginn, Ende, Ort und Treffpunkt des aufgerufenen Termins – für die Terminseite.', 'nabu-unofficial' ),
			'editor_script'   => 'nabu-blocks-editor',
			'uses_context'    => array( 'postId', 'postType' ),
			'attributes'      => array(
				'layout' => array(
					'type'    => 'string',
					'default' => 'box',
				),
			),
			'supports'        => array(
				'html'   => false,
				'anchor' => true,
			),
			'render_callback' => 'nabu_render_event_details',
		)
	);
}
add_action( 'init', 'nabu_register_event_blocks' );
