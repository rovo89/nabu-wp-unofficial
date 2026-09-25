<?php
/**
 * Import content written by export-content.php.
 *
 *   wp eval-file /scripts/import-content.php [/path/to/content-export]
 *
 * Creates or updates (by slug and type) the exported posts, uploads the
 * images into the media library once and rewrites the image references in
 * the content. Parents are resolved by slug, so parents must be part of the
 * export or already exist on the target.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Where the export sits.
 *
 * A function, not a top-level variable: `wp eval-file` runs this file inside
 * a function, so `global $export_dir` in a helper below would find nothing.
 *
 * @param string|null $set Path to remember on the first call.
 * @return string
 */
function nabu_import_dir( $set = null ) {
	static $dir = null;

	if ( null !== $set ) {
		$dir = rtrim( $set, '/' );
	}

	if ( null === $dir ) {
		$dir = is_dir( '/content-export' ) ? '/content-export' : __DIR__ . '/../content-export';
	}

	return $dir;
}

nabu_import_dir( ! empty( $args[0] ) ? $args[0] : null );

$file = nabu_import_dir() . '/content.json';
if ( ! file_exists( $file ) ) {
	WP_CLI::error( "No $file" );
}

$data = json_decode( (string) file_get_contents( $file ), true );
if ( ! is_array( $data ) || empty( $data['items'] ) ) {
	WP_CLI::error( 'Nothing to import.' );
}

/**
 * Upload an exported image once; later calls return the existing attachment.
 *
 * @param string $key   Image key from the export.
 * @param array  $image Image data.
 * @return int Attachment ID, 0 on failure.
 */
function nabu_import_image( $key, $image ) {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_nabu_import_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}

	$path = nabu_import_dir() . '/images/' . $key;
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Image missing: $key" );
		return 0;
	}

	$tmp = wp_tempnam( $key );
	copy( $path, $tmp );

	$id = media_handle_sideload(
		array(
			'name'     => preg_replace( '/^\d+-/', '', $key ),
			'tmp_name' => $tmp,
		),
		0,
		$image['title'] ?? ''
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		WP_CLI::warning( "Upload failed ($key): " . $id->get_error_message() );
		return 0;
	}

	update_post_meta( $id, '_nabu_import_key', $key );
	if ( ! empty( $image['alt'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $image['alt'] );
	}
	if ( ! empty( $image['credit'] ) ) {
		update_post_meta( $id, '_nabu_image_credit', $image['credit'] );
	}
	if ( ! empty( $image['caption'] ) ) {
		wp_update_post( array( 'ID' => $id, 'post_excerpt' => $image['caption'] ) );
	}

	return (int) $id;
}

$image_ids = array();
foreach ( (array) $data['images'] as $key => $image ) {
	$image_ids[ $key ] = nabu_import_image( $key, $image );
}
WP_CLI::log( count( array_filter( $image_ids ) ) . ' images in the media library.' );

/**
 * Find a post by slug and type, whatever its status. The statuses are named
 * one by one: a query by name with 'any' drops drafts under WP-CLI.
 *
 * @param string $type Post type.
 * @param string $slug Slug.
 * @return WP_Post|null
 */
function nabu_import_find( $type, $slug ) {
	$found = get_posts(
		array(
			'post_type'        => $type,
			'name'             => $slug,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'   => 1,
			'suppress_filters' => false,
		)
	);
	return $found ? $found[0] : null;
}

// Parents first: sort so that an item comes after the item it hangs under.
$items  = $data['items'];
$sorted = array();
$by     = array();
foreach ( $items as $item ) {
	$by[ $item['type'] . ':' . $item['slug'] ] = $item;
}
$place = function ( $item ) use ( &$place, &$sorted, &$by ) {
	$key = $item['type'] . ':' . $item['slug'];
	if ( isset( $sorted[ $key ] ) ) {
		return;
	}
	$parent_key = $item['type'] . ':' . $item['parent_slug'];
	if ( '' !== $item['parent_slug'] && isset( $by[ $parent_key ] ) ) {
		$place( $by[ $parent_key ] );
	}
	$sorted[ $key ] = $item;
};
foreach ( $items as $item ) {
	$place( $item );
}

$created = 0;
$updated = 0;
$reports = array();

foreach ( $sorted as $item ) {
	$content = (string) $item['content'];
	foreach ( $image_ids as $key => $id ) {
		if ( ! $id ) {
			continue;
		}
		$content = str_replace( '"id":"IMAGE:' . $key . '"', '"id":' . $id, $content );
		$content = str_replace( 'wp-image-IMAGE:' . $key, 'wp-image-' . $id, $content );
		$content = str_replace( 'IMAGEURL:' . $key, (string) wp_get_attachment_url( $id ), $content );
	}

	$parent = 0;
	if ( '' !== $item['parent_slug'] ) {
		$parent_post = nabu_import_find( $item['type'], $item['parent_slug'] );
		if ( $parent_post ) {
			$parent = (int) $parent_post->ID;
		} else {
			WP_CLI::warning( "Parent \"{$item['parent_slug']}\" of \"{$item['slug']}\" not found — placed on the top level." );
		}
	}

	$post_data = array(
		'post_type'     => $item['type'],
		'post_name'     => $item['slug'],
		'post_title'    => $item['title'],
		'post_status'   => $item['status'],
		'post_date'     => $item['date'],
		'post_date_gmt' => get_gmt_from_date( $item['date'] ),
		'post_excerpt'  => $item['excerpt'],
		'post_content'  => $content,
		'post_parent'   => $parent,
		'menu_order'    => (int) $item['menu_order'],
		'post_author'   => 1,
	);

	$existing = nabu_import_find( $item['type'], $item['slug'] );
	if ( $existing ) {
		$post_data['ID']        = $existing->ID;
		$post_data['edit_date'] = true;
		$id                     = wp_update_post( $post_data, true );
		++$updated;
	} else {
		$id = wp_insert_post( $post_data, true );
		++$created;
	}
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "Could not write \"{$item['slug']}\": " . $id->get_error_message() );
		continue;
	}

	foreach ( (array) $item['meta'] as $meta_key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $id, $meta_key );
		} else {
			update_post_meta( $id, $meta_key, $value );
		}
	}

	if ( ! empty( $item['report_of'] ) ) {
		$reports[ $id ] = $item['report_of'];
	}

	if ( ! empty( $item['featured'] ) && ! empty( $image_ids[ $item['featured'] ] ) ) {
		set_post_thumbnail( $id, $image_ids[ $item['featured'] ] );
	}

	if ( 'post' === $item['type'] && ! empty( $item['categories'] ) ) {
		$term_ids = array();
		foreach ( (array) $item['categories'] as $name ) {
			$term = term_exists( $name, 'category' );
			if ( ! $term ) {
				$term = wp_insert_term( $name, 'category' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_ids[] = (int) $term['term_id'];
			}
		}
		if ( $term_ids ) {
			wp_set_post_categories( $id, $term_ids );
		}
	}

	WP_CLI::log( "  {$item['type']} \"{$item['slug']}\"" );
}

// Reports once every event is in place, whatever the order of the export.
foreach ( $reports as $id => $event_slug ) {
	$event = nabu_import_find( 'nabu_event', $event_slug );
	if ( $event ) {
		update_post_meta( $id, '_nabu_report_event', (int) $event->ID );
	} else {
		WP_CLI::warning( "Event \"$event_slug\" of report $id not found." );
	}
}

WP_CLI::success( "$created created, $updated updated." );
