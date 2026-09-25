<?php
/**
 * Export hand-made content of one instance for import into another.
 *
 *   wp eval-file /scripts/export-content.php [ids...]
 *
 * Without IDs: every post, page and event that does not carry the demo
 * marker `_nabu_demo`. Writes content-export/content.json plus the image
 * files into content-export/images/. The counterpart is import-content.php.
 *
 * What travels: title, slug, type, status, date, excerpt, content, parent
 * (by slug), menu_order, page template, categories, all `_nabu_*` meta
 * (event fields, subtitle), the event a report belongs to (by slug), Nested
 * Pages nav status and title, the featured image and every image referenced
 * by a core/image block — each with alt text, caption title and credit.
 * Attachment IDs and URLs in the content are replaced on import.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

/**
 * Where the export goes.
 *
 * Not a top-level variable: `wp eval-file` runs this file inside a function,
 * so nothing declared here is global and `global $x` in a function below
 * would silently find nothing. Everything shared therefore travels through
 * function statics.
 *
 * @return string
 */
function nabu_export_dir() {
	static $dir = null;

	if ( null === $dir ) {
		if ( defined( 'NABU_EXPORT_DIR' ) ) {
			$dir = NABU_EXPORT_DIR;
		} elseif ( is_dir( '/content-export' ) ) {
			$dir = '/content-export';
		} else {
			$dir = __DIR__ . '/../content-export';
		}
	}

	return $dir;
}

/**
 * Registry of the images the export collected.
 *
 * @param string|null $key  Image key, or null to read the whole list.
 * @param array|null  $data Image data to store.
 * @return array|null The whole list when called without a key.
 */
function nabu_export_images( $key = null, $data = null ) {
	static $images = array();

	if ( null === $key ) {
		return $images;
	}

	if ( null !== $data ) {
		$images[ $key ] = $data;
	}

	return isset( $images[ $key ] ) ? $images[ $key ] : null;
}

if ( ! is_dir( nabu_export_dir() . '/images' ) && ! mkdir( nabu_export_dir() . '/images', 0775, true ) ) {
	WP_CLI::error( 'Cannot create ' . nabu_export_dir() . '/images' );
}

$ids = array_map( 'intval', array_filter( (array) $args, 'is_numeric' ) );

if ( ! $ids ) {
	$ids = get_posts(
		array(
			'post_type'      => array( 'post', 'page', 'nabu_event' ),
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_nabu_demo',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
}

$items = array();

/**
 * Record an attachment for export and copy its file.
 *
 * @param int $attachment_id Attachment ID.
 * @return string Key in the image list, empty on failure.
 */
function nabu_export_image( $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	if ( ! $attachment_id ) {
		return '';
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) {
		WP_CLI::warning( "Attachment $attachment_id has no file." );
		return '';
	}

	$key = $attachment_id . '-' . basename( $file );
	if ( null !== nabu_export_images( $key ) ) {
		return $key;
	}

	if ( ! copy( $file, nabu_export_dir() . '/images/' . $key ) ) {
		WP_CLI::warning( "Could not copy the file of attachment $attachment_id." );
		return '';
	}

	$post = get_post( $attachment_id );

	nabu_export_images(
		$key,
		array(
			'title'   => $post ? $post->post_title : '',
			'alt'     => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption' => $post ? $post->post_excerpt : '',
			'credit'  => (string) get_post_meta( $attachment_id, '_nabu_image_credit', true ),
			'url'     => (string) wp_get_attachment_url( $attachment_id ),
		)
	);

	return $key;
}

foreach ( $ids as $id ) {
	$post = get_post( $id );
	if ( ! $post ) {
		WP_CLI::warning( "No post with ID $id." );
		continue;
	}

	$content = (string) $post->post_content;

	// Every core/image block: remember the attachment, replace the ID in the
	// block markup with a placeholder the importer resolves.
	$image_keys = array();
	$content    = (string) preg_replace_callback(
		'#<!-- wp:image (\{[^\n]*?"id":(\d+)[^\n]*?\}) -->#',
		static function ( $m ) use ( &$image_keys ) {
			$key = nabu_export_image( (int) $m[2] );
			if ( '' === $key ) {
				return $m[0];
			}
			$image_keys[ (int) $m[2] ] = $key;
			return str_replace( '"id":' . $m[2], '"id":"IMAGE:' . $key . '"', $m[0] );
		},
		$content
	);
	foreach ( $image_keys as $old_id => $key ) {
		$image   = nabu_export_images( $key );
		$content = str_replace( 'wp-image-' . $old_id, 'wp-image-IMAGE:' . $key, $content );

		if ( ! $image || ! $image['url'] ) {
			continue;
		}

		// Not str_replace on the stored URL: an editor who worked through the
		// LAN address has that host in the markup, while wp_get_attachment_url()
		// reports the current one. Match the file name under any host instead.
		$content = (string) preg_replace(
			'#https?://[^"\'\s]*?/' . preg_quote( basename( $image['url'] ), '#' ) . '#',
			'IMAGEURL:' . $key,
			$content
		);
	}

	// A report names its event by ID, which is different on the target.
	$report_event = (int) get_post_meta( $id, '_nabu_report_event', true );

	$meta = array();
	foreach ( get_post_meta( $id ) as $meta_key => $values ) {
		if ( '_nabu_report_event' === $meta_key ) {
			continue;
		}
		if ( 0 === strpos( $meta_key, '_nabu_' ) && ! in_array( $meta_key, array( '_nabu_demo', '_nabu_content_hash', '_nabu_scaffold', '_nabu_demo_source', '_nabu_sample_source' ), true ) ) {
			$meta[ $meta_key ] = (string) $values[0];
		}
		if ( in_array( $meta_key, array( '_np_nav_status', '_np_nav_title', '_wp_page_template' ), true ) ) {
			$meta[ $meta_key ] = (string) $values[0];
		}
	}

	$featured = get_post_thumbnail_id( $id );

	$items[] = array(
		'type'        => $post->post_type,
		'slug'        => $post->post_name,
		'title'       => $post->post_title,
		'status'      => $post->post_status,
		'date'        => $post->post_date,
		'excerpt'     => $post->post_excerpt,
		'content'     => $content,
		'parent_slug' => $post->post_parent ? get_post_field( 'post_name', $post->post_parent ) : '',
		'menu_order'  => (int) $post->menu_order,
		'categories'  => 'post' === $post->post_type ? wp_get_post_categories( $id, array( 'fields' => 'names' ) ) : array(),
		'meta'        => $meta,
		'report_of'   => $report_event ? (string) get_post_field( 'post_name', $report_event ) : '',
		'featured'    => $featured ? nabu_export_image( $featured ) : '',
	);

	WP_CLI::log( "  $post->post_type \"$post->post_name\"" );
}

$images = nabu_export_images();

file_put_contents(
	nabu_export_dir() . '/content.json',
	wp_json_encode(
		array(
			'exported' => gmdate( 'c' ),
			'source'   => home_url( '/' ),
			'items'    => $items,
			'images'   => $images,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	)
);

WP_CLI::success( count( $items ) . ' items and ' . count( $images ) . ' images written to ' . nabu_export_dir() );
