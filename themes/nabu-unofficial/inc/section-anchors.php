<?php
/**
 * Sections in the page tree.
 *
 * A section is the archive of a post type, such as "Termine". Not being a
 * page, it has no place in the page tree of its own — but a page can stand
 * for it: for events the "Terminseite" under Settings → Reading, which the
 * companion plugin hands in through the filter `nabu_section_pages`. The
 * section then shows up where that page hangs, with its order, label and
 * visibility — in the sidebar navigation and in the breadcrumbs — and the
 * page's text introduces the archive (nabu/section-intro).
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pages that stand for a section.
 *
 * @return array<string, int> Post type => page ID.
 */
function nabu_section_anchors() {
	static $anchors = null;

	if ( null === $anchors ) {
		$anchors = array();

		/**
		 * Pages that stand for a post type archive.
		 *
		 * @param array<string, int> $pages Post type => page ID.
		 */
		foreach ( (array) apply_filters( 'nabu_section_pages', array() ) as $type => $page ) {
			if ( post_type_exists( $type ) && (int) $page ) {
				$anchors[ $type ] = (int) $page;
			}
		}
	}

	return $anchors;
}

/**
 * The section a page stands for.
 *
 * @param int $id Page ID.
 * @return string Post type, empty if none.
 */
function nabu_section_for_page( $id ) {
	$type = array_search( (int) $id, nabu_section_anchors(), true );

	return is_string( $type ) ? $type : '';
}

/**
 * The page that stands for a section.
 *
 * @param string $type Post type.
 * @return int Page ID, 0 if none.
 */
function nabu_section_anchor_page( $type ) {
	return nabu_section_anchors()[ $type ] ?? 0;
}

/**
 * Breadcrumb path to a section: the ancestors of its page, then the section
 * itself, under the page's title and with the archive as target.
 *
 * @param string $type Post type.
 * @return array<int, array{label:string,url:string}> Empty without a page.
 */
function nabu_section_anchor_path( $type ) {
	$page = nabu_section_anchor_page( $type );

	if ( ! $page ) {
		return array();
	}

	$path = array();

	foreach ( array_reverse( (array) get_post_ancestors( $page ) ) as $ancestor ) {
		$path[] = array(
			'label' => get_the_title( $ancestor ),
			'url'   => (string) get_permalink( $ancestor ),
		);
	}

	$path[] = array(
		'label' => get_the_title( $page ),
		'url'   => (string) get_post_type_archive_link( $type ),
	);

	return $path;
}

/**
 * The introduction of a section (nabu/section-intro): the content of the page
 * that stands for it, so the editors write the text above the event list
 * where they write every other text.
 *
 * @return string
 */
function nabu_render_section_intro() {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return sprintf(
			'<div %1$s><p>%2$s</p></div>',
			get_block_wrapper_attributes(),
			esc_html__( 'Hier steht der Text der Seite, die für diese Übersicht steht (Einstellungen → Lesen → Terminseite).', 'nabu-unofficial' )
		);
	}

	if ( ! is_post_type_archive() ) {
		return '';
	}

	$page    = nabu_section_anchor_page( (string) get_query_var( 'post_type' ) );
	$content = $page ? trim( (string) get_post_field( 'post_content', $page ) ) : '';
	if ( '' === $content ) {
		return '';
	}

	// The page must not render itself again from within its own content.
	static $rendering = false;
	if ( $rendering ) {
		return '';
	}
	$rendering = true;
	$html      = apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
	$rendering = false;

	return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes( array( 'class' => 'nabu-section-intro' ) ), $html );
}

/**
 * Register the block.
 */
function nabu_register_section_intro_block() {
	register_block_type(
		'nabu/section-intro',
		array(
			'api_version'     => 3,
			'title'           => __( 'NABU Einleitung der Übersicht', 'nabu-unofficial' ),
			'category'        => 'theme',
			'icon'            => 'text',
			'description'     => __( 'Der Text der Seite, die für eine Übersicht steht – etwa der Terminseite über der Terminliste.', 'nabu-unofficial' ),
			'editor_script'   => 'nabu-blocks-editor',
			'supports'        => array(
				'html'   => false,
				'anchor' => true,
			),
			'render_callback' => 'nabu_render_section_intro',
		)
	);
}
add_action( 'init', 'nabu_register_section_intro_block' );
