<?php
/**
 * Breadcrumbs (nabu/breadcrumb) with BreadcrumbList markup, and the heading
 * of the current view (nabu/page-title), which is the trail's last item.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the breadcrumb trail for the current request.
 *
 * @return array<int, array{label:string, url:string}> From the front page to the current page.
 */
function nabu_breadcrumb_trail() {
	$trail = array(
		array(
			'label' => __( 'Startseite', 'nabu-unofficial' ),
			'url'   => home_url( '/' ),
		),
	);

	if ( is_front_page() ) {
		return $trail;
	}

	if ( is_singular() ) {
		$post = get_queried_object();

		if ( ! $post instanceof WP_Post ) {
			return $trail;
		}

		$post_type = get_post_type_object( $post->post_type );

		if ( $post_type && ! empty( $post_type->has_archive ) ) {
			// Through the page that stands for the section, if there is one
			// (e.g. Mitmachen › Termine), else straight to the archive.
			$anchor_path = nabu_section_anchor_path( $post->post_type );
			if ( $anchor_path ) {
				$trail = array_merge( $trail, $anchor_path );
			} else {
				$archive_link = get_post_type_archive_link( $post->post_type );
				if ( $archive_link ) {
					$trail[] = array(
						'label' => $post_type->labels->name,
						'url'   => $archive_link,
					);
				}
			}
		} elseif ( 'post' === $post->post_type ) {
			$blog_page = (int) get_option( 'page_for_posts' );
			if ( $blog_page ) {
				$trail[] = array(
					'label' => get_the_title( $blog_page ),
					'url'   => get_permalink( $blog_page ),
				);
			}
		}

		foreach ( array_reverse( (array) get_post_ancestors( $post ) ) as $ancestor_id ) {
			$trail[] = array(
				'label' => get_the_title( $ancestor_id ),
				'url'   => (string) get_permalink( $ancestor_id ),
			);
		}

		$trail[] = array(
			'label' => get_the_title( $post ),
			'url'   => (string) get_permalink( $post ),
		);

		return $trail;
	}

	if ( is_home() ) {
		$blog_page = (int) get_option( 'page_for_posts' );
		$trail[]   = array(
			'label' => $blog_page ? get_the_title( $blog_page ) : __( 'Aktuelles', 'nabu-unofficial' ),
			'url'   => $blog_page ? (string) get_permalink( $blog_page ) : home_url( '/' ),
		);
		return $trail;
	}

	if ( is_search() ) {
		$trail[] = array(
			/* translators: %s: search term. */
			'label' => sprintf( __( 'Suche nach „%s“', 'nabu-unofficial' ), get_search_query() ),
			'url'   => '',
		);
		return $trail;
	}

	if ( is_404() ) {
		$trail[] = array(
			'label' => __( 'Seite nicht gefunden', 'nabu-unofficial' ),
			'url'   => '',
		);
		return $trail;
	}

	// A section with a page of its own: that page's ancestors first, and its
	// title as the name — it is what the menus call the section.
	$anchor_title = '';
	if ( is_post_type_archive() ) {
		$anchor_path = nabu_section_anchor_path( (string) get_query_var( 'post_type' ) );
		if ( $anchor_path ) {
			$anchor_title = array_pop( $anchor_path )['label'];
			$trail        = array_merge( $trail, $anchor_path );
		}
	}

	// Archives without the "Archiv:" prefix: the name is the menu item.
	if ( '' !== $anchor_title ) {
		$title = wp_strip_all_tags( $anchor_title );
	} else {
		$title = is_post_type_archive()
			? wp_strip_all_tags( post_type_archive_title( '', false ) )
			: wp_strip_all_tags( get_the_archive_title() );
	}
	if ( $title ) {
		$trail[] = array(
			'label' => $title,
			'url'   => '',
		);
	}

	return $trail;
}

/**
 * Render the breadcrumb block.
 *
 * @return string
 */
function nabu_render_breadcrumb() {
	$trail = nabu_breadcrumb_trail();

	if ( count( $trail ) < 2 ) {
		return '';
	}

	$home_icon = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.5 0 8h2.5v6.5h4V10h3v4.5h4V8H16z"/></svg>';

	$items    = array();
	$ld_items = array();
	$last     = count( $trail ) - 1;

	foreach ( $trail as $index => $crumb ) {
		$label = 0 === $index
			? '<span class="nabu-breadcrumb__home">' . $home_icon . '<span class="screen-reader-text">' . esc_html( $crumb['label'] ) . '</span></span>'
			: esc_html( $crumb['label'] );

		if ( $index === $last || empty( $crumb['url'] ) ) {
			$items[] = '<li><span aria-current="page">' . $label . '</span></li>';
		} else {
			$items[] = '<li><a href="' . esc_url( $crumb['url'] ) . '">' . $label . '</a></li>';
		}

		$ld_items[] = array(
			'@type'    => 'ListItem',
			'position' => $index + 1,
			'name'     => $crumb['label'],
			'item'     => $crumb['url'] ? $crumb['url'] : null,
		);
	}

	$ld = wp_json_encode(
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $ld_items,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'nabu-breadcrumb' ) );

	// The JSON-LD sits inside the <nav>: as a sibling it would break the
	// adjacent-sibling rules the spacing below the breadcrumbs relies on.
	return sprintf(
		'<nav %1$s aria-label="%2$s"><ol>%3$s</ol><script type="application/ld+json">%4$s</script></nav>',
		$wrapper,
		esc_attr__( 'Brotkrumen', 'nabu-unofficial' ),
		implode( '', $items ),
		$ld
	);
}

/**
 * Heading of the current view (nabu/page-title).
 *
 * For the posts page and the events archive. core/post-title cannot do it:
 * it reads the global post, which outside the loop is the first post of the
 * list. The last item of the breadcrumb trail is exactly the heading wanted.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function nabu_render_page_title( $attributes ) {
	$level = isset( $attributes['level'] ) ? min( 6, max( 1, (int) $attributes['level'] ) ) : 1;

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		$title = __( 'Titel der Ansicht', 'nabu-unofficial' );
	} else {
		$trail = nabu_breadcrumb_trail();
		$last  = end( $trail );
		$title = $last ? $last['label'] : '';
	}

	if ( '' === $title ) {
		return '';
	}

	return sprintf(
		'<h%1$d %2$s>%3$s</h%1$d>',
		$level,
		get_block_wrapper_attributes( array( 'class' => 'wp-block-heading' ) ),
		esc_html( $title )
	);
}

/**
 * Register the blocks.
 */
function nabu_register_breadcrumb_blocks() {
	register_block_type(
		'nabu/page-title',
		array(
			'api_version'     => 3,
			'title'           => __( 'NABU Seitentitel', 'nabu-unofficial' ),
			'category'        => 'theme',
			'icon'            => 'heading',
			'description'     => __( 'Überschrift der aufgerufenen Ansicht – auf der Beitragsseite deren Name, auf einem Archiv der Archivname.', 'nabu-unofficial' ),
			'editor_script'   => 'nabu-blocks-editor',
			'attributes'      => array(
				'level' => array(
					'type'    => 'number',
					'default' => 1,
				),
			),
			'supports'        => array(
				'html'   => false,
				'anchor' => true,
			),
			'render_callback' => 'nabu_render_page_title',
		)
	);

	register_block_type(
		'nabu/breadcrumb',
		array(
			'api_version'     => 3,
			'title'           => __( 'NABU Brotkrumen', 'nabu-unofficial' ),
			'category'        => 'theme',
			'icon'            => 'menu-alt',
			'description'     => __( 'Pfad von der Startseite zur aktuellen Seite, inklusive BreadcrumbList-Auszeichnung.', 'nabu-unofficial' ),
			'editor_script'   => 'nabu-blocks-editor',
			'supports'        => array(
				'html'   => false,
				'align'  => array( 'wide', 'full' ),
				'anchor' => true,
			),
			'render_callback' => 'nabu_render_breadcrumb',
		)
	);
}
add_action( 'init', 'nabu_register_breadcrumb_blocks' );
