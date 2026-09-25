<?php
/**
 * NABU Unofficial — theme functions.
 *
 * The theme carries the presentation. The event post type, the extra fields
 * and the group settings live in the companion plugin "NABU Unofficial:
 * Inhalte", so they survive a theme switch; without it the theme still
 * renders, with less to show.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version string for a theme file: its modification time, so every deploy
 * that changes it gets past the browsers' cache.
 *
 * @param string $file Path below the theme directory.
 * @return string
 */
function nabu_asset_version( $file ) {
	return (string) filemtime( get_template_directory() . '/' . $file );
}

require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/event-reports.php';
require_once __DIR__ . '/inc/association-news.php';
require_once __DIR__ . '/inc/breadcrumb.php';
require_once __DIR__ . '/inc/third-party-embeds.php';
require_once __DIR__ . '/inc/section-anchors.php';
require_once __DIR__ . '/inc/sidenav.php';
require_once __DIR__ . '/inc/nav-current.php';
require_once __DIR__ . '/inc/image-credit.php';
require_once __DIR__ . '/inc/subtitle.php';
require_once __DIR__ . '/inc/news.php';
require_once __DIR__ . '/inc/group-blocks.php';

/**
 * Theme supports beyond those of every block theme, editor styles, pattern
 * category.
 */
function nabu_setup() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 270,
			'width'       => 320,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// The front-end stylesheet in the editor too, so templates look the same there.
	add_editor_style( 'style.css' );
	add_editor_style( 'assets/css/editor.css' );

	register_block_pattern_category(
		'nabu',
		array( 'label' => __( 'NABU', 'nabu-unofficial' ) )
	);
}
add_action( 'after_setup_theme', 'nabu_setup' );

/**
 * Front-end assets.
 */
function nabu_enqueue_assets() {
	wp_enqueue_style( 'nabu-style', get_template_directory_uri() . '/style.css', array(), nabu_asset_version( 'style.css' ) );

	wp_enqueue_script(
		'nabu-script',
		get_template_directory_uri() . '/assets/js/nabu.js',
		array(),
		nabu_asset_version( 'assets/js/nabu.js' ),
		array( 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'nabu_enqueue_assets' );

/**
 * The editor side of the theme's blocks, shared by all of them.
 */
function nabu_register_block_editor_script() {
	wp_register_script(
		'nabu-blocks-editor',
		get_template_directory_uri() . '/assets/js/blocks.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		nabu_asset_version( 'assets/js/blocks.js' ),
		true
	);
}
add_action( 'init', 'nabu_register_block_editor_script', 5 );

/**
 * The NABU favicon until a site icon is set under Settings → General.
 */
function nabu_favicon() {
	if ( has_site_icon() ) {
		return;
	}

	printf(
		'<link rel="icon" href="%s" sizes="32x32">' . "\n",
		esc_url( get_template_directory_uri() . '/assets/images/favicon.ico' )
	);
}
add_action( 'wp_head', 'nabu_favicon' );
add_action( 'admin_head', 'nabu_favicon' );

/**
 * Block styles. Free colour choice is locked in theme.json; these variants
 * are what editors choose from instead.
 */
function nabu_register_block_styles() {
	$styles = array(
		'core/button'    => array(
			'nabu-cta'        => __( 'NABU Aktion (orange)', 'nabu-unofficial' ),
			'nabu-ghost'      => __( 'NABU Kontur hell (auf Foto/Farbe)', 'nabu-unofficial' ),
			'nabu-ghost-dark' => __( 'NABU Kontur blau (auf Weiß/Sand)', 'nabu-unofficial' ),
		),
		'core/heading'   => array(
			'nabu-kicker'   => __( 'NABU Dachzeile (Versalien)', 'nabu-unofficial' ),
			'nabu-subtitle' => __( 'NABU Untertitel (graugrün)', 'nabu-unofficial' ),
		),
		'core/paragraph' => array(
			'nabu-kicker' => __( 'NABU Dachzeile (Versalien)', 'nabu-unofficial' ),
		),
	);

	foreach ( $styles as $block => $variants ) {
		foreach ( $variants as $name => $label ) {
			register_block_style(
				$block,
				array(
					'name'  => $name,
					'label' => $label,
				)
			);
		}
	}
}
add_action( 'init', 'nabu_register_block_styles' );

/**
 * A placeholder in the logo tab while no logo is set.
 *
 * The logo is fixed by NABU for each group and released by NABU-Publishing;
 * the group uploads it itself, as SVG with the Safe SVG plugin. The theme
 * ships no logo, only a placeholder of the same proportions that makes the
 * gap obvious.
 *
 * @param string $block_content Rendered markup.
 * @param array  $block         Block data.
 * @return string
 */
function nabu_site_logo_fallback( $block_content, $block ) {
	if ( 'core/site-logo' !== ( $block['blockName'] ?? '' ) || has_custom_logo() ) {
		return $block_content;
	}

	return sprintf(
		'<div class="wp-block-site-logo"><a href="%1$s" class="custom-logo-link" rel="home"><img class="custom-logo" src="%2$s" alt="%3$s" width="160" height="151" decoding="async"/></a></div>',
		esc_url( home_url( '/' ) ),
		esc_url( get_template_directory_uri() . '/assets/images/logo-placeholder.png' ),
		esc_attr( get_bloginfo( 'name', 'display' ) )
	);
}
add_filter( 'render_block', 'nabu_site_logo_fallback', 10, 2 );

/**
 * Remind admins that the logo is missing.
 */
function nabu_logo_missing_notice() {
	if ( has_custom_logo() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		wp_kses(
			sprintf(
				/* translators: %s: URL of the customizer logo setting. */
				__( 'Es ist noch kein Logo hinterlegt – die Website zeigt einen Platzhalter. <a href="%s">Das freigegebene Logo der Gruppe hochladen</a> (SVG möglich, solange das Plugin Safe SVG aktiv ist).', 'nabu-unofficial' ),
				esc_url( admin_url( 'customize.php?autofocus[control]=custom_logo' ) )
			),
			array( 'a' => array( 'href' => array() ) )
		)
	);
}
add_action( 'admin_notices', 'nabu_logo_missing_notice' );

/**
 * Give search forms an accessible name.
 *
 * core/search renders `<form role="search">` without one; with a second
 * search on the page (404, mobile menu) the landmarks are indistinguishable.
 * The name is the block's own label.
 *
 * @param string $block_content Rendered markup.
 * @param array  $block         Block data.
 * @return string
 */
function nabu_search_landmark_label( $block_content, $block ) {
	if ( 'core/search' !== ( $block['blockName'] ?? '' ) ) {
		return $block_content;
	}

	$label = trim( (string) ( $block['attrs']['label'] ?? '' ) );
	if ( '' === $label ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( $processor->next_tag( 'form' ) ) {
		$processor->set_attribute( 'aria-label', $label );
		return $processor->get_updated_html();
	}

	return $block_content;
}
add_filter( 'render_block', 'nabu_search_landmark_label', 10, 2 );

/**
 * The hero image: loaded first, at window width, and shown whole.
 *
 * The first cover with the class nabu-hero is the largest contentful paint:
 * eager, fetchpriority=high. It always spans the window, so sizes="100vw" —
 * otherwise WordPress derives sizes from the size picked in the editor and a
 * wide screen gets a stretched 1024px file. wp_filter_content_tags() adds
 * srcset later and keeps a sizes attribute that is already there.
 *
 * For an image from the media library the photo's proportions go into
 * --nabu-hero-ratio: style.css sizes the hero by them, and where the photo is
 * narrower than the window a blurred copy (.nabu-hero__blur, the same file)
 * fills the sides.
 *
 * @param string $block_content Rendered markup.
 * @param array  $block         Block data.
 * @return string
 */
function nabu_hero_image( $block_content, $block ) {
	static $done = false;

	if ( $done || 'core/cover' !== ( $block['blockName'] ?? '' ) || false === strpos( $block_content, 'nabu-hero' ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $processor->next_tag() ) {
		return $block_content;
	}
	$processor->set_bookmark( 'cover' );

	if ( ! $processor->next_tag( 'img' ) ) {
		return $block_content;
	}
	$processor->set_attribute( 'loading', 'eager' );
	$processor->set_attribute( 'fetchpriority', 'high' );
	$processor->remove_attribute( 'decoding' );
	$processor->set_attribute( 'sizes', '100vw' );
	$done = true;

	$id     = (int) ( $block['attrs']['id'] ?? 0 );
	$meta   = $id ? wp_get_attachment_metadata( $id ) : false;
	$width  = (int) ( $meta['width'] ?? 0 );
	$height = (int) ( $meta['height'] ?? 0 );
	$src    = (string) $processor->get_attribute( 'src' );
	if ( ! $width || ! $height || '' === $src ) {
		$processor->release_bookmark( 'cover' );
		return $processor->get_updated_html();
	}

	$processor->seek( 'cover' );
	$processor->add_class( 'nabu-hero--framed' );
	$style = rtrim( (string) $processor->get_attribute( 'style' ), '; ' );
	$processor->set_attribute( 'style', ltrim( $style . ';--nabu-hero-ratio:' . round( $width / $height, 4 ), ';' ) );
	$processor->release_bookmark( 'cover' );

	// The wp-image class earns the copy the same srcset, so the browser picks
	// the same file for both.
	$blur = sprintf(
		'<img class="wp-block-cover__image-background wp-image-%1$d nabu-hero__blur" src="%2$s" alt="" aria-hidden="true" loading="eager" sizes="100vw" />',
		$id,
		esc_url( $src )
	);
	$html = $processor->get_updated_html();
	$at   = strpos( $html, '<img' );

	return false === $at ? $html : substr_replace( $html, $blur, $at, 0 );
}
add_filter( 'render_block', 'nabu_hero_image', 10, 2 );

/**
 * Keep the post on screen out of the query loops below it: "Weitere
 * Meldungen" lists the latest posts, and on a site with few of them that
 * includes the one being read.
 *
 * @param array    $query Query vars.
 * @param WP_Block $block The query block.
 * @return array
 */
function nabu_exclude_current_post( $query, $block ) {
	if ( ! is_singular( 'post' ) ) {
		return $query;
	}

	// Only loops with a query of their own; an inheriting loop is the main query.
	$context = $block->context['query'] ?? array();
	if ( ! empty( $context['inherit'] ) || 'post' !== ( $context['postType'] ?? '' ) ) {
		return $query;
	}

	$query['post__not_in'] = array_merge( (array) ( $query['post__not_in'] ?? array() ), array( get_queried_object_id() ) );

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'nabu_exclude_current_post', 10, 2 );

/**
 * No "Themen: Allgemein" under every post of a group that never uses
 * categories: the terms line disappears when it would only name the default
 * category.
 *
 * @param string $block_content Rendered markup.
 * @param array  $block         Block data.
 * @return string
 */
function nabu_hide_default_category( $block_content, $block ) {
	if ( 'core/post-terms' !== ( $block['blockName'] ?? '' ) || 'category' !== ( $block['attrs']['term'] ?? '' ) ) {
		return $block_content;
	}

	$terms = get_the_terms( get_the_ID(), 'category' );

	if ( is_array( $terms ) && 1 === count( $terms ) && (int) $terms[0]->term_id === (int) get_option( 'default_category' ) ) {
		return '';
	}

	return $block_content;
}
add_filter( 'render_block', 'nabu_hide_default_category', 10, 2 );

/**
 * No author archives.
 *
 * The theme names no author — posts speak for the group. WordPress would
 * still publish …/author/<name>/ for every account and list it in the
 * sitemap, giving whoever maintains the site a public page of their own.
 */
function nabu_no_author_archive() {
	if ( ! is_author() ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
// Before redirect_canonical() (10), which would send ?author=1 on to the
// account's archive address and so name the account.
add_action( 'template_redirect', 'nabu_no_author_archive', 9 );

/**
 * Leave the accounts out of the sitemap as well.
 *
 * @param WP_Sitemaps_Provider|false $provider Sitemap provider.
 * @param string                     $name     Provider name.
 * @return WP_Sitemaps_Provider|false
 */
function nabu_sitemap_without_users( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'nabu_sitemap_without_users', 10, 2 );

/**
 * Nor in the REST API for visitors: the editor, logged in, still gets the
 * accounts it offers as authors.
 *
 * @param array $endpoints REST routes.
 * @return array
 */
function nabu_rest_without_users( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\\d]+)'] );
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'nabu_rest_without_users' );

/**
 * Nor in the embed data other sites read for a link preview.
 *
 * @param array $data oEmbed response.
 * @return array
 */
function nabu_oembed_without_author( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
}
add_filter( 'oembed_response_data', 'nabu_oembed_without_author' );

/**
 * Address of the events overview, root-relative: patterns copy it into page
 * content, where an absolute address would break on a change of domain.
 *
 * @return string
 */
function nabu_events_url() {
	$archive = post_type_exists( 'nabu_event' ) ? get_post_type_archive_link( 'nabu_event' ) : '';

	return wp_make_link_relative( $archive ? $archive : home_url( '/' . nabu_group_get( 'events_slug' ) . '/' ) );
}

if ( ! function_exists( 'nabu_group_get' ) ) {
	/**
	 * Group settings without the plugin: the site title as name, the default
	 * events address, nothing else.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	function nabu_group_get( $key ) {
		if ( 'name' === $key ) {
			return (string) get_bloginfo( 'name', 'display' );
		}
		return 'events_slug' === $key ? 'termine' : '';
	}
}
