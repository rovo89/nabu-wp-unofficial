<?php
/**
 * Reports on events.
 *
 * A report is an ordinary post that names its event; the companion plugin
 * sends every link to it to the event page. There the block
 * nabu/event-report shows it above the announcement — it sits in the event
 * template, editors never insert it — and the announcement folds away under
 * "Ankündigung".
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Without the plugin there are no reports.
if ( ! function_exists( 'nabu_event_reports' ) ) {
	/**
	 * Published reports on an event.
	 *
	 * @param int $event_id Event ID.
	 * @return WP_Post[]
	 */
	function nabu_event_reports( $event_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return array();
	}
}

/**
 * Whether an event is over: its last day lies before today — the same cut as
 * the "past" range of nabu/events.
 *
 * @param WP_Post $post Event.
 * @return bool
 */
function nabu_event_is_past( $post ) {
	$end   = (string) get_post_meta( $post->ID, '_nabu_event_end', true );
	$start = (string) get_post_meta( $post->ID, '_nabu_event_start', true );
	$ts    = nabu_event_timestamp( $end ? $end : $start );

	return $ts && wp_date( 'Y-m-d', $ts ) < current_time( 'Y-m-d' );
}

/**
 * Render the reports on the event page (nabu/event-report).
 *
 * Each report with date, title, featured image and text. The title is left
 * out where it only repeats the event's, and the image where the report says
 * so. Without a report, nothing.
 *
 * @param array    $attributes Unused.
 * @param string   $content    Unused.
 * @param WP_Block $block      Block with context (postId).
 * @return string
 */
function nabu_render_event_report( $attributes, $content, $block ) {
	$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
	$event   = $post_id ? get_post( $post_id ) : null;

	if ( ! $event instanceof WP_Post || 'nabu_event' !== $event->post_type ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="nabu-events__empty">' . esc_html__( 'Bericht zum Termin – erscheint hier, sobald ein Beitrag mit „Bericht zu Termin“ veröffentlicht ist.', 'nabu-unofficial' ) . '</p>';
		}
		return '';
	}

	$reports = nabu_event_reports( $event->ID );
	if ( ! $reports ) {
		return '';
	}

	// Render each report as the current post, so blocks that read the global
	// post (image credits, galleries) see the report, not the event.
	global $post;
	$previous = $post;

	$event_title = nabu_comparable_title( get_the_title( $event ) );

	$html = '';
	foreach ( $reports as $i => $report ) {
		$post = $report; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		$title = '';
		if ( nabu_comparable_title( get_the_title( $report ) ) !== $event_title ) {
			$title = '<h2 class="wp-block-heading nabu-event-report__title">' . esc_html( get_the_title( $report ) ) . '</h2>';
		}

		$image = '';
		if ( has_post_thumbnail( $report ) && ! ( function_exists( 'nabu_image_hidden' ) && nabu_image_hidden( $report->ID ) ) ) {
			$image = nabu_append_image_credit(
				'<figure class="wp-block-post-featured-image nabu-event-report__image">' . get_the_post_thumbnail( $report, 'large' ) . '</figure>',
				(int) get_post_thumbnail_id( $report )
			);
		}

		$html .= sprintf(
			'<section class="nabu-event-report"%1$s>
				<p class="nabu-kicker">%2$s</p>
				%3$s
				%4$s
				<div class="nabu-event-report__content">%5$s</div>
			</section>',
			0 === $i ? ' id="bericht"' : '',
			sprintf(
				/* translators: %s: date of the report. */
				esc_html__( 'Bericht vom %s', 'nabu-unofficial' ),
				'<time datetime="' . esc_attr( get_the_date( 'Y-m-d', $report ) ) . '">' . esc_html( get_the_date( 'j. F Y', $report ) ) . '</time>'
			),
			$title,
			$image,
			apply_filters( 'the_content', $report->post_content ) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		);
	}

	$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	if ( $post ) {
		setup_postdata( $post );
	}

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'nabu-event-reports' ) );

	return sprintf( '<div %1$s>%2$s</div>', $wrapper, $html );
}

/**
 * A title as the reader sees it, for comparing two of them.
 *
 * @param string $title Title as get_the_title() returns it.
 * @return string
 */
function nabu_comparable_title( $title ) {
	return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' ) ) ) );
}

/**
 * Fold the announcement away once there is a report.
 *
 * In the event template the group nabu-event-announcement holds the event's
 * image, text and details. With a report above it, the group becomes a closed
 * <details> under the heading "Ankündigung".
 *
 * @param string $block_content Rendered markup.
 * @param array  $block         Block data.
 * @return string
 */
function nabu_event_announcement( $block_content, $block ) {
	if ( ! preg_match( '/(^|\s)nabu-event-announcement(\s|$)/', $block['attrs']['className'] ?? '' ) ) {
		return $block_content;
	}

	$event_id = get_the_ID();
	if ( ! $event_id || 'nabu_event' !== get_post_type( $event_id ) || ! nabu_event_reports( $event_id ) ) {
		return $block_content;
	}

	$summary = '<summary><h2 class="wp-block-heading">' . esc_html__( 'Ankündigung', 'nabu-unofficial' ) . '</h2></summary>';

	$block_content = (string) preg_replace( '/^\s*<div\b([^>]*)>/', '<details$1>' . $summary, $block_content, 1 );

	return (string) preg_replace( '#</div>\s*$#', '</details>', $block_content, 1 );
}
add_filter( 'render_block_core/group', 'nabu_event_announcement', 10, 2 );

/**
 * Register nabu/event-report.
 */
function nabu_register_event_report_block() {
	register_block_type(
		'nabu/event-report',
		array(
			'api_version'     => 3,
			'title'           => __( 'NABU Bericht zum Termin', 'nabu-unofficial' ),
			'category'        => 'theme',
			'icon'            => 'media-document',
			'description'     => __( 'Die Berichte zum aufgerufenen Termin, über der Ankündigung – für die Terminseite.', 'nabu-unofficial' ),
			'editor_script'   => 'nabu-blocks-editor',
			'uses_context'    => array( 'postId', 'postType' ),
			'supports'        => array(
				'html'     => false,
				'inserter' => false,
			),
			'render_callback' => 'nabu_render_event_report',
		)
	);
}
add_action( 'init', 'nabu_register_event_report_block' );
