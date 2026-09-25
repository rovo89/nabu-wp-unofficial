<?php
/**
 * Demo content for the test instance.
 *
 *   wp eval-file /scripts/demo-content.php
 *
 * Runs scripts/scaffold.php, then fills the site with activities, protected
 * areas, events, news and images — from the sample data of a real NABU group
 * (scripts/sample-data/, see fetch-sample-data.py) where it was fetched,
 * otherwise from generated placeholders.
 *
 * Idempotent: entries are found by slug and updated, and a post edited by
 * hand since the last run keeps its text (nabu_demo_post()). Everything
 * created here carries the meta `_nabu_demo`; demo entries a run no longer
 * produces are removed at the end.
 *
 * All of it is test material and must never be published.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

require __DIR__ . '/scaffold.php';

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/* ------------------------------------------------------------------ *
 * Helpers
 *
 * `wp eval-file` runs this file inside a function, so its top-level
 * variables are not global; shared state lives in function statics.
 * ------------------------------------------------------------------ */

/**
 * Remember a post this run created or updated, or read the list.
 *
 * @param int|null $id Post ID to add.
 * @return int[]
 */
function nabu_demo_created( $id = null ) {
	static $ids = array();

	if ( null !== $id ) {
		$ids[] = (int) $id;
	}

	return $ids;
}

/**
 * The fetched sample data, or null.
 *
 * @return array|null
 */
function nabu_sample_data() {
	static $data = false;

	if ( false === $data ) {
		$path = '/scripts/sample-data/content.json';
		$raw  = file_exists( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;
		$data = is_array( $raw ) ? $raw : null;
	}

	return $data;
}

/**
 * Put a file into the media library once; later runs get the same
 * attachment back.
 *
 * @param string $path   Absolute path inside the container.
 * @param string $key    Identifies the file across runs.
 * @param string $alt    Alternative text.
 * @param string $credit Image credit, e.g. "Foto: NABU/H. May".
 * @param string $title  Title in the media library.
 * @return int Attachment ID, 0 on failure.
 */
function nabu_demo_upload( $path, $key, $alt, $credit, $title ) {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'meta_key'       => '_nabu_demo_source',
			'meta_value'     => $key,
			'fields'         => 'ids',
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}

	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Image missing: $path" );
		return 0;
	}

	$tmp = wp_tempnam( basename( $path ) );
	copy( $path, $tmp );

	$id = media_handle_sideload(
		array(
			'name'     => basename( $path ),
			'tmp_name' => $tmp,
		),
		0,
		$title
	);

	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		WP_CLI::warning( 'Upload failed (' . basename( $path ) . '): ' . $id->get_error_message() );
		return 0;
	}

	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, '_nabu_demo_source', $key );
	if ( '' !== $credit ) {
		update_post_meta( $id, '_nabu_image_credit', $credit );
	}

	return (int) $id;
}

/**
 * Put a placeholder image of the theme into the media library once.
 *
 * @param string $file   File name in assets/images.
 * @param string $alt    Alternative text.
 * @param string $title  Title in the media library.
 * @return int Attachment ID, 0 on failure.
 */
function nabu_demo_image( $file, $alt, $title ) {
	return nabu_demo_upload( get_template_directory() . '/assets/images/' . $file, $file, $alt, 'Foto: Platzhalter', $title );
}

/**
 * Replace the SAMPLEIMAGE:<file> placeholders of the sample data with images
 * from the media library — with their attachment ID, which earns them a
 * srcset and keeps the block valid in the editor.
 *
 * @param string[] $blocks Block markup from the sample data.
 * @param int[]    $map    File name => attachment ID.
 * @return string
 */
function nabu_resolve_image_blocks( $blocks, $map ) {
	return (string) preg_replace_callback(
		'#<!-- wp:image (\{.*?\}) -->\s*<figure class="wp-block-image size-large"><img src="SAMPLEIMAGE:([^"]+)" alt="([^"]*)"/>(.*?)</figure>\s*<!-- /wp:image -->#s',
		static function ( $match ) use ( $map ) {
			$id = (int) ( $map[ $match[2] ] ?? 0 );
			if ( ! $id ) {
				return '';
			}

			$attributes       = json_decode( $match[1], true );
			$attributes       = is_array( $attributes ) ? $attributes : array();
			$attributes['id'] = $id;

			return sprintf(
				"<!-- wp:image %s -->\n<figure class=\"wp-block-image size-large\"><img src=\"%s\" alt=\"%s\" class=\"wp-image-%d\"/>%s</figure>\n<!-- /wp:image -->",
				wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				esc_url( (string) wp_get_attachment_url( $id ) ),
				esc_attr( $match[3] ),
				$id,
				$match[4]
			);
		},
		implode( "\n\n", (array) $blocks )
	);
}

/**
 * Pick an unused sample image whose caption shares a word with the title.
 *
 * @param string $title  Post title.
 * @param array  $images Additional images of the sample data.
 * @param int[]  $map    File name => attachment ID.
 * @param int[]  $taken  Attachment IDs already used; extended.
 * @return int Attachment ID, 0 if none matches.
 */
function nabu_matching_image( $title, $images, $map, &$taken ) {
	$words = array_filter(
		preg_split( '/[^\p{L}]+/u', mb_strtolower( $title ) ),
		static fn( $word ) => mb_strlen( $word ) > 4
	);

	foreach ( $images as $image ) {
		$id          = $map[ $image['file'] ] ?? 0;
		$description = mb_strtolower( ( $image['alt'] ?? '' ) . ' ' . ( $image['caption'] ?? '' ) );
		if ( ! $id || in_array( $id, $taken, true ) || '' === trim( $description ) ) {
			continue;
		}
		foreach ( $words as $word ) {
			if ( false !== mb_strpos( $description, $word ) ) {
				$taken[] = $id;
				return $id;
			}
		}
	}

	return 0;
}

/**
 * An excerpt from the first paragraph of some block markup.
 *
 * @param string $html  Block markup.
 * @param int    $words Maximum number of words.
 * @return string
 */
function nabu_excerpt_from_blocks( $html, $words = 26 ) {
	return preg_match( '#<p>(.*?)</p>#s', $html, $m ) ? wp_trim_words( wp_strip_all_tags( $m[1] ), $words, ' …' ) : '';
}

/**
 * Create a post, or update the one with its slug.
 *
 * Content and excerpt are written only while the post still holds exactly
 * what the last run wrote (`_nabu_content_hash`), so a post edited by hand
 * keeps its text; title, order, parent and date are updated regardless.
 * NABU_DEMO_FORCE=1 overwrites edited posts too.
 *
 * @param array $args wp_insert_post() arguments; post_type and post_name required.
 * @return int 0 on failure.
 */
function nabu_demo_post( $args ) {
	$existing = nabu_scaffold_find( $args['post_type'], $args['post_name'] );

	$data = wp_parse_args(
		$args,
		array(
			'post_status' => 'publish',
			'post_author' => 1,
		)
	);

	// An update keeps the old post_date_gmt unless it is given too, and the
	// publish-or-future decision reads that field.
	if ( ! empty( $data['post_date'] ) && empty( $data['post_date_gmt'] ) ) {
		$data['post_date_gmt'] = get_gmt_from_date( $data['post_date'] );
	}

	$writes_content = true;

	if ( $existing ) {
		$data['ID']        = $existing->ID;
		$data['edit_date'] = true;

		$stored = (string) get_post_meta( $existing->ID, '_nabu_content_hash', true );
		if ( '' !== $stored && md5( (string) $existing->post_content ) !== $stored && '1' !== getenv( 'NABU_DEMO_FORCE' ) ) {
			unset( $data['post_content'], $data['post_excerpt'] );
			$writes_content = false;
			WP_CLI::log( '  kept edited content of ' . $args['post_type'] . ' "' . $args['post_name'] . '"' );
		}

		$id = wp_update_post( $data, true );
	} else {
		$id = wp_insert_post( $data, true );
	}

	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( 'Could not create ' . $args['post_name'] . ': ' . $id->get_error_message() );
		return 0;
	}

	update_post_meta( (int) $id, '_nabu_demo', '1' );

	// The hash records what this script wrote, never what happens to be
	// there: taking the hash of edited text would let the next run overwrite it.
	if ( $writes_content ) {
		nabu_demo_record_content( (int) $id );
	}

	nabu_demo_created( (int) $id );

	return (int) $id;
}

/**
 * Record a post's content as what this script wrote (nabu_demo_post()).
 *
 * @param int $id Post ID.
 */
function nabu_demo_record_content( $id ) {
	update_post_meta( $id, '_nabu_content_hash', md5( (string) get_post_field( 'post_content', $id, 'raw' ) ) );
}

/**
 * The next image for a card: a sample teaser if any is left, else the
 * placeholder.
 *
 * @param int[] $teasers     Unused sample teasers; shortened.
 * @param int[] $taken       Attachment IDs already used; extended.
 * @param int   $placeholder Placeholder attachment ID.
 * @return int
 */
function nabu_demo_next_image( &$teasers, &$taken, $placeholder ) {
	$teasers = array_values( array_diff( $teasers, $taken ) );
	$image   = $teasers ? array_shift( $teasers ) : (int) $placeholder;
	if ( $image ) {
		$taken[] = $image;
	}

	return $image;
}

// A test instance is never indexed.
update_option( 'blog_public', 0 );

/* ------------------------------------------------------------------ *
 * Images
 * ------------------------------------------------------------------ */

$images = array(
	'streuobstwiese'  => nabu_demo_image( 'streuobstwiese.jpg', 'Alte Obstbäume auf einer Wiese am Ortsrand', 'Streuobstwiese' ),
	'amphibien'       => nabu_demo_image( 'amphibien.jpg', 'Feuchtbiotop mit Schilfgürtel', 'Amphibienbiotop' ),
	'nistkasten'      => nabu_demo_image( 'nistkasten.jpg', 'Nistkasten an einem Baumstamm', 'Nistkasten' ),
	'bachlauf'        => nabu_demo_image( 'bachlauf.jpg', 'Renaturierter Bachlauf mit Uferbewuchs', 'Bachlauf' ),
	'hecke'           => nabu_demo_image( 'hecke.jpg', 'Feldhecke zwischen zwei Äckern', 'Feldhecke' ),
	'wiesenpflege'    => nabu_demo_image( 'wiesenpflege.jpg', 'Freiwillige bei der Mahd einer Wiese', 'Wiesenpflege' ),
	'vogelkartierung' => nabu_demo_image( 'vogelkartierung.jpg', 'Person mit Fernglas am Waldrand', 'Vogelkartierung' ),
	'insekten'        => nabu_demo_image( 'insekten.jpg', 'Blühstreifen mit Wildblumen', 'Blühstreifen' ),
);
$missing = array_keys( array_filter( $images, static fn( $id ) => ! $id ) );
if ( $missing ) {
	WP_CLI::warning( 'Not in the media library: ' . implode( ', ', $missing ) );
}

$sample        = nabu_sample_data();
$sample_images = array();
$hero_image    = 0;
$teasers       = array();
$taken         = array();

if ( $sample ) {
	foreach ( (array) $sample['images'] as $file => $meta ) {
		$id = nabu_demo_upload(
			'/scripts/sample-data/images/' . $file,
			'sample:' . $file,
			(string) ( $meta['alt'] ?? '' ),
			(string) ( $meta['credit'] ?? '' ),
			(string) ( $meta['alt'] ?? $file )
		);
		if ( $id ) {
			$sample_images[ $file ] = $id;
		}
	}

	/*
	 * Wide images are hero candidates, the rest become card images. Of the
	 * candidates the most photographic wins: the web builder's banners carry
	 * burned-in lettering, and the hero heading would sit on top of it.
	 */
	$candidates = array();
	foreach ( (array) ( $sample['extra_images'] ?? array() ) as $image ) {
		$id = $sample_images[ $image['file'] ] ?? 0;
		if ( ! $id ) {
			continue;
		}
		if ( $image['height'] && $image['width'] / $image['height'] > 2.5 && $image['width'] >= 900 ) {
			$candidates[ $id ] = (float) ( $image['flat_share'] ?? 0 );
		} else {
			$teasers[] = $id;
		}
	}
	asort( $candidates );
	if ( $candidates ) {
		$hero_image = (int) array_key_first( $candidates );
		$taken[]    = $hero_image;
		$teasers    = array_merge( $teasers, array_slice( array_keys( $candidates ), 1 ) );
	}

	WP_CLI::log( sprintf( 'Sample data from %s: %d images, hero image %s.', $sample['source'], count( $sample_images ), $hero_image ? '#' . $hero_image : 'none' ) );
} else {
	WP_CLI::warning( 'No sample data; run scripts/fetch-sample-data.py for real content. Placeholders stay.' );
}

/* ------------------------------------------------------------------ *
 * Front page: the scaffold built it from the pattern; a sample hero image
 * replaces the placeholder.
 * ------------------------------------------------------------------ */

$front_id = (int) get_option( 'page_on_front' );

if ( $hero_image && $front_id ) {
	$content = (string) get_post_field( 'post_content', $front_id );
	$url     = (string) wp_get_attachment_url( $hero_image );
	$alt     = (string) get_post_meta( $hero_image, '_wp_attachment_image_alt', true );
	$credit  = trim( (string) get_post_meta( $hero_image, '_nabu_image_credit', true ) );

	$content = str_replace(
		'"url":"/wp-content/themes/nabu-unofficial/assets/images/hero.jpg"',
		'"url":"' . esc_url( $url ) . '","id":' . $hero_image,
		$content
	);
	// The alt text stands twice, in the block attributes and on the img; the
	// editor calls the block broken when they differ.
	$content = (string) preg_replace( '#(<!-- wp:cover \{[^}]*?)"alt":"[^"]*"#', '$1"alt":"' . esc_attr( $alt ) . '"', $content, 1 );
	$content = (string) preg_replace(
		'#<img class="wp-block-cover__image-background" alt="[^"]*" src="[^"]*" data-object-fit="cover"/>#',
		sprintf( '<img class="wp-block-cover__image-background wp-image-%d" alt="%s" src="%s" data-object-fit="cover"/>', $hero_image, esc_attr( $alt ), esc_url( $url ) ),
		$content,
		1
	);
	$content = (string) preg_replace(
		'#<!-- wp:paragraph \{"className":"nabu-credit"\} -->\s*<p class="nabu-credit">.*?</p>\s*<!-- /wp:paragraph -->#s',
		'' !== $credit ? nabu_scaffold_p( esc_html( $credit ), array( 'className' => 'nabu-credit' ) ) : '',
		$content,
		1
	);

	wp_update_post(
		array(
			'ID'           => $front_id,
			'post_content' => $content,
		)
	);
	WP_CLI::log( 'Front page hero replaced.' );
}

/* ------------------------------------------------------------------ *
 * Pages: a fuller "Über uns" with a few levels of child pages, to show what
 * the sidebar navigation does with a hierarchy, and "Schutzgebiete" as a
 * second section of child pages.
 * ------------------------------------------------------------------ */

$about = <<<'HTML'
<!-- wp:paragraph {"fontSize":"lead"} -->
<p class="has-lead-font-size">Wir sind rund vierzig Aktive zwischen 12 und 81 Jahren. Was uns verbindet, ist eine Handvoll Flächen, die ohne uns niemand pflegen würde.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Wie wir arbeiten</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Die Gruppe trifft sich einmal im Monat, ansonsten organisiert sich die Arbeit über die einzelnen Aktivitäten. Wer mitmachen will, sucht sich das Thema aus, das ihn interessiert – niemand muss überall dabei sein. Die meisten Einsätze dauern einen Vormittag.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Formal sind wir eine Ortsgruppe im NABU. Das heißt: eigene Kasse, eigene Entscheidungen, aber Rückhalt durch den Landesverband bei Fachfragen, Versicherung und Rechtsberatung.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Vorstand</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-nabu-subtitle"} -->
<p class="is-style-nabu-subtitle">Gewählt auf der Jahreshauptversammlung, Amtszeit zwei Jahre.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<!-- wp:list-item --><li><strong>Vorsitz</strong> — Name folgt</li><!-- /wp:list-item -->
<!-- wp:list-item --><li><strong>Stellvertretung</strong> — Name folgt</li><!-- /wp:list-item -->
<!-- wp:list-item --><li><strong>Kasse</strong> — Name folgt</li><!-- /wp:list-item -->
<!-- wp:list-item --><li><strong>Schriftführung</strong> — Name folgt</li><!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Geschichte</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Gegründet 1985, anfangs vor allem mit Nistkästen beschäftigt. Die Streuobstwiese kam dazu, als die Gemeinde eine Fläche abgeben wollte; der Krötenzaun folgte, nachdem an der Kreisstraße drei Jahre in Folge Hunderte Tiere überfahren wurden.</p>
<!-- /wp:paragraph -->
HTML;

$board = <<<'HTML'
<!-- wp:paragraph -->
<p>Der Vorstand wird alle zwei Jahre von der Mitgliederversammlung gewählt. Er ist ehrenamtlich tätig und in allen inhaltlichen Fragen ansprechbar.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Zusammensetzung</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Erste*r Vorsitzende*r, Stellvertretung, Kassenführung, Schriftführung sowie zwei Beisitzende. Die Namen tragen wir vor dem Livegang ein.</p>
<!-- /wp:paragraph -->
HTML;

$history = <<<'HTML'
<!-- wp:paragraph -->
<p>Die Gruppe wurde 1985 gegründet, zunächst als Ortsgruppe des Deutschen Bundes für Vogelschutz. Aus der Nistkastenbetreuung im Gemeindewald ist über die Jahre die Pflege mehrerer Flächen geworden.</p>
<!-- /wp:paragraph -->
HTML;

$chronicle = <<<'HTML'
<!-- wp:paragraph -->
<p>1985 Gründung. 1991 Übernahme der ersten Streuobstwiese am Sonnenhang. 1996 erster Krötenzaun an der Kreisstraße. 2000 Pflegevereinbarung für die Mühlbachaue.</p>
<!-- /wp:paragraph -->
HTML;

$statutes = <<<'HTML'
<!-- wp:paragraph -->
<p>Es gilt die Mustersatzung des NABU für Orts- und Kreisgruppen in der Fassung des Landesverbands. Die geltende Fassung liegt beim Vorstand aus.</p>
<!-- /wp:paragraph -->
HTML;

// The cards list the page's own children. Once the page exists its ID goes
// straight into the content; on the first run it follows the insert.
$areas_page  = nabu_scaffold_find( 'page', 'schutzgebiete' );
$areas_intro = nabu_scaffold_content(
	nabu_scaffold_p( 'Flächen, für die wir eine Pflegevereinbarung haben – und die man besuchen kann. Jedes Gebiet ist eine Unterseite dieser Seite.', array( 'fontSize' => 'lead' ) ),
	nabu_scaffold_child_grid( $areas_page ? (int) $areas_page->ID : 0 )
);

// Parents before their children: slug, title, content, parent slug, order.
$pages = array(
	array( 'ueber-uns', 'Über uns', $about, '', 1 ),
	array( 'vorstand', 'Der Vorstand', $board, 'ueber-uns', 1 ),
	array( 'geschichte', 'Unsere Geschichte', $history, 'ueber-uns', 2 ),
	array( 'chronik', 'Chronik 1985–2000', $chronicle, 'geschichte', 1 ),
	array( 'satzung', 'Satzung', $statutes, 'ueber-uns', 3 ),
	array( 'schutzgebiete', 'Schutzgebiete', $areas_intro, '', 4 ),
);

$page_ids = array();
foreach ( array( 'aktivitaeten', 'mitmachen', 'kontakt' ) as $slug ) {
	$page = nabu_scaffold_find( 'page', $slug );
	if ( $page ) {
		$page_ids[ $slug ] = (int) $page->ID;
	}
}

foreach ( $pages as list( $slug, $title, $content, $parent, $order ) ) {
	$id = nabu_demo_post(
		array(
			'post_type'    => 'page',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content,
			'post_parent'  => $page_ids[ $parent ] ?? 0,
			'menu_order'   => $order,
		)
	);
	if ( $id ) {
		$page_ids[ $slug ] = $id;
	}
}

if ( ! $areas_page && ! empty( $page_ids['schutzgebiete'] ) ) {
	nabu_scaffold_grid_parent( $page_ids['schutzgebiete'], $page_ids['schutzgebiete'] );
	nabu_demo_record_content( $page_ids['schutzgebiete'] );
}

/* ------------------------------------------------------------------ *
 * Activities
 * ------------------------------------------------------------------ */

$activities = array(
	array(
		'slug'    => 'streuobstwiese',
		'title'   => 'Streuobstwiesen',
		'image'   => 'streuobstwiese',
		'excerpt' => 'Rund vierzig Hochstämme alter Sorten, jedes Jahr im Winter geschnitten.',
		'content' => '<!-- wp:paragraph {"fontSize":"lead"} -->
<p class="has-lead-font-size">Streuobstwiesen sind der artenreichste Lebensraum, den Mitteleuropa zu bieten hat – und einer der am schnellsten verschwindenden.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Unsere Fläche am Sonnenhang umfasst knapp zwei Hektar mit etwa vierzig Bäumen, die meisten davon über fünfzig Jahre alt. Darunter sind Sorten, die im Handel längst nicht mehr vorkommen: Brettacher, Bohnapfel, eine Zwetschgenreihe unbekannter Herkunft.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Was dort lebt</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Steinkauz und Wendehals brüten in den Höhlen der alten Stämme, beide auf der Roten Liste. Dazu kommen Gartenrotschwanz, Grünspecht und im Sommer ein Bestand an Wildbienen, den wir seit drei Jahren zählen lassen.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Was wir tun</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list">
<!-- wp:list-item --><li>Winterschnitt zwischen Januar und März, in zwei bis drei Einsätzen</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Mahd zweimal im Jahr, Schnittgut wird abgeräumt</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Nachpflanzung von zwei bis drei Hochstämmen pro Jahr</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Apfelernte im Oktober — der Saft geht an die Mitglieder</li><!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:paragraph {"className":"nabu-arrow-link"} -->
<p class="nabu-arrow-link"><a href="/termine/">Nächster Schnitttermin</a></p>
<!-- /wp:paragraph -->',
	),
	array(
		'slug'    => 'amphibienschutz',
		'title'   => 'Amphibienschutz',
		'image'   => 'amphibien',
		'excerpt' => 'Jedes Frühjahr Krötenzaun an der Kreisstraße – und Zählung dessen, was durchkommt.',
		'content' => '<!-- wp:paragraph {"fontSize":"lead"} -->
<p class="has-lead-font-size">Zwischen Ende Februar und Anfang April wandern Erdkröten, Grasfrösche und Bergmolche zu ihren Laichgewässern. Ihr Weg führt über die Kreisstraße.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Wir stellen auf 400 Metern einen Fangzaun auf und leeren zweimal täglich die eingegrabenen Eimer. Die Tiere werden gezählt, nach Art bestimmt und über die Straße getragen. In der Saison 2025 waren es 1.240 Tiere, davon knapp 900 Erdkröten.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Helfen Sie mit</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Der Zaun braucht jeden Morgen und jeden Abend eine Kontrolle, sechs bis acht Wochen lang. Das schafft niemand allein. Eine Schicht dauert etwa 45 Minuten; wer zweimal die Woche kann, hilft schon sehr.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Eingelernt wird vor Ort – Sie müssen keine Art bestimmen können, das zeigt sich nach zwei Abenden von selbst.</p>
<!-- /wp:paragraph -->',
	),
	array(
		'slug'    => 'nistkaesten',
		'title'   => 'Nistkästen',
		'image'   => 'nistkasten',
		'excerpt' => 'Über zweihundert Kästen im Gemeindegebiet, im Herbst gereinigt und ausgewertet.',
		'content' => '<!-- wp:paragraph {"fontSize":"lead"} -->
<p class="has-lead-font-size">Alte Bäume mit Höhlen sind selten geworden. Nistkästen ersetzen sie nicht, aber sie überbrücken.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Im Gemeindegebiet hängen gut zweihundert Kästen: Meisenkästen im Wald und in den Obstwiesen, Halbhöhlen an Gebäuden, dazu ein gutes Dutzend Fledermauskästen und zwei Schleiereulenkästen in Scheunen.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Reinigung im Herbst</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ab Oktober werden alle Kästen geöffnet, das alte Nistmaterial entfernt und der Belegungserfolg notiert. Aus diesen Zahlen lässt sich über die Jahre ablesen, wo der Bestand zurückgeht – 2024 etwa in den Kästen entlang der neu ausgebauten Straße.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Der Bau neuer Kästen findet im Winter statt, im Werkraum der Schule, meist gemeinsam mit einer Klasse.</p>
<!-- /wp:paragraph -->',
	),
);

$order = 1;

if ( $sample && ! empty( $sample['projects'] ) ) {
	foreach ( $sample['projects'] as $project ) {
		$content = nabu_resolve_image_blocks( $project['blocks'], $sample_images );

		if ( ! empty( $project['subtitle'] ) ) {
			$content = nabu_scaffold_p(
				esc_html( $project['subtitle'] ),
				array(
					'className' => 'is-style-nabu-subtitle',
					'fontSize'  => 'lead',
				)
			) . "\n\n" . $content;
		}

		$id = nabu_demo_post(
			array(
				'post_type'    => 'page',
				'post_parent'  => $page_ids['aktivitaeten'] ?? 0,
				'menu_order'   => $order++,
				'post_name'    => $project['slug'],
				'post_title'   => $project['title'],
				'post_excerpt' => nabu_excerpt_from_blocks( $content ),
				'post_content' => $content,
			)
		);
		if ( ! $id ) {
			continue;
		}

		update_post_meta( $id, '_nabu_sample_source', (string) $sample['source'] );

		$image = $sample_images[ $project['featured_image'] ?? '' ] ?? 0;
		if ( ! $image ) {
			$image = nabu_matching_image( $project['title'], (array) ( $sample['extra_images'] ?? array() ), $sample_images, $taken );
		}
		if ( ! $image && $teasers ) {
			$image = array_shift( $teasers );
		}
		if ( $image ) {
			set_post_thumbnail( $id, $image );
			$taken[] = $image;
		}
	}
} else {
	foreach ( $activities as $activity ) {
		$id = nabu_demo_post(
			array(
				'post_type'    => 'page',
				'post_parent'  => $page_ids['aktivitaeten'] ?? 0,
				'menu_order'   => $order++,
				'post_name'    => $activity['slug'],
				'post_title'   => $activity['title'],
				'post_excerpt' => $activity['excerpt'],
				'post_content' => $activity['content'],
			)
		);
		if ( $id && ! empty( $images[ $activity['image'] ] ) ) {
			set_post_thumbnail( $id, $images[ $activity['image'] ] );
		}
	}
}

/* ------------------------------------------------------------------ *
 * Protected areas
 * ------------------------------------------------------------------ */

$areas = array(
	array(
		'slug'    => 'sonnenhang',
		'title'   => 'Streuobsthang am Sonnenhang',
		'image'   => 'hecke',
		'excerpt' => 'Zwei Hektar Südhang mit altem Baumbestand, seit 1998 in unserer Pflege.',
		'content' => '<!-- wp:paragraph -->
<p>Der Hang liegt südexponiert über dem Ort und trocknet im Sommer stark aus – genau das macht ihn wertvoll. Auf den mageren Stellen wachsen Orchideen, die auf gedüngten Wiesen längst verschwunden sind.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Zugang:</strong> Über den Feldweg am Ortsausgang, etwa zehn Minuten zu Fuß. Der Hang ist frei begehbar. Bitte bleiben Sie auf den Wegen und führen Sie Hunde an der Leine – zwischen April und Juli brüten hier Bodenbrüter.</p>
<!-- /wp:paragraph -->',
	),
	array(
		'slug'    => 'muehlbachaue',
		'title'   => 'Mühlbachaue',
		'image'   => 'bachlauf',
		'excerpt' => 'Renaturierter Bachabschnitt mit Feuchtwiese und Laichgewässer.',
		'content' => '<!-- wp:paragraph -->
<p>Der Mühlbach war auf 600 Metern begradigt und verrohrt. Seit der Rückverlegung 2019 mäandriert er wieder, hat eine Kiesbank und zwei flache Stillwasserbereiche, in denen Grasfrösche ablaichen.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Die angrenzende Feuchtwiese wird einmal jährlich ab Mitte Juli gemäht, damit Wiesenknopf und Kuckuckslichtnelke aussamen können.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Zugang:</strong> Ein Beobachtungspunkt liegt am Wanderweg. Die Aue selbst ist nicht begehbar.</p>
<!-- /wp:paragraph -->',
	),
);

$order = 1;

foreach ( $areas as $area ) {
	$id = nabu_demo_post(
		array(
			'post_type'    => 'page',
			'post_parent'  => $page_ids['schutzgebiete'] ?? 0,
			'menu_order'   => $order++,
			'post_name'    => $area['slug'],
			'post_title'   => $area['title'],
			'post_excerpt' => $area['excerpt'],
			'post_content' => $area['content'],
		)
	);
	$image = $id ? nabu_demo_next_image( $teasers, $taken, $images[ $area['image'] ] ?? 0 ) : 0;
	if ( $image ) {
		set_post_thumbnail( $id, $image );
	}
}

WP_CLI::log( 'Activities and protected areas done.' );

/* ------------------------------------------------------------------ *
 * Events, relative to today so the demo never runs out of dates
 * ------------------------------------------------------------------ */

$now = time();

$events = array(
	array( 'days' => 6, 'time' => '09:00', 'slug' => 'wiesenmahd-sonnenhang', 'title' => 'Wiesenmahd am Sonnenhang', 'location' => 'Streuobsthang am Sonnenhang', 'meeting' => 'Parkplatz Feldweg, am Ortsausgang', 'excerpt' => 'Zweiter Schnitt des Jahres. Sensen und Rechen sind da, Verpflegung auch.', 'image' => 'wiesenpflege', 'map' => '51.1657, 10.4515' ),
	array( 'days' => 13, 'time' => '19:30', 'slug' => 'gruppenabend-oktober', 'title' => 'Gruppenabend', 'location' => 'Gasthaus zur Krone, Nebenzimmer', 'meeting' => '', 'excerpt' => 'Offener Abend. Diesmal: Auswertung der Nistkastenkontrolle.', 'image' => '' ),
	array( 'days' => 24, 'time' => '10:00', 'slug' => 'nistkastenkontrolle', 'title' => 'Nistkastenkontrolle im Gemeindewald', 'location' => 'Gemeindewald, Abteilung 4', 'meeting' => 'Waldparkplatz an der Kreisstraße', 'excerpt' => 'Kästen öffnen, reinigen, Belegung notieren. Leitern stellen wir, feste Schuhe bitte mitbringen.', 'image' => 'nistkasten' ),
	array( 'days' => 38, 'time' => '14:00', 'slug' => 'apfelernte', 'title' => 'Apfelernte auf der Streuobstwiese', 'location' => 'Streuobsthang am Sonnenhang', 'meeting' => 'Am oberen Wiesentor', 'excerpt' => 'Pflücken, sammeln, zur Mosterei bringen. Kinder ausdrücklich willkommen.', 'image' => 'streuobstwiese' ),
	array( 'days' => 61, 'time' => '09:30', 'slug' => 'heckenpflege-muehlbach', 'title' => 'Heckenpflege an der Mühlbachaue', 'location' => 'Mühlbachaue', 'meeting' => 'Beobachtungspunkt am Wanderweg', 'excerpt' => 'Auf-den-Stock-setzen der Feldhecke, abschnittsweise über mehrere Jahre.', 'image' => 'hecke' ),
	array( 'days' => 96, 'time' => '09:00', 'slug' => 'obstbaumschnitt-kurs', 'title' => 'Obstbaumschnitt für Anfänger*innen', 'location' => 'Streuobsthang am Sonnenhang', 'meeting' => 'Parkplatz Feldweg', 'excerpt' => 'Halbtägiger Kurs. Anmeldung nötig, die Teilnahme ist kostenlos.', 'image' => 'streuobstwiese' ),
	array( 'days' => -12, 'time' => '19:30', 'slug' => 'jahreshauptversammlung', 'title' => 'Jahreshauptversammlung', 'location' => 'Gasthaus zur Krone', 'meeting' => '', 'excerpt' => 'Bericht des Vorstands, Kassenbericht, Neuwahlen.', 'image' => '' ),
	array( 'days' => -30, 'time' => '08:00', 'slug' => 'vogelstimmenwanderung', 'title' => 'Vogelstimmenwanderung', 'location' => 'Mühlbachaue', 'meeting' => 'Beobachtungspunkt am Wanderweg', 'excerpt' => 'Frühe Runde mit Fernglas. 34 Arten verhört.', 'image' => 'vogelkartierung' ),
	array( 'days' => -54, 'time' => '10:00', 'slug' => 'bluehstreifen-anlegen', 'title' => 'Blühstreifen anlegen', 'location' => 'Acker am Ortsrand', 'meeting' => 'Feldweg hinter dem Sportplatz', 'excerpt' => 'Gemeinsam mit dem Landwirt: 800 Meter mehrjähriger Blühstreifen eingesät.', 'image' => 'insekten' ),
);

$event_ids = array();

foreach ( $events as $event ) {
	$ts    = strtotime( "{$event['days']} days", $now );
	$start = wp_date( 'Y-m-d', $ts ) . ' ' . $event['time'];

	$id = nabu_demo_post(
		array(
			'post_type'    => 'nabu_event',
			'post_name'    => $event['slug'],
			'post_title'   => $event['title'],
			'post_excerpt' => $event['excerpt'],
			'post_content' => nabu_scaffold_content(
				nabu_scaffold_p( esc_html( $event['excerpt'] ) ),
				nabu_scaffold_p( 'Die Teilnahme ist kostenlos und ohne Mitgliedschaft möglich. Bei Dauerregen fällt der Termin aus – wir sagen dann am Vorabend per E-Mail ab.' )
			),
			// The event's date is in the meta; a post date in the future
			// would make it "future" and drop it from every query.
			'post_date'    => wp_date( 'Y-m-d H:i:s', min( $ts, $now ) ),
		)
	);
	if ( ! $id ) {
		continue;
	}

	$event_ids[ $event['slug'] ] = $id;

	$end = ( new DateTimeImmutable( $start, wp_timezone() ) )->modify( '+3 hours' )->format( 'Y-m-d H:i' );
	$meta = array(
		'start'         => $start,
		'end'           => $end,
		'location'      => $event['location'],
		'address'       => trim( nabu_group_get( 'postal_code' ) . ' ' . nabu_group_get( 'city' ) ),
		'meeting_point' => $event['meeting'],
		// Coordinates for a meeting point without an address.
		'map'           => $event['map'] ?? '',
	);
	foreach ( $meta as $key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $id, '_nabu_event_' . $key );
		} else {
			update_post_meta( $id, '_nabu_event_' . $key, $value );
		}
	}

	$image = $event['image'] ? nabu_demo_next_image( $teasers, $taken, $images[ $event['image'] ] ?? 0 ) : 0;
	if ( $image ) {
		set_post_thumbnail( $id, $image );
	}
}

WP_CLI::log( 'Events done (' . count( $events ) . ').' );

/* ------------------------------------------------------------------ *
 * News
 * ------------------------------------------------------------------ */

$categories = array();
foreach ( array( 'Aus der Gruppe', 'Artenschutz', 'Aus den Projekten' ) as $name ) {
	$term = term_exists( $name, 'category' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'category' );
	}
	if ( ! is_wp_error( $term ) ) {
		$categories[ $name ] = (int) $term['term_id'];
	}
}

$news = array(
	array(
		'days'     => -4,
		'slug'     => 'kroetenzaun-bilanz',
		'title'    => '1.240 Amphibien über die Straße getragen',
		'category' => 'Artenschutz',
		'image'    => 'amphibien',
		'excerpt'  => 'Die Bilanz der Wandersaison: mehr Tiere als im Vorjahr, aber weniger Grasfrösche.',
		'content'  => '<!-- wp:paragraph {"fontSize":"lead"} -->
<p class="has-lead-font-size">Sechs Wochen, zwei Kontrollen am Tag, achtzehn Beteiligte: Die Amphibiensaison an der Kreisstraße ist ausgewertet.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>1.240 Tiere haben wir über die Straße getragen, gut hundert mehr als im Vorjahr. Der Zuwachs geht fast vollständig auf Erdkröten zurück; die Zahl der Grasfrösche ist zum dritten Mal in Folge gesunken, diesmal auf 84 Tiere.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Woran das liegt, wissen wir nicht sicher. Der wahrscheinlichste Grund ist das trockene Frühjahr – Grasfrösche laichen früher als Erdkröten und sind auf Wasser in den flachen Senken angewiesen, das dieses Jahr Anfang März schon weg war.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Wie es weitergeht</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Für die kommende Saison prüfen wir mit der Gemeinde, ob sich zwei zusätzliche Kleingewässer anlegen lassen. Der Zaun bleibt, solange keine feste Amphibienleitanlage gebaut wird – die steht seit Jahren im Gespräch.</p>
<!-- /wp:paragraph -->',
	),
	array(
		'days'     => -17,
		'slug'     => 'steinkauz-brut',
		'title'    => 'Steinkauz brütet wieder am Sonnenhang',
		'category' => 'Artenschutz',
		'image'    => 'streuobstwiese',
		'excerpt'  => 'Zum ersten Mal seit 2016 ist wieder eine erfolgreiche Brut belegt.',
		'content'  => '<!-- wp:paragraph {"fontSize":"lead"} -->
<p class="has-lead-font-size">Drei Jungvögel sind ausgeflogen. Der Nachweis gelang bei der Routinekontrolle im Juni.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Der Steinkauz braucht Höhlen in alten Obstbäumen und kurzrasige Flächen zum Jagen – beides gibt es am Sonnenhang wieder, seit die Wiese zweimal jährlich gemäht und das Schnittgut abgefahren wird. Genau darauf zielt die Pflege seit über zwanzig Jahren ab.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Wir bitten darum, den oberen Wiesenbereich zwischen März und Juli nicht zu betreten. Beobachten lässt sich der Kauz gut vom Feldweg aus, in der Dämmerung.</p>
<!-- /wp:paragraph -->',
	),
	array(
		'days'     => -33,
		'slug'     => 'neue-mitglieder',
		'title'    => 'Zwölf neue Aktive in diesem Jahr',
		'category' => 'Aus der Gruppe',
		'image'    => 'wiesenpflege',
		'excerpt'  => 'Die Gruppe wächst – vor allem bei den unter Dreißigjährigen.',
		'content'  => '<!-- wp:paragraph {"fontSize":"lead"} -->
<p class="has-lead-font-size">Beim Sommerfest waren so viele Leute da wie lange nicht. Das hat einen erfreulichen Grund.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Zwölf Menschen sind in diesem Jahr neu dazugekommen, sieben davon unter dreißig. Das verändert die Gruppe: Es gibt jetzt eine feste Truppe für die Kartierungen, und die Öffentlichkeitsarbeit läuft nicht mehr an einer Person hängen.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Wer dazustoßen möchte: Der Gruppenabend ist offen, und bei den Pflegeeinsätzen kann man einfach vorbeikommen.</p>
<!-- /wp:paragraph -->',
	),
	array(
		'days'     => -58,
		'slug'     => 'bluehstreifen-gesaet',
		'title'    => '800 Meter Blühstreifen eingesät',
		'category' => 'Aus den Projekten',
		'image'    => 'insekten',
		'excerpt'  => 'Gemeinsam mit einem Landwirt aus dem Ort – mehrjährig, nicht nur für eine Saison.',
		'content'  => '<!-- wp:paragraph {"fontSize":"lead"} -->
<p class="has-lead-font-size">Einjährige Blühmischungen sehen gut aus und helfen wenig. Dieser Streifen bleibt fünf Jahre stehen.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Der Unterschied ist erheblich: Wildbienen brauchen nicht nur Blüten, sondern auch Nistplätze und Überwinterungsstrukturen. Die entstehen erst, wenn eine Fläche mehrere Jahre in Ruhe gelassen wird.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Verwendet wurde regionales Saatgut aus dem Ursprungsgebiet, kein Baumarktgemisch. Das kostet mehr und ist die Ausgabe wert – die Pflanzen passen zu den hiesigen Insekten und zum Boden.</p>
<!-- /wp:paragraph -->',
	),
);

if ( $sample && ! empty( $sample['posts'] ) ) {
	// The source's own posts replace the invented ones.
	$keywords = array(
		'vogel'      => 'Artenschutz',
		'insekten'   => 'Artenschutz',
		'greifvogel' => 'Artenschutz',
		'mitglied'   => 'Aus der Gruppe',
		'bunte'      => 'Aus den Projekten',
	);

	foreach ( $sample['posts'] as $post ) {
		$content = nabu_resolve_image_blocks( $post['blocks'], $sample_images );
		$ts      = $post['date'] ? strtotime( $post['date'] ) : $now;

		$id = nabu_demo_post(
			array(
				'post_type'    => 'post',
				'post_name'    => $post['slug'],
				'post_title'   => $post['title'],
				'post_excerpt' => nabu_excerpt_from_blocks( $content ),
				'post_content' => $content,
				'post_date'    => wp_date( 'Y-m-d H:i:s', $ts ? min( $ts, $now ) : $now ),
			)
		);
		if ( ! $id ) {
			continue;
		}

		update_post_meta( $id, '_nabu_sample_source', (string) $sample['source'] );

		$category = 'Aus der Gruppe';
		foreach ( $keywords as $keyword => $name ) {
			if ( false !== stripos( $post['title'], $keyword ) ) {
				$category = $name;
				break;
			}
		}
		if ( ! empty( $categories[ $category ] ) ) {
			wp_set_post_categories( $id, array( $categories[ $category ] ) );
		}

		$image = $sample_images[ $post['featured_image'] ?? '' ] ?? 0;
		if ( $image ) {
			set_post_thumbnail( $id, $image );
		}
	}

	WP_CLI::log( 'News from the sample data done (' . count( $sample['posts'] ) . ').' );
	$news = array();
}

foreach ( $news as $item ) {
	$id = nabu_demo_post(
		array(
			'post_type'    => 'post',
			'post_name'    => $item['slug'],
			'post_title'   => $item['title'],
			'post_excerpt' => $item['excerpt'],
			'post_content' => $item['content'],
			'post_date'    => wp_date( 'Y-m-d H:i:s', strtotime( "{$item['days']} days", $now ) ),
		)
	);
	if ( ! $id ) {
		continue;
	}

	if ( ! empty( $categories[ $item['category'] ] ) ) {
		wp_set_post_categories( $id, array( $categories[ $item['category'] ] ) );
	}
	if ( ! empty( $images[ $item['image'] ] ) ) {
		set_post_thumbnail( $id, $images[ $item['image'] ] );
	}
}

if ( $news ) {
	WP_CLI::log( 'News done (' . count( $news ) . ').' );
}

/* ------------------------------------------------------------------ *
 * A report on a past event: an ordinary post that names the event
 * ------------------------------------------------------------------ */

if ( ! empty( $event_ids['vogelstimmenwanderung'] ) ) {
	$event_start = (string) get_post_meta( $event_ids['vogelstimmenwanderung'], '_nabu_event_start', true );

	$id = nabu_demo_post(
		array(
			'post_type'    => 'post',
			'post_name'    => 'rueckblick-vogelstimmenwanderung',
			'post_title'   => '34 Arten vor dem Frühstück',
			'post_excerpt' => 'Bericht von der Vogelstimmenwanderung in der Mühlbachaue.',
			'post_content' => nabu_scaffold_content(
				nabu_scaffold_p( 'Rund zwanzig Frühaufsteher*innen waren bei der Vogelstimmenwanderung durch die Mühlbachaue dabei. Am Ende standen 34 Arten auf der Liste.' ),
				nabu_scaffold_p( 'Danke an alle, die mitgelaufen sind, und an alle, die ihre Beobachtungen hinterher noch gemeldet haben.' )
			),
			'post_date'    => wp_date( 'Y-m-d H:i:s', min( nabu_event_timestamp( $event_start ) + 2 * DAY_IN_SECONDS, $now ) ),
		)
	);

	if ( $id ) {
		update_post_meta( $id, '_nabu_report_event', $event_ids['vogelstimmenwanderung'] );
		if ( ! empty( $categories['Aus der Gruppe'] ) ) {
			wp_set_post_categories( $id, array( $categories['Aus der Gruppe'] ) );
		}
		WP_CLI::log( 'Report on the bird walk done.' );
	}
}

/* ------------------------------------------------------------------ *
 * Demo entries this run no longer produces, e.g. the invented news once
 * sample data is there. Only posts marked as demo; nothing the scaffold
 * owns.
 * ------------------------------------------------------------------ */

$keep = array_merge(
	nabu_demo_created(),
	array_map( 'intval', array( get_option( 'page_on_front' ), get_option( 'page_for_posts' ), get_option( 'wp_page_for_privacy_policy' ), get_option( 'nabu_page_for_events' ) ) ),
	get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_nabu_scaffold',
		)
	)
);

$stale = get_posts(
	array(
		'post_type'      => array( 'post', 'page', 'nabu_event' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_nabu_demo',
		'meta_value'     => '1',
		'post__not_in'   => array_filter( array_unique( $keep ) ),
	)
);
foreach ( $stale as $id ) {
	wp_delete_post( (int) $id, true );
}
if ( $stale ) {
	WP_CLI::log( 'Orphaned demo entries removed (' . count( $stale ) . ').' );
}

flush_rewrite_rules();

WP_CLI::success( 'Demo content done.' );
