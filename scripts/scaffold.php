<?php
/**
 * Site scaffold for every group, test instance and production alike.
 *
 *   wp eval-file scripts/scaffold.php [path/to/group.json]
 *
 * Creates what every group site needs and nothing a group would delete: the
 * pages of the main navigation, the front page from its pattern, the posts
 * page and the events page, the legal pages as skeletons that read the
 * group settings, permalinks and a few basic options. No photos and no
 * sample text; that is scripts/demo-content.php.
 *
 * Idempotent and conservative: it creates what is missing and leaves what
 * exists alone — pages (by slug) with their content, reading settings that
 * are already set, WordPress' sample content once edited. The one exception
 * is the group data: the first argument, else group.json next to this
 * script, else — on a site without group settings — group.example.json is
 * written over Settings → NABU Gruppe, and site_title and site_tagline over
 * the site's title and tagline.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

/* ------------------------------------------------------------------ *
 * Helpers
 * ------------------------------------------------------------------ */

/**
 * Find a post by slug and post type, whatever its status.
 *
 * Not get_page_by_path(): it searches attachments too, and an image
 * "streuobstwiese.jpg" has the slug of the page "streuobstwiese". The
 * statuses are named one by one, because a query by name with 'any' drops
 * drafts when nobody is logged in, as under WP-CLI.
 *
 * @param string $post_type Post type.
 * @param string $slug      Slug.
 * @return WP_Post|null
 */
function nabu_scaffold_find( $post_type, $slug ) {
	$found = get_posts(
		array(
			'post_type'        => $post_type,
			'name'             => $slug,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'   => 1,
			'suppress_filters' => false,
		)
	);

	return $found ? $found[0] : null;
}

/**
 * Record a post this run created, or read the list.
 *
 * @param int|null $id Post ID to record.
 * @return int[]
 */
function nabu_scaffold_created( $id = null ) {
	static $ids = array();

	if ( null !== $id ) {
		$ids[] = (int) $id;
	}

	return $ids;
}

/**
 * Create a post unless one with this slug exists. Returns the ID either way.
 *
 * @param array $args wp_insert_post() arguments; post_type and post_name required.
 * @return int 0 on failure.
 */
function nabu_scaffold_post( $args ) {
	$existing = nabu_scaffold_find( $args['post_type'], $args['post_name'] );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	$id = wp_insert_post(
		wp_parse_args(
			$args,
			array(
				'post_status' => 'publish',
				'post_author' => 1,
			)
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( 'Could not create ' . $args['post_name'] . ': ' . $id->get_error_message() );
		return 0;
	}

	update_post_meta( (int) $id, '_nabu_scaffold', '1' );
	nabu_scaffold_created( (int) $id );
	WP_CLI::log( '  created ' . $args['post_type'] . ' "' . $args['post_name'] . '"' );

	return (int) $id;
}

/**
 * Whether a post was never edited since it was created. The local times,
 * because a draft has no GMT date yet.
 *
 * @param WP_Post $post Post.
 * @return bool
 */
function nabu_scaffold_untouched( $post ) {
	return $post->post_modified === $post->post_date;
}

/**
 * Content of a registered pattern.
 *
 * @param string $slug Pattern slug.
 * @return string
 */
function nabu_scaffold_pattern( $slug ) {
	$registry = WP_Block_Patterns_Registry::get_instance();
	if ( ! $registry->is_registered( $slug ) ) {
		WP_CLI::warning( "Pattern $slug is not registered — is the theme active?" );
		return '';
	}
	return (string) $registry->get_registered( $slug )['content'];
}

/**
 * Serialize a block with inner HTML.
 *
 * @param string $name  Block name without "core/".
 * @param array  $attrs Block attributes.
 * @param string $html  Inner HTML.
 * @return string
 */
function nabu_scaffold_block( $name, $attrs, $html ) {
	$json = $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';

	return "<!-- wp:$name$json -->\n$html\n<!-- /wp:$name -->";
}

/**
 * A paragraph. The text is HTML.
 *
 * @param string $html  Content.
 * @param array  $attrs className and/or fontSize.
 * @return string
 */
function nabu_scaffold_p( $html, $attrs = array() ) {
	$classes = array_filter(
		array(
			$attrs['className'] ?? '',
			isset( $attrs['fontSize'] ) ? 'has-' . $attrs['fontSize'] . '-font-size' : '',
		)
	);

	return nabu_scaffold_block( 'paragraph', $attrs, '<p' . ( $classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '' ) . '>' . $html . '</p>' );
}

/**
 * A heading.
 *
 * @param string $text   Plain text.
 * @param int    $level  Heading level.
 * @param array  $attrs  anchor and/or className.
 * @return string
 */
function nabu_scaffold_h( $text, $level = 2, $attrs = array() ) {
	$attrs = array_filter( array( 'level' => 2 === $level ? null : $level ) + $attrs );

	return nabu_scaffold_block(
		'heading',
		$attrs,
		sprintf(
			'<h%1$d class="%2$s"%3$s>%4$s</h%1$d>',
			$level,
			esc_attr( trim( 'wp-block-heading ' . ( $attrs['className'] ?? '' ) ) ),
			isset( $attrs['anchor'] ) ? ' id="' . esc_attr( $attrs['anchor'] ) . '"' : '',
			esc_html( $text )
		)
	);
}

/**
 * A bulleted list. The items are HTML.
 *
 * @param string[] $items List items.
 * @return string
 */
function nabu_scaffold_list( $items ) {
	$html = '';
	foreach ( $items as $item ) {
		$html .= nabu_scaffold_block( 'list-item', array(), '<li>' . $item . '</li>' ) . "\n";
	}

	return nabu_scaffold_block( 'list', array(), "<ul class=\"wp-block-list\">\n" . $html . '</ul>' );
}

/**
 * A paragraph bound to a group setting.
 *
 * @param string $key Key of the bindings source nabu/group.
 * @return string
 */
function nabu_scaffold_bound( $key ) {
	return nabu_scaffold_block(
		'paragraph',
		array( 'metadata' => array( 'bindings' => array( 'content' => array( 'source' => 'nabu/group', 'args' => array( 'key' => $key ) ) ) ) ),
		'<p></p>'
	);
}

/**
 * A heading and a bound paragraph that disappear together while the setting
 * is empty (nabu_group_hide_empty_blocks()).
 *
 * @param string $heading Heading text.
 * @param string $key     Key of the bindings source nabu/group.
 * @return string
 */
function nabu_scaffold_optional_section( $heading, $key ) {
	return nabu_scaffold_block(
		'group',
		array(
			'className' => 'nabu-optional',
			'layout'    => array( 'type' => 'default' ),
		),
		"<div class=\"wp-block-group nabu-optional\">\n" . nabu_scaffold_h( $heading ) . "\n\n" . nabu_scaffold_bound( $key ) . "\n</div>"
	);
}

/**
 * The child pages of a page as cards. The query takes the parent by ID:
 * given here, or set with nabu_scaffold_grid_parent() once the page exists.
 *
 * @param int $parent_id Page whose children the grid lists; 0 for later.
 * @return string
 */
function nabu_scaffold_child_grid( $parent_id = 0 ) {
	$grid = <<<'HTML'
<!-- wp:query {"queryId":41,"query":{"perPage":24,"pages":1,"offset":0,"postType":"page","parents":[0],"order":"asc","orderBy":"menu_order","inherit":false},"layout":{"type":"default"}} -->
<div class="wp-block-query">
	<!-- wp:post-template {"className":"nabu-grid"} -->
		<!-- wp:group {"className":"nabu-card","layout":{"type":"default"}} -->
		<div class="wp-block-group nabu-card">
			<!-- wp:post-featured-image {"isLink":true} /-->
			<!-- wp:group {"className":"nabu-card__body","layout":{"type":"default"}} -->
			<div class="wp-block-group nabu-card__body">
				<!-- wp:post-title {"isLink":true,"level":2} /-->
				<!-- wp:post-excerpt {"excerptLength":22,"showMoreOnNewLine":false} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph --><p>Die einzelnen Einträge erscheinen hier, sobald sie angelegt sind.</p><!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
HTML;

	return str_replace( '"parents":[0]', '"parents":[' . (int) $parent_id . ']', $grid );
}

/**
 * Point the card grids of a page that still list top-level pages at a parent.
 *
 * @param int $page_id   Page with the grid.
 * @param int $parent_id Page whose children the grid lists.
 */
function nabu_scaffold_grid_parent( $page_id, $parent_id ) {
	$content = (string) get_post_field( 'post_content', $page_id );
	$changed = str_replace( '"parents":[0]', '"parents":[' . (int) $parent_id . ']', $content );

	if ( $page_id && $parent_id && $changed !== $content ) {
		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $changed,
			)
		);
	}
}

/**
 * Join blocks into post content.
 *
 * @param string ...$blocks Serialized blocks.
 * @return string
 */
function nabu_scaffold_content( ...$blocks ) {
	return implode( "\n\n", $blocks );
}

/* ------------------------------------------------------------------ *
 * Group data
 * ------------------------------------------------------------------ */

$nabu_group_file = '';
if ( ! empty( $args[0] ) ) {
	$nabu_group_file = $args[0];
} elseif ( file_exists( __DIR__ . '/group.json' ) ) {
	$nabu_group_file = __DIR__ . '/group.json';
} elseif ( ! get_option( 'nabu_group' ) ) {
	$nabu_group_file = __DIR__ . '/group.example.json';
}

$nabu_group_data = array();
if ( $nabu_group_file && file_exists( $nabu_group_file ) ) {
	$nabu_group_data = json_decode( (string) file_get_contents( $nabu_group_file ), true );
	$nabu_group_data = is_array( $nabu_group_data ) ? $nabu_group_data : array();
	unset( $nabu_group_data['_comment'] );
	WP_CLI::log( 'Group data from ' . basename( $nabu_group_file ) . '.' );
} elseif ( $nabu_group_file ) {
	WP_CLI::warning( "$nabu_group_file not found — the group settings stay as they are." );
}

if ( $nabu_group_data ) {
	if ( ! empty( $nabu_group_data['site_title'] ) ) {
		update_option( 'blogname', (string) $nabu_group_data['site_title'] );
	}
	if ( isset( $nabu_group_data['site_tagline'] ) ) {
		update_option( 'blogdescription', (string) $nabu_group_data['site_tagline'] );
	}
	unset( $nabu_group_data['site_title'], $nabu_group_data['site_tagline'] );

	if ( function_exists( 'nabu_group_sanitize' ) ) {
		$nabu_current = get_option( 'nabu_group', array() );
		update_option( 'nabu_group', nabu_group_sanitize( array_merge( is_array( $nabu_current ) ? $nabu_current : array(), $nabu_group_data ) ) );
		WP_CLI::log( 'Group settings written.' );
	} else {
		WP_CLI::warning( 'Plugin "NABU Unofficial: Inhalte" is not active — group settings not written.' );
	}
}

/* ------------------------------------------------------------------ *
 * Basic options, where they are still WordPress' defaults
 * ------------------------------------------------------------------ */

$nabu_defaults = array(
	'timezone_string'        => array( '', 'Europe/Berlin' ),
	'date_format'            => array( 'F j, Y', 'j. F Y' ),
	'time_format'            => array( 'g:i a', 'H:i' ),
	'start_of_week'          => array( '1', 1 ),
	'default_ping_status'    => array( 'open', 'closed' ),
	'default_comment_status' => array( 'open', 'closed' ),
);
foreach ( $nabu_defaults as $nabu_option => list( $nabu_wp_default, $nabu_value ) ) {
	$nabu_current = get_option( $nabu_option );
	if ( false === $nabu_current || (string) $nabu_current === (string) $nabu_wp_default ) {
		update_option( $nabu_option, $nabu_value );
	}
}

// Named at install time, and a hoster's installer often runs in English.
$nabu_category = get_term( (int) get_option( 'default_category' ), 'category' );
if ( $nabu_category instanceof WP_Term && 'Uncategorized' === $nabu_category->name ) {
	wp_update_term(
		$nabu_category->term_id,
		'category',
		array(
			'name' => 'Allgemein',
			'slug' => 'allgemein',
		)
	);
	WP_CLI::log( '  default category renamed to "Allgemein"' );
}

WP_CLI::log( 'Basic options set.' );

/* ------------------------------------------------------------------ *
 * Permalinks, before any content that holds an address
 * ------------------------------------------------------------------ */

/*
 * Posts live below the news page with their year, /aktuelles/2026/<slug>/,
 * so the Jahreshauptversammlung of every year gets a clean address; topics
 * and keywords in German, /aktuelles/thema/<slug>/. Only set while WordPress
 * is on plain links: changing a structure in use would break every address
 * already out there.
 */
if ( '' === (string) get_option( 'permalink_structure' ) ) {
	$nabu_posts_page = (int) get_option( 'page_for_posts' );
	$nabu_posts_slug = $nabu_posts_page ? (string) get_post_field( 'post_name', $nabu_posts_page ) : '';
	$nabu_posts_slug = $nabu_posts_slug ? $nabu_posts_slug : 'aktuelles';
	$nabu_structure  = '/' . $nabu_posts_slug . '/%year%/%postname%/';

	// A category or tag base of its own drops the structure's /aktuelles/
	// front, so it has to repeat it.
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( $nabu_structure );
	$wp_rewrite->set_category_base( $nabu_posts_slug . '/thema' );
	$wp_rewrite->set_tag_base( $nabu_posts_slug . '/stichwort' );
	WP_CLI::log( '  permalinks: ' . $nabu_structure );
}

/* ------------------------------------------------------------------ *
 * Page contents
 * ------------------------------------------------------------------ */

$nabu_events_url = function_exists( 'nabu_events_url' ) ? nabu_events_url() : '/termine/';

$nabu_kicker = static function ( $text ) {
	return nabu_scaffold_p( esc_html( $text ), array( 'className' => 'nabu-kicker' ) );
};

$nabu_about = nabu_scaffold_content(
	$nabu_kicker( 'Platzhalter – Text der Gruppe einsetzen' ),
	nabu_scaffold_p( 'Wer wir sind, was uns verbindet und wie man uns findet.', array( 'fontSize' => 'lead' ) ),
	nabu_scaffold_h( 'Wo wir sind' ),
	nabu_scaffold_p( 'Für welche Orte die Gruppe zuständig ist und wo sie unterwegs ist.' ),
	nabu_scaffold_h( 'Wie wir arbeiten' ),
	nabu_scaffold_p( 'Die Gruppe trifft sich regelmäßig, ansonsten organisiert sich die Arbeit über die einzelnen Aktivitäten. Wer mitmachen will, sucht sich das Thema aus, das ihn interessiert.' ),
	nabu_scaffold_h( 'Verein und Vorstand' ),
	nabu_scaffold_bound( 'legal_form' ),
	nabu_scaffold_list(
		array(
			'<strong>Vorsitz</strong> — Name folgt',
			'<strong>Stellvertretung</strong> — Name folgt',
			'<strong>Kasse</strong> — Name folgt',
			'<strong>Schriftführung</strong> — Name folgt',
		)
	)
);

$nabu_activities = nabu_scaffold_content(
	nabu_scaffold_p( 'Was wir das Jahr über tun. Jede Aktivität ist eine Unterseite dieser Seite.', array( 'fontSize' => 'lead' ) ),
	nabu_scaffold_child_grid()
);

$nabu_events_intro = nabu_scaffold_p(
	'Exkursionen, Pflegeeinsätze und Treffen der Gruppe. Gäste sind willkommen – eine Anmeldung ist nur dort nötig, wo es dabeisteht.',
	array(
		'className' => 'is-style-nabu-subtitle',
		'fontSize'  => 'lead',
	)
);

$nabu_join = nabu_scaffold_content(
	nabu_scaffold_p( 'Es gibt viele Wege, unsere Arbeit zu unterstützen – jeder hilft.', array( 'fontSize' => 'lead' ) ),
	nabu_scaffold_h( 'Mit anpacken' ),
	nabu_scaffold_p( 'Pflegeeinsätze, Kartierungen, Öffentlichkeitsarbeit: Helfende Hände sind immer willkommen, Vorkenntnisse braucht es keine. Zum Kennenlernen eignen sich unsere Gruppentreffen – ohne Anmeldung.' ),
	nabu_scaffold_p( '<a href="' . esc_url( $nabu_events_url ) . '">Nächste Termine ansehen</a>', array( 'className' => 'nabu-arrow-link' ) ),
	nabu_scaffold_h( 'Mitglied werden' ),
	nabu_scaffold_p( 'Eine Mitgliedschaft im NABU unterstützt automatisch auch unsere Gruppe. Sie können jederzeit eintreten, auch wenn Sie nicht aktiv mitarbeiten möchten.' ),
	nabu_scaffold_p( '<a href="https://www.nabu.de/spenden-und-mitmachen/mitglied-werden/">Jetzt Mitglied werden</a>', array( 'className' => 'nabu-arrow-link' ) ),
	nabu_scaffold_h( 'Spenden', 2, array( 'anchor' => 'spenden' ) ),
	nabu_scaffold_p( 'Spenden an die Gruppe fließen zu hundert Prozent in die Arbeit vor Ort. Auf Wunsch stellen wir eine Zuwendungsbestätigung aus.' ),
	// Without bank details the box disappears (nabu-optional).
	nabu_scaffold_block(
		'group',
		array(
			'className' => 'nabu-widget nabu-optional',
			'layout'    => array( 'type' => 'default' ),
		),
		"<div class=\"wp-block-group nabu-widget nabu-optional\">\n" . nabu_scaffold_h( 'Spendenkonto', 3, array( 'className' => 'is-style-nabu-kicker' ) ) . "\n\n" . nabu_scaffold_bound( 'bank_account' ) . "\n</div>"
	),
	nabu_scaffold_h( 'Weitersagen' ),
	nabu_scaffold_p( 'Kennen Sie Menschen, die sich für die Natur vor Ort interessieren? Teilen Sie den Link zu dieser Seite oder zu einem unserer <a href="' . esc_url( $nabu_events_url ) . '">Termine</a>.' )
);

$nabu_contact = nabu_scaffold_content(
	nabu_scaffold_p( 'Sie haben Fragen zu unseren Aktivitäten, möchten mitmachen oder haben eine Beobachtung, die wir kennen sollten? Schreiben Sie uns.', array( 'fontSize' => 'lead' ) ),
	'<!-- wp:pattern {"slug":"nabu-unofficial/contact"} /-->'
);

$nabu_imprint = nabu_scaffold_content(
	$nabu_kicker( 'Platzhalter – vor dem Livegang juristisch prüfen lassen' ),
	nabu_scaffold_h( 'Angaben gemäß § 5 DDG' ),
	nabu_scaffold_bound( 'legal_address' ),
	nabu_scaffold_bound( 'contact_lines_labelled' ),
	nabu_scaffold_optional_section( 'Vertretungsberechtigter Vorstand', 'representative' ),
	nabu_scaffold_h( 'Rechtsform und Landesverband' ),
	nabu_scaffold_bound( 'legal_form' ),
	nabu_scaffold_h( 'Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV' ),
	nabu_scaffold_p( 'Name, Anschrift wie oben.' ),
	nabu_scaffold_h( 'Bildnachweise' ),
	nabu_scaffold_p( 'Fotos stammen aus der NABU-Medienbibliothek oder von Mitgliedern der Gruppe; der Nachweis steht jeweils an der Bildunterschrift. Sollte ein Bild Rechte Dritter verletzen, bitten wir um eine kurze Nachricht; wir entfernen es dann umgehend.' )
);

// After the NABU model text: the group (I) and the association (II) as
// NABU words them, the website (III) as this theme and its plugins run it.
// Square brackets mark what depends on the hosting.
$nabu_privacy = nabu_scaffold_content(
	$nabu_kicker( 'Platzhalter – vor dem Livegang juristisch prüfen lassen' ),
	nabu_scaffold_p( 'Diese Website kommt ohne Werbung, ohne Besucherstatistik von Dritten und ohne Cookie-Banner aus. Inhalte fremder Anbieter, etwa Karten, laden erst, wenn Sie das ausdrücklich wollen.', array( 'fontSize' => 'lead' ) ),
	nabu_scaffold_p( 'Im Folgenden erfahren Sie, wer für die Verarbeitung Ihrer Daten verantwortlich ist und welche Rechte Sie haben (Teil I), wie wir Daten als Verein verarbeiten, etwa von Mitgliedern und Spender*innen (Teil II), und was beim Besuch dieser Website geschieht (Teil III).' ),
	nabu_scaffold_h( 'I. Allgemeines' ),
	nabu_scaffold_h( '1. Verantwortliche Stelle', 3 ),
	nabu_scaffold_bound( 'legal_address' ),
	nabu_scaffold_bound( 'contact_lines_labelled' ),
	nabu_scaffold_p( 'Unter dieser Anschrift und E-Mail-Adresse erreichen Sie auch unsere Ansprechperson für den Datenschutz. Weitere Angaben zur Gruppe, zum Vorstand und zum Landesverband stehen im <a href="/impressum/">Impressum</a>.' ),
	nabu_scaffold_h( '2. Ihre Rechte', 3 ),
	nabu_scaffold_p( 'Soweit keine vertraglichen oder gesetzlichen Pflichten entgegenstehen, haben Sie das Recht auf' ),
	nabu_scaffold_list(
		array(
			'Auskunft über die zu Ihrer Person gespeicherten Daten (Art. 15 DSGVO, mit den Einschränkungen nach §§ 34, 35 BDSG),',
			'Berichtigung unrichtiger Daten (Art. 16 DSGVO),',
			'Löschung (Art. 17 DSGVO, mit den Einschränkungen nach §§ 34, 35 BDSG),',
			'Einschränkung der Verarbeitung (Art. 18 DSGVO),',
			'Datenübertragbarkeit (Art. 20 DSGVO).',
		)
	),
	nabu_scaffold_p( '<strong>Widerspruch.</strong> Beruht eine Verarbeitung auf Art. 6 Abs. 1 lit. e oder f DSGVO, können Sie ihr aus Gründen, die sich aus Ihrer besonderen Situation ergeben, widersprechen (Art. 21 Abs. 1 DSGVO). Einer Nutzung Ihrer Daten für Werbezwecke können Sie jederzeit ohne Angabe von Gründen widersprechen (Art. 21 Abs. 3 DSGVO). Eine erteilte Einwilligung können Sie jederzeit mit Wirkung für die Zukunft widerrufen.' ),
	nabu_scaffold_p( 'Wenden Sie sich dazu am einfachsten per E-Mail an uns. Außerdem können Sie sich bei einer Datenschutz-Aufsichtsbehörde beschweren (Art. 77 DSGVO). Für uns zuständig ist [Name und Anschrift der Aufsichtsbehörde des Bundeslands].' ),
	nabu_scaffold_h( 'II. Daten von Mitgliedern, Spender*innen und Interessierten' ),
	nabu_scaffold_h( '1. Welche Daten', 3 ),
	nabu_scaffold_p( 'Welche Daten wir verarbeiten, hängt davon ab, in welcher Beziehung Sie zu uns stehen – als Mitglied, Spender*in, Teilnehmer*in einer Veranstaltung oder weil Sie uns eine Anfrage geschickt haben. In der Regel sind das Name, Anschrift und Kontaktdaten (Telefon, E-Mail), bei Mitgliedern auch Geburtsdatum, Mitgliedsnummer und bei Familienmitgliedschaften Angaben zu Familienangehörigen, bei Spenden und Beiträgen die Bankverbindung und die Zahlungen. Haben wir direkt mit Ihnen zu tun, kommen Datum, Anlass und Inhalt des Kontakts hinzu.' ),
	nabu_scaffold_h( '2. Zwecke und Rechtsgrundlagen', 3 ),
	nabu_scaffold_list(
		array(
			'<strong>Mitgliedschaft, Spenden, Anmeldungen</strong> (Art. 6 Abs. 1 lit. b DSGVO): Verwaltung der Mitgliedschaft, Einzug von Beiträgen, Bearbeitung von Spenden, Organisation von Veranstaltungen.',
			'<strong>Gesetzliche Pflichten</strong> (Art. 6 Abs. 1 lit. c DSGVO): etwa Zuwendungsbestätigungen und die steuer- und handelsrechtlichen Aufbewahrungspflichten.',
			'<strong>Berechtigtes Interesse</strong> (Art. 6 Abs. 1 lit. f DSGVO): Information unserer Mitglieder über die Arbeit der Gruppe, Einladungen zu Treffen und Mitgliederversammlungen.',
			'<strong>Einwilligung</strong> (Art. 6 Abs. 1 lit. a DSGVO): etwa für einen E-Mail-Verteiler oder für Fotos, auf denen Sie zu erkennen sind. Die Einwilligung können Sie jederzeit widerrufen.',
		)
	),
	nabu_scaffold_h( '3. Empfänger', 3 ),
	nabu_scaffold_p( 'Innerhalb der Gruppe haben nur die Personen Zugriff, die die Daten für ihre Aufgabe brauchen. Die Mitgliedschaft im NABU ist satzungsgemäß mehrfach gestuft: Mitglieder der Gruppe sind zugleich Mitglieder ihres Landesverbands und des NABU-Bundesverbands. Die Mitgliederverwaltung liegt deshalb auch bei der Bundesgeschäftsstelle und beim Landesverband. Weitere Empfänger sind Banken für den Zahlungsverkehr, Behörden, soweit wir gesetzlich dazu verpflichtet sind, und Dienstleister, die Daten in unserem Auftrag verarbeiten (Art. 28 DSGVO). In Länder außerhalb der EU und des EWR übermitteln wir keine Daten, abgesehen von dem in Teil III beschriebenen Kartendienst, wenn Sie ihn selbst aufrufen.' ),
	nabu_scaffold_h( '4. Speicherdauer', 3 ),
	nabu_scaffold_p( 'Wir speichern personenbezogene Daten, solange wir sie für die genannten Zwecke brauchen, und löschen sie danach. Länger aufbewahrt werden sie nur, wo das Gesetz es verlangt – bei Buchungs- und Spendenbelegen nach Abgabenordnung und Handelsgesetzbuch bis zu zehn Jahre – oder solange sie als Beweismittel innerhalb der gesetzlichen Verjährungsfristen nötig sind.' ),
	nabu_scaffold_h( '5. Pflicht zur Angabe', 3 ),
	nabu_scaffold_p( 'Für eine Mitgliedschaft oder eine Spendenquittung brauchen wir die dafür nötigen Angaben; ohne sie können wir die Mitgliedschaft nicht führen oder die Bestätigung nicht ausstellen. Darüber hinaus sind Sie nicht verpflichtet, uns Daten zu geben.' ),
	nabu_scaffold_h( 'III. Besuch dieser Website' ),
	nabu_scaffold_h( '1. Hosting', 3 ),
	nabu_scaffold_p( 'Die Website liegt bei [Name und Anschrift des Hosting-Anbieters] auf Servern in [Land]. Der Anbieter verarbeitet die Daten in unserem Auftrag (Art. 28 DSGVO). Die Verbindung zur Website ist per TLS verschlüsselt.' ),
	nabu_scaffold_h( '2. Server-Protokolle', 3 ),
	nabu_scaffold_p( 'Bei jedem Aufruf protokolliert der Server automatisch: Ihre IP-Adresse, Datum und Uhrzeit, die aufgerufene Adresse, den Statuscode, die übertragene Datenmenge, die zuvor besuchte Seite (Referrer) sowie Browser und Betriebssystem. Das ist nötig, um die Seiten auszuliefern und Angriffe und Fehler zu erkennen. Rechtsgrundlage ist unser berechtigtes Interesse am sicheren Betrieb (Art. 6 Abs. 1 lit. f DSGVO).' ),
	nabu_scaffold_p( 'Die Protokolle werden nach spätestens [Anzahl] Tagen gelöscht. Wir geben die Daten nicht weiter und führen sie nicht mit anderen Daten zusammen.' ),
	nabu_scaffold_h( '3. Cookies', 3 ),
	nabu_scaffold_p( 'Beim bloßen Besuch setzt diese Website keine Cookies. Nur wenn Sie bei einer Karte oder einem anderen fremden Inhalt das Häkchen „immer anzeigen“ setzen, speichert Ihr Browser diese Entscheidung für ein Jahr im Cookie <code>embed-privacy</code> (siehe Abschnitt 5). Entfernen Sie das Häkchen, wird das Cookie gelöscht. Rechtsgrundlage ist Ihre Einwilligung (§ 25 Abs. 1 TDDDG, Art. 6 Abs. 1 lit. a DSGVO).' ),
	nabu_scaffold_p( 'Mitglieder der Redaktion, die sich zum Bearbeiten anmelden, erhalten die technisch notwendigen Anmelde-Cookies von WordPress (§ 25 Abs. 2 TDDDG).' ),
	nabu_scaffold_h( '4. Alles vom eigenen Server', 3 ),
	nabu_scaffold_p( 'Schrift („Source Sans 3“), Bilder, Skripte und Stile liegen auf unserem Server; beim Seitenaufruf wird keine Verbindung zu Google Fonts, Content-Delivery-Netzen oder Statistikdiensten aufgebaut. Die Meldungen „Neues vom NABU“ holt unser Server selbst von nabu.de ab und speichert die Vorschaubilder bei sich – Ihr Browser spricht dabei nur mit dieser Website. Erst wenn Sie einem Link zu nabu.de folgen, gilt dort die Datenschutzerklärung des NABU.' ),
	nabu_scaffold_h( '5. Karten von Google Maps', 3 ),
	nabu_scaffold_p( 'Bei Terminen zeigen wir den Treffpunkt auf einer Karte von Google Maps, einem Dienst der Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland. Die Karte wird nicht automatisch geladen. An ihrer Stelle steht ein Hinweis; erst wenn Sie darauf klicken, baut Ihr Browser eine Verbindung zu Google auf. Dabei erhält Google unter anderem Ihre IP-Adresse und die Information, welche Seite Sie aufgerufen haben, und kann Cookies setzen. Sind Sie bei Google angemeldet, kann Google den Aufruf Ihrem Konto zuordnen. Eine Übermittlung in die USA ist möglich; die Google LLC ist nach dem EU-US Data Privacy Framework zertifiziert.' ),
	nabu_scaffold_p( 'Rechtsgrundlage ist Ihre Einwilligung durch den Klick (Art. 6 Abs. 1 lit. a DSGVO, § 25 Abs. 1 TDDDG). Wer die Karte nicht laden möchte, findet die Anschrift immer auch als Text und als Link. Mehr dazu in der <a href="https://policies.google.com/privacy?hl=de" rel="noopener">Datenschutzerklärung von Google</a>.' ),
	nabu_scaffold_h( '6. Kontakt per E-Mail', 3 ),
	nabu_scaffold_p( 'Ein Kontaktformular gibt es nicht. Wenn Sie uns eine E-Mail schreiben, verarbeiten wir Ihre Adresse und Ihre Angaben, um Ihr Anliegen zu beantworten (Art. 6 Abs. 1 lit. b oder f DSGVO), und bewahren die Nachricht bis zu zwei Jahre auf, sofern keine längere Aufbewahrungspflicht besteht.' ),
	nabu_scaffold_h( '7. Links auf andere Websites', 3 ),
	nabu_scaffold_p( 'Unsere Seiten verlinken auf andere Angebote, etwa auf nabu.de für die Mitgliedschaft oder auf Google Maps. Für die dort verarbeiteten Daten ist der jeweilige Anbieter verantwortlich; es gilt seine Datenschutzerklärung.' ),
	nabu_scaffold_p( 'Stand: [Monat Jahr]', array( 'fontSize' => 'small' ) )
);

/* ------------------------------------------------------------------ *
 * Pages — in the order of the main navigation
 * ------------------------------------------------------------------ */

$nabu_pages = array(
	'ueber-uns'    => array( 'Über uns', $nabu_about, 1 ),
	'aktuelles'    => array( 'Aktuelles', '', 2 ),
	'aktivitaeten' => array( 'Aktivitäten', $nabu_activities, 4 ),
	'mitmachen'    => array( 'Mitmachen', $nabu_join, 5 ),
	'kontakt'      => array( 'Kontakt', $nabu_contact, 6 ),
	'impressum'    => array( 'Impressum', $nabu_imprint, 10 ),
	'datenschutz'  => array( 'Datenschutzerklärung', $nabu_privacy, 11 ),
);

$nabu_page_ids = array();
foreach ( $nabu_pages as $nabu_slug => list( $nabu_title, $nabu_content, $nabu_order ) ) {
	$nabu_page_ids[ $nabu_slug ] = nabu_scaffold_post(
		array(
			'post_type'    => 'page',
			'post_name'    => $nabu_slug,
			'post_title'   => $nabu_title,
			'post_content' => $nabu_content,
			'menu_order'   => $nabu_order,
		)
	);
}

// The page for events stands for the archive, as "Aktuelles" does for posts.
if ( function_exists( 'nabu_events_page_id' ) && ! nabu_events_page_id() ) {
	$nabu_events_page = nabu_scaffold_post(
		array(
			'post_type'    => 'page',
			'post_name'    => nabu_group_get( 'events_slug' ),
			'post_title'   => 'Termine',
			'post_content' => $nabu_events_intro,
			'menu_order'   => 3,
		)
	);
	if ( $nabu_events_page ) {
		update_option( 'nabu_page_for_events', $nabu_events_page );
	}
}

// Through page-full.html: without it the front page would get title,
// breadcrumbs and sidebar. A front-page.html would take precedence over any
// template the editors assign, so there is none.
$nabu_front_id = nabu_scaffold_post(
	array(
		'post_type'    => 'page',
		'post_name'    => 'startseite',
		'post_title'   => 'Startseite',
		'post_content' => nabu_scaffold_pattern( 'nabu-unofficial/front-page' ),
	)
);
if ( $nabu_front_id && ! get_post_meta( $nabu_front_id, '_wp_page_template', true ) ) {
	update_post_meta( $nabu_front_id, '_wp_page_template', 'page-full' );
}

// Reading settings, where they are unset or point to no published page.
$nabu_published = static function ( $option ) {
	$id = (int) get_option( $option );
	return $id && 'publish' === get_post_status( $id );
};
if ( 'page' !== get_option( 'show_on_front' ) || ! $nabu_published( 'page_on_front' ) ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $nabu_front_id );
}
if ( ! $nabu_published( 'page_for_posts' ) ) {
	update_option( 'page_for_posts', $nabu_page_ids['aktuelles'] );
}
if ( ! $nabu_published( 'wp_page_for_privacy_policy' ) ) {
	update_option( 'wp_page_for_privacy_policy', $nabu_page_ids['datenschutz'] );
}

// New legal pages go into the footer, not into the sidebar navigation.
foreach ( array( 'impressum', 'datenschutz' ) as $nabu_slug ) {
	if ( in_array( $nabu_page_ids[ $nabu_slug ], nabu_scaffold_created(), true ) ) {
		update_post_meta( $nabu_page_ids[ $nabu_slug ], '_np_nav_status', 'hide' );
	}
}

// The card grids of new pages list the child pages of "Aktivitäten".
foreach ( array( $nabu_page_ids['aktivitaeten'], $nabu_front_id ) as $nabu_page_id ) {
	if ( in_array( $nabu_page_id, nabu_scaffold_created(), true ) ) {
		nabu_scaffold_grid_parent( $nabu_page_id, $nabu_page_ids['aktivitaeten'] );
	}
}

/* ------------------------------------------------------------------ *
 * WordPress' own sample content
 * ------------------------------------------------------------------ */

// Removed while untouched, like the installer's English privacy draft.
foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page' ) as $nabu_slug => $nabu_type ) {
	$nabu_sample = nabu_scaffold_find( $nabu_type, $nabu_slug );
	if ( $nabu_sample && nabu_scaffold_untouched( $nabu_sample ) && ! get_post_meta( $nabu_sample->ID, '_nabu_scaffold', true ) ) {
		wp_delete_post( $nabu_sample->ID, true );
	}
}

$nabu_privacy_draft = nabu_scaffold_find( 'page', 'privacy-policy' );
if ( $nabu_privacy_draft
	&& 'draft' === $nabu_privacy_draft->post_status
	&& nabu_scaffold_untouched( $nabu_privacy_draft )
	&& (int) $nabu_privacy_draft->ID !== (int) get_option( 'wp_page_for_privacy_policy' )
	&& ! get_post_meta( $nabu_privacy_draft->ID, '_nabu_scaffold', true )
) {
	wp_delete_post( $nabu_privacy_draft->ID, true );
	WP_CLI::log( '  removed the English privacy draft' );
}

flush_rewrite_rules();

WP_CLI::success( 'Scaffold done.' );
