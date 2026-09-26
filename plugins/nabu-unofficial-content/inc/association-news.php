<?php
/**
 * News from the federal and the state association (German UI: "Neues vom NABU").
 *
 * Both publish an RSS feed with a teaser image per item. The server reads the
 * feeds, keeps the result for a few hours and stores the teaser images in the
 * uploads folder, so a visitor's browser only ever talks to this site — no
 * request to nabu.de, no consent needed. The links lead to the articles on
 * nabu.de, which carry the credit; the tiles show none.
 *
 * The state association's feed also carries topic pages and evergreen
 * guides. Its real news live under /news/, so that feed can be narrowed to
 * those.
 *
 * The theme renders the cards (nabu/association-news); this file only
 * supplies the data, and the theme shows nothing without it.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How long a fetched feed counts as fresh.
 */
const NABU_NEWS_TTL = 3 * HOUR_IN_SECONDS;

/**
 * Feed and name of a source.
 *
 * @param string $source "federal" or "state".
 * @return array{name:string, url:string, feed:string}|null Null when the group has no state association set.
 */
function nabu_association_source( $source ) {
	if ( 'federal' === $source ) {
		return array(
			'name' => __( 'NABU bundesweit', 'nabu-unofficial-content' ),
			'url'  => 'https://www.nabu.de/news/',
			'feed' => 'https://www.nabu.de/rssfeed.php',
		);
	}

	$associations = nabu_group_state_associations();
	$code         = nabu_group_get( 'state_association' );

	// The LBV in Bavaria runs a different system without this feed.
	if ( 'state' !== $source || ! isset( $associations[ $code ] ) || 'by' === $code ) {
		return null;
	}

	return array(
		'name' => $associations[ $code ]['name'],
		'url'  => $associations[ $code ]['url'] . '/',
		'feed' => $associations[ $code ]['url'] . '/rssfeed.php',
	);
}

/**
 * The latest items of a source, newest first.
 *
 * Served from the cache. When it has gone stale, the last good result is
 * served and a refresh is queued for the background, so no visitor waits for
 * nabu.de. Only the very first request fetches while the page renders.
 *
 * @param string $source    "federal" or "state".
 * @param int    $count     Number of items.
 * @param bool   $news_only Only items below /news/ (falls back to all when that leaves fewer than two).
 * @return array<int, array{title:string, link:string, date:int, excerpt:string, image:string, image_small?:string, image_width?:int, alt:string}>
 */
function nabu_association_news( $source, $count = 4, $news_only = false ) {
	if ( ! nabu_association_source( $source ) ) {
		return array();
	}

	$stored = get_option( 'nabu_news_' . $source );

	if ( ! is_array( $stored ) || ! isset( $stored['items'] ) ) {
		$stored = nabu_association_news_refresh( $source );
	} elseif ( $stored['fetched'] + NABU_NEWS_TTL < time() && ! wp_next_scheduled( 'nabu_association_news_refresh', array( $source ) ) ) {
		wp_schedule_single_event( time(), 'nabu_association_news_refresh', array( $source ) );
	}

	$items = (array) $stored['items'];

	if ( $news_only ) {
		$news = array_values( array_filter( $items, static fn( $item ) => false !== strpos( (string) wp_parse_url( $item['link'], PHP_URL_PATH ), '/news/' ) ) );
		if ( count( $news ) >= 2 ) {
			$items = $news;
		}
	}

	return array_slice( $items, 0, max( 1, (int) $count ) );
}

/**
 * Fetch a feed, store its images locally and keep the result.
 *
 * A failed fetch, or a feed without readable items, keeps the previous
 * result: the section would rather show last week's news than disappear
 * because nabu.de had a bad moment.
 *
 * @param string $source "federal" or "state".
 * @return array{fetched:int, items:array}
 */
function nabu_association_news_refresh( $source ) {
	$previous = get_option( 'nabu_news_' . $source );
	$previous = is_array( $previous ) && isset( $previous['items'] ) ? $previous : array( 'fetched' => 0, 'items' => array() );
	$config   = nabu_association_source( $source );

	if ( ! $config ) {
		return $previous;
	}

	$response = wp_safe_remote_get( $config['feed'], array( 'timeout' => 10 ) );
	$body     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
	$items    = 200 === (int) wp_remote_retrieve_response_code( $response ) ? nabu_association_news_parse( $body ) : array();

	if ( ! $items ) {
		// Try again at the next interval, not on every page view.
		$previous['fetched'] = time();
		update_option( 'nabu_news_' . $source, $previous, false );
		return $previous;
	}

	usort( $items, static fn( $a, $b ) => $b['date'] <=> $a['date'] );
	$items = array_slice( $items, 0, 12 );

	foreach ( $items as $i => $item ) {
		$items[ $i ]['image']       = $item['image'] ? nabu_association_news_image( $item['image'], $config['feed'] ) : '';
		$items[ $i ]['image_small'] = $items[ $i ]['image'] ? nabu_association_news_image_small( $items[ $i ]['image'] ) : '';
		$items[ $i ]['image_width'] = $items[ $i ]['image_small'] ? nabu_association_news_image_width( $items[ $i ]['image'] ) : 0;
	}

	$result = array(
		'fetched' => time(),
		'items'   => $items,
	);
	update_option( 'nabu_news_' . $source, $result, false );
	nabu_association_news_cleanup();

	return $result;
}
add_action( 'nabu_association_news_refresh', 'nabu_association_news_refresh' );

/**
 * Read the items out of an RSS document.
 *
 * The teaser sits in content:encoded as HTML: an image whose title carries
 * caption and credit ("Nachtigall - Foto: Mathias Schäf"), then the text and
 * a "mehr ..." link.
 *
 * @param string $xml Feed body.
 * @return array<int, array{title:string, link:string, date:int, excerpt:string, image:string, alt:string}>
 */
function nabu_association_news_parse( $xml ) {
	$previous = libxml_use_internal_errors( true );
	$doc      = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );
	libxml_use_internal_errors( $previous );

	if ( ! $doc || ! isset( $doc->channel->item ) ) {
		return array();
	}

	$items = array();

	foreach ( $doc->channel->item as $entry ) {
		$link = esc_url_raw( trim( (string) $entry->link ) );
		if ( ! $link ) {
			continue;
		}

		$html  = (string) $entry->children( 'http://purl.org/rss/1.0/modules/content/' )->encoded;
		$image = '';
		$title = '';

		$tags = new WP_HTML_Tag_Processor( $html );
		if ( $tags->next_tag( 'img' ) ) {
			$image = (string) $tags->get_attribute( 'src' );
			$title = (string) $tags->get_attribute( 'title' );
		}

		// "Nachtigall - Foto: Mathias Schäf": the caption is the alt text.
		$pos = stripos( $title, 'Foto' );
		$alt = false === $pos ? $title : rtrim( substr( $title, 0, $pos ), " -–.\t" );

		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( preg_replace( '#<a\b[^>]*>\s*mehr\s*\.\.\.\s*</a>#i', '', $html ) ) ) );

		$items[] = array(
			'title'   => sanitize_text_field( html_entity_decode( (string) $entry->title, ENT_QUOTES, 'UTF-8' ) ),
			'link'    => $link,
			'date'    => (int) strtotime( (string) $entry->pubDate ),
			'excerpt' => sanitize_text_field( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ),
			'image'   => $image,
			'alt'     => sanitize_text_field( html_entity_decode( $alt, ENT_QUOTES, 'UTF-8' ) ),
		);
	}

	return $items;
}

/**
 * Folder for the stored teaser images.
 *
 * @return array{dir:string, url:string}
 */
function nabu_association_news_dir() {
	$uploads = wp_upload_dir( null, false );

	return array(
		'dir' => trailingslashit( $uploads['basedir'] ) . 'nabu-verband',
		'url' => trailingslashit( $uploads['baseurl'] ) . 'nabu-verband',
	);
}

/**
 * Store a teaser image locally and return its URL.
 *
 * Only images from the feed's own host (or another nabu.de host) are taken,
 * and only real JPEG, PNG or WebP files.
 *
 * @param string $url  Image URL from the feed.
 * @param string $feed Feed URL, for the host check.
 * @return string Local URL, empty on failure.
 */
function nabu_association_news_image( $url, $feed ) {
	$host      = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$feed_host = strtolower( (string) wp_parse_url( $feed, PHP_URL_HOST ) );

	if ( '' === $host || ( $host !== $feed_host && ! preg_match( '/(^|\.)nabu\.de$/', $host ) ) ) {
		return '';
	}

	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$ext = 'jpeg' === $ext ? 'jpg' : $ext;
	if ( ! in_array( $ext, array( 'jpg', 'png', 'webp' ), true ) ) {
		return '';
	}

	$store = nabu_association_news_dir();
	$name  = md5( $url ) . '.' . $ext;
	$path  = $store['dir'] . '/' . $name;

	if ( file_exists( $path ) ) {
		return $store['url'] . '/' . $name;
	}

	if ( ! wp_mkdir_p( $store['dir'] ) ) {
		return '';
	}

	$response = wp_safe_remote_get( $url, array( 'timeout' => 10 ) );
	$body     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );

	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || '' === $body || strlen( $body ) > 5 * MB_IN_BYTES ) {
		return '';
	}

	$info = @getimagesizefromstring( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! $info || ! in_array( $info['mime'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		return '';
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( false === file_put_contents( $path, $body ) ) {
		return '';
	}

	return $store['url'] . '/' . $name;
}

/**
 * Width of the small copy of a teaser image. The feeds deliver 680px; a tile
 * is about 220px wide, so 440 serves double density.
 */
const NABU_NEWS_IMAGE_SMALL = 440;

/**
 * A smaller copy of a stored teaser image, beside it, for the tiles in a row.
 * The original stays for a tile alone on a phone.
 *
 * @param string $url Local URL of the stored image.
 * @return string URL of the copy; empty if the image is not wider or cannot be read.
 */
function nabu_association_news_image_small( $url ) {
	$store = nabu_association_news_dir();
	$name  = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$ext   = pathinfo( $name, PATHINFO_EXTENSION );
	$small = pathinfo( $name, PATHINFO_FILENAME ) . '-' . NABU_NEWS_IMAGE_SMALL . '.' . $ext;

	if ( file_exists( $store['dir'] . '/' . $small ) ) {
		return $store['url'] . '/' . $small;
	}

	$editor = wp_get_image_editor( $store['dir'] . '/' . $name );
	if ( is_wp_error( $editor ) || (int) $editor->get_size()['width'] <= NABU_NEWS_IMAGE_SMALL ) {
		return '';
	}

	if ( is_wp_error( $editor->resize( NABU_NEWS_IMAGE_SMALL, null ) ) || is_wp_error( $editor->save( $store['dir'] . '/' . $small ) ) ) {
		return '';
	}

	return $store['url'] . '/' . $small;
}

/**
 * Width of a stored teaser image.
 *
 * @param string $url Local URL of the stored image.
 * @return int 0 if it cannot be read.
 */
function nabu_association_news_image_width( $url ) {
	$size = wp_getimagesize( nabu_association_news_dir()['dir'] . '/' . basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );

	return $size ? (int) $size[0] : 0;
}

/**
 * Remove stored images that no source refers to any more.
 */
function nabu_association_news_cleanup() {
	$keep = array();
	foreach ( array( 'federal', 'state' ) as $source ) {
		$stored = get_option( 'nabu_news_' . $source );
		foreach ( is_array( $stored ) ? (array) ( $stored['items'] ?? array() ) : array() as $item ) {
			foreach ( array( 'image', 'image_small' ) as $field ) {
				if ( ! empty( $item[ $field ] ) ) {
					$keep[ basename( (string) $item[ $field ] ) ] = true;
				}
			}
		}
	}

	foreach ( (array) glob( nabu_association_news_dir()['dir'] . '/*' ) as $file ) {
		if ( is_file( $file ) && ! isset( $keep[ basename( $file ) ] ) ) {
			wp_delete_file( $file );
		}
	}
}

/**
 * Drop the stored state feed when the group switches its state association.
 *
 * @param mixed $old Previous option value.
 * @param mixed $new New option value.
 */
function nabu_association_news_reset( $old, $new ) {
	if ( ( $old['state_association'] ?? '' ) !== ( $new['state_association'] ?? '' ) ) {
		delete_option( 'nabu_news_state' );
		nabu_association_news_cleanup();
	}
}
add_action( 'update_option_nabu_group', 'nabu_association_news_reset', 10, 2 );
