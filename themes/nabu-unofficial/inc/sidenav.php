<?php
/**
 * Sidebar navigation (nabu/sidenav).
 *
 * One box, built the same everywhere: the top level of the site — the main
 * pages, and sections such as Termine — with the entry you are in unfolded
 * along the path to the current page, as deep as the structure goes. What
 * the indent can no longer show, the breadcrumbs above do.
 *
 * Structure and order are WordPress' own `post_parent` and `menu_order`,
 * maintained in the Nested Pages tree, which also has the "Hide in Nav Menu"
 * switch and the "Nav Title" field this box respects.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a page is hidden from the navigation ("Hide in Nav Menu").
 *
 * @param int|WP_Post $page Page.
 * @return bool
 */
function nabu_sidenav_is_hidden( $page ) {
	$id = $page instanceof WP_Post ? (int) $page->ID : (int) $page;

	return $id && 'hide' === get_post_meta( $id, '_np_nav_status', true );
}

/**
 * Label of a page: its "Nav Title" if set — for titles too long for a narrow
 * column — else its title.
 *
 * @param WP_Post $page Page.
 * @return string
 */
function nabu_sidenav_label( $page ) {
	$nav_title = trim( (string) get_post_meta( $page->ID, '_np_nav_title', true ) );

	return '' !== $nav_title ? $nav_title : get_the_title( $page );
}

/**
 * Whether a page is the one on screen. A page that stands for a section is
 * current on the section's archive.
 *
 * @param int $page_id Page ID.
 * @return bool
 */
function nabu_sidenav_is_current( $page_id ) {
	$type = nabu_section_for_page( $page_id );

	if ( '' !== $type ) {
		return is_post_type_archive( $type );
	}

	return is_singular() && get_queried_object_id() === (int) $page_id;
}

/**
 * All published pages, grouped by parent and sorted like the page tree.
 *
 * @return array<int, WP_Post[]>
 */
function nabu_sidenav_nodes() {
	static $by_parent = null;

	if ( null !== $by_parent ) {
		return $by_parent;
	}

	$pages = get_posts(
		array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	$by_parent = array();
	foreach ( $pages as $page ) {
		$by_parent[ (int) $page->post_parent ][] = $page;
	}

	return $by_parent;
}

/**
 * The top level of the site: the pages in their order, then the sections
 * that no page stands for, alphabetically.
 *
 * @return array<int, array<string, mixed>> Entries with kind, id, post type, label and URL.
 */
function nabu_sidenav_top_level() {
	$front_page = (int) get_option( 'page_on_front' );
	$entries    = array();

	foreach ( nabu_sidenav_nodes()[0] ?? array() as $page ) {
		if ( (int) $page->ID === $front_page || nabu_sidenav_is_hidden( $page ) ) {
			continue;
		}

		$entries[] = array(
			'kind'      => 'page',
			'id'        => (int) $page->ID,
			'post_type' => '',
			'label'     => nabu_sidenav_label( $page ),
			'url'       => (string) get_permalink( $page ),
		);
	}

	$sections = array();

	foreach ( get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' ) as $type ) {
		$archive = $type->has_archive ? get_post_type_archive_link( $type->name ) : '';

		if ( ! $archive || nabu_section_anchor_page( $type->name ) ) {
			continue;
		}

		$sections[] = array(
			'kind'      => 'section',
			'id'        => 0,
			'post_type' => $type->name,
			'label'     => $type->labels->name,
			'url'       => (string) $archive,
		);
	}

	usort(
		$sections,
		static function ( $a, $b ) {
			return strnatcasecmp( $a['label'], $b['label'] );
		}
	);

	return array_merge( $entries, $sections );
}

/**
 * Which top-level entry is open.
 *
 * @param array<int, array<string, mixed>> $level Entries from nabu_sidenav_top_level().
 * @return int Position in the list, -1 for none (front page, search, 404).
 */
function nabu_sidenav_active_index( $level ) {
	if ( is_front_page() || is_search() || is_404() ) {
		return -1;
	}

	$page_id = 0;
	$type    = '';

	if ( is_singular( 'page' ) ) {
		$page_id = get_queried_object_id();
	} elseif ( is_singular() ) {
		$type = (string) get_post_type();
	} elseif ( is_post_type_archive() ) {
		$type = (string) get_query_var( 'post_type' );
	}

	// Posts belong to the posts page, a section to the page standing for it.
	if ( 'post' === $type || is_home() || is_category() || is_tag() || is_date() ) {
		$type    = '';
		$page_id = (int) get_option( 'page_for_posts' );
	} elseif ( $type && nabu_section_anchor_page( $type ) ) {
		$page_id = nabu_section_anchor_page( $type );
		$type    = '';
	}

	if ( $page_id ) {
		$ancestors = get_post_ancestors( $page_id );
		$page_id   = $ancestors ? (int) end( $ancestors ) : $page_id;
	}

	foreach ( $level as $i => $entry ) {
		if ( $page_id && 'page' === $entry['kind'] && $entry['id'] === $page_id ) {
			return $i;
		}
		if ( $type && 'section' === $entry['kind'] && $entry['post_type'] === $type ) {
			return $i;
		}
	}

	return -1;
}

/**
 * The child pages below a parent, unfolded along the path to the current
 * page; everything else stays folded, or the whole site would be in the box.
 *
 * @param int             $parent  Parent ID.
 * @param array<int, int> $path    Current page and its ancestors.
 * @param int             $current ID of the current page.
 * @return array<int, array<string, mixed>>
 */
function nabu_sidenav_branch( $parent, $path, $current ) {
	$nodes = array();

	foreach ( nabu_sidenav_nodes()[ $parent ] ?? array() as $page ) {
		if ( nabu_sidenav_is_hidden( $page ) ) {
			continue;
		}

		$id = (int) $page->ID;

		$nodes[] = array(
			'label'    => nabu_sidenav_label( $page ),
			'url'      => (string) get_permalink( $page ),
			'current'  => $id === $current && nabu_sidenav_is_current( $id ),
			'children' => in_array( $id, $path, true ) ? nabu_sidenav_branch( $id, $path, $current ) : array(),
		);
	}

	return $nodes;
}

/**
 * Render a list of entries with their unfolded branches.
 *
 * The depth goes onto the list as --nabu-level; the stylesheet derives the
 * indent from it with one rule for every depth.
 *
 * @param array<int, array<string, mixed>> $nodes Entries.
 * @param int                              $level Depth, 1-based.
 * @return string
 */
function nabu_sidenav_list( $nodes, $level = 1 ) {
	if ( ! $nodes ) {
		return '';
	}

	$rows = array();

	foreach ( $nodes as $entry ) {
		// Only the open main entry gets the blue surface; below it the
		// unfolded branch shows the path, and bold (aria-current) the page.
		$open = 1 === $level && ! empty( $entry['open'] );

		$rows[] = sprintf(
			'<li><a href="%1$s"%2$s%3$s>%4$s</a>%5$s</li>',
			esc_url( $entry['url'] ),
			$open ? ' class="nabu-navbox__open"' : '',
			empty( $entry['current'] ) ? '' : ' aria-current="page"',
			esc_html( $entry['label'] ),
			empty( $entry['children'] ) ? '' : nabu_sidenav_list( $entry['children'], $level + 1 )
		);
	}

	return sprintf(
		'<ul%1$s>%2$s</ul>',
		$level > 1 ? ' style="--nabu-level:' . (int) $level . '"' : '',
		implode( '', $rows )
	);
}

/**
 * Render the block.
 *
 * @return string
 */
function nabu_render_sidenav() {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return nabu_sidenav_editor_preview();
	}

	$level = nabu_sidenav_top_level();

	if ( ! $level ) {
		return '';
	}

	$open = nabu_sidenav_active_index( $level );

	if ( $open >= 0 ) {
		$entry = &$level[ $open ];

		$entry['open'] = true;

		// Posts and events are a stream, not structure: nothing unfolds under
		// the posts page or a section. The breadcrumbs say where you are.
		if ( 'section' === $entry['kind'] ) {
			$entry['current'] = is_post_type_archive( $entry['post_type'] );
		} elseif ( (int) get_option( 'page_for_posts' ) === $entry['id'] ) {
			$entry['current'] = is_home();
		} else {
			// The path leads to the page on screen, or to the page standing
			// for the section on screen; only the former is current.
			$type    = is_singular( 'page' ) ? '' : (string) ( is_singular() ? get_post_type() : get_query_var( 'post_type' ) );
			$anchor  = $type ? nabu_section_anchor_page( $type ) : 0;
			$current = is_singular( 'page' ) ? get_queried_object_id() : ( is_post_type_archive() ? $anchor : 0 );
			$target  = $anchor ? $anchor : $current;
			$path    = $target ? array_merge( array( $target ), array_map( 'intval', get_post_ancestors( $target ) ) ) : array();

			$entry['children'] = nabu_sidenav_branch( $entry['id'], $path, $current );
			$entry['current']  = $current && $entry['id'] === $current;
		}

		unset( $entry );
	}

	return sprintf(
		'<nav %1$s aria-label="%2$s">%3$s</nav>',
		get_block_wrapper_attributes( array( 'class' => 'nabu-navbox' ) ),
		esc_attr__( 'Seitennavigation', 'nabu-unofficial' ),
		nabu_sidenav_list( $level )
	);
}

/**
 * A sample box for the editor, which has no page on screen to build from.
 *
 * @return string
 */
function nabu_sidenav_editor_preview() {
	$sample = array(
		array(
			'label' => __( 'Erste Hauptseite', 'nabu-unofficial' ),
			'url'   => '#',
		),
		array(
			'label'    => __( 'Aufgerufener Bereich', 'nabu-unofficial' ),
			'url'      => '#',
			'open'     => true,
			'children' => array(
				array(
					'label' => __( 'Unterseite', 'nabu-unofficial' ),
					'url'   => '#',
				),
				array(
					'label'   => __( 'Aufgerufene Unterseite', 'nabu-unofficial' ),
					'url'     => '#',
					'current' => true,
				),
			),
		),
		array(
			'label' => __( 'Weitere Hauptseite', 'nabu-unofficial' ),
			'url'   => '#',
		),
	);

	return sprintf(
		'<nav %1$s aria-label="%2$s">%3$s<p class="nabu-navbox__note">%4$s</p></nav>',
		get_block_wrapper_attributes( array( 'class' => 'nabu-navbox nabu-navbox--preview' ) ),
		esc_attr__( 'Seitennavigation', 'nabu-unofficial' ),
		nabu_sidenav_list( $sample ),
		esc_html__( 'Beispielansicht – vorn stehen hier die Hauptseiten der Website, der aufgerufene Bereich aufgeklappt.', 'nabu-unofficial' )
	);
}

/**
 * Register the block.
 */
function nabu_register_sidenav_block() {
	register_block_type(
		'nabu/sidenav',
		array(
			'api_version'     => 3,
			'title'           => __( 'NABU Seitennavigation', 'nabu-unofficial' ),
			'category'        => 'theme',
			'icon'            => 'list-view',
			'description'     => __( 'Die Hauptseiten der Website, der aufgerufene Bereich mit seinen Unterseiten aufgeklappt.', 'nabu-unofficial' ),
			'editor_script'   => 'nabu-blocks-editor',
			'supports'        => array(
				'html'   => false,
				'anchor' => true,
			),
			'render_callback' => 'nabu_render_sidenav',
		)
	);
}
add_action( 'init', 'nabu_register_sidenav_block' );
