<?php
/**
 * Footer blocks that read the group settings and the page tree, so the
 * footer holds no group data and no page addresses:
 *
 * nabu/social-links — icon links to the networks set under Settings → NABU
 *                     Gruppe, plus e-mail.
 * nabu/footer-nav   — the top level of the site, as in the sidebar box.
 * nabu/legal-links  — the pages hidden from the navigation in the page tree
 *                     (Impressum, Datenschutz), then state and federal
 *                     association.
 *
 * With nothing in them to edit, the footer can stay as it ships with the
 * theme and follow its updates.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icons as inline SVG (24 × 24, currentColor).
 *
 * @return array<string, string>
 */
function nabu_social_icons() {
	return array(
		'mastodon'  => '<path d="M21.3 8.6c0-4.2-2.7-5.4-2.7-5.4C17.2 2.5 14.9 2.2 12.5 2.2h-.06c-2.4 0-4.7.3-6.1.96 0 0-2.7 1.2-2.7 5.4 0 .96-.02 2.1.01 3.3.1 4.1.74 8.1 4.5 9.1 1.7.46 3.2.56 4.4.5 2.2-.12 3.4-.79 3.4-.79l-.07-1.6s-1.6.5-3.3.44c-1.7-.06-3.6-.19-3.8-2.3a4 4 0 0 1-.04-.6s1.7.4 3.8.5c1.3.06 2.5-.08 3.8-.22 2.4-.29 4.4-1.7 4.7-3.1.4-2.1.4-5.2.4-5.2zm-3.2 5.4h-2V9.1c0-1-.4-1.5-1.3-1.5-.95 0-1.4.6-1.4 1.8v2.7h-2V9.4c0-1.2-.5-1.8-1.4-1.8-.86 0-1.3.5-1.3 1.5v4.9h-2V8.9c0-1 .26-1.8.78-2.4.54-.6 1.2-.9 2.1-.9 1 0 1.7.4 2.2 1.1l.5.8.5-.8c.5-.7 1.2-1.1 2.2-1.1.86 0 1.5.3 2.1.9.52.6.78 1.4.78 2.4z"/>',
		'instagram' => '<path d="M12 2.2c3.2 0 3.6 0 4.9.07 1.2.05 1.8.25 2.2.42.56.22.96.48 1.38.9.42.42.68.82.9 1.38.17.4.37 1 .42 2.2.06 1.3.07 1.7.07 4.9s0 3.6-.07 4.9c-.05 1.2-.25 1.8-.42 2.2-.22.56-.48.96-.9 1.38a3.7 3.7 0 0 1-1.38.9c-.4.17-1 .37-2.2.42-1.3.06-1.7.07-4.9.07s-3.6 0-4.9-.07c-1.2-.05-1.8-.25-2.2-.42a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.17-.4-.37-1-.42-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.9c.05-1.2.25-1.8.42-2.2.22-.56.48-.96.9-1.38a3.7 3.7 0 0 1 1.38-.9c.4-.17 1-.37 2.2-.42C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.1 0-3.5 0-4.8.07-1.1.05-1.7.24-2.1.4-.53.2-.9.45-1.3.85-.4.4-.65.77-.85 1.3-.16.4-.35 1-.4 2.1C2.5 8.5 2.5 8.9 2.5 12s0 3.5.07 4.8c.05 1.1.24 1.7.4 2.1.2.53.45.9.85 1.3.4.4.77.65 1.3.85.4.16 1 .35 2.1.4 1.3.06 1.7.07 4.8.07s3.5 0 4.8-.07c1.1-.05 1.7-.24 2.1-.4.53-.2.9-.45 1.3-.85.4-.4.65-.77.85-1.3.16-.4.35-1 .4-2.1.06-1.3.07-1.7.07-4.8s0-3.5-.07-4.8c-.05-1.1-.24-1.7-.4-2.1a3.5 3.5 0 0 0-.85-1.3 3.5 3.5 0 0 0-1.3-.85c-.4-.16-1-.35-2.1-.4C15.5 4 15.1 4 12 4zm0 3.1a4.9 4.9 0 1 1 0 9.8 4.9 4.9 0 0 1 0-9.8zm0 1.8a3.1 3.1 0 1 0 0 6.2 3.1 3.1 0 0 0 0-6.2zm5.1-3.2a1.15 1.15 0 1 1 0 2.3 1.15 1.15 0 0 1 0-2.3z"/>',
		'facebook'  => '<path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.6 1.6-1.6h1.7V4.4c-.3 0-1.3-.1-2.5-.1-2.5 0-4.1 1.5-4.1 4.2v2.3H7.4V14h2.8v8z"/>',
		'youtube'   => '<path d="M23 7.2a2.9 2.9 0 0 0-2-2C19.2 4.7 12 4.7 12 4.7s-7.2 0-9 .5a2.9 2.9 0 0 0-2 2C.5 9 .5 12 .5 12s0 3 .5 4.8a2.9 2.9 0 0 0 2 2c1.8.5 9 .5 9 .5s7.2 0 9-.5a2.9 2.9 0 0 0 2-2c.5-1.8.5-4.8.5-4.8s0-3-.5-4.8zM9.7 15.1V8.9l6 3.1z"/>',
		'email'     => '<path d="M2 5.5A1.5 1.5 0 0 1 3.5 4h17A1.5 1.5 0 0 1 22 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-17A1.5 1.5 0 0 1 2 18.5zm2 .5v.6l8 5.3 8-5.3V6zm16 2.9-8 5.3-8-5.3V18h16z"/>',
	);
}

/**
 * Render nabu/social-links.
 *
 * @return string Empty when nothing is set.
 */
function nabu_render_social_links() {
	$group = nabu_group_get( 'name' );
	$items = array(
		'mastodon'  => array( nabu_group_get( 'mastodon_url' ), 'Mastodon' ),
		'instagram' => array( nabu_group_get( 'instagram_url' ), 'Instagram' ),
		'facebook'  => array( nabu_group_get( 'facebook_url' ), 'Facebook' ),
		'youtube'   => array( nabu_group_get( 'youtube_url' ), 'YouTube' ),
	);
	$icons = nabu_social_icons();
	$html  = '';

	foreach ( $items as $key => $item ) {
		if ( '' === $item[0] ) {
			continue;
		}
		$html .= sprintf(
			'<a href="%1$s" rel="me noopener" aria-label="%2$s"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">%3$s</svg></a>',
			esc_url( $item[0] ),
			/* translators: 1: group name, 2: network. */
			esc_attr( sprintf( __( '%1$s bei %2$s', 'nabu-unofficial' ), $group, $item[1] ) ),
			$icons[ $key ]
		);
	}

	$email = nabu_group_get( 'email' );
	if ( '' !== $email ) {
		$html .= sprintf(
			'<a href="mailto:%1$s" aria-label="%2$s"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">%3$s</svg></a>',
			esc_attr( $email ),
			/* translators: %s: group name. */
			esc_attr( sprintf( __( 'E-Mail an %s', 'nabu-unofficial' ), $group ) ),
			$icons['email']
		);
	}

	if ( '' === $html ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="nabu-navbox__note">' . esc_html__( 'Keine sozialen Netzwerke eingetragen – Einstellungen → NABU Gruppe.', 'nabu-unofficial' ) . '</p>';
		}
		return '';
	}

	return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes( array( 'class' => 'nabu-social' ) ), $html );
}

/**
 * Render nabu/footer-nav: the top level of the site as a list.
 *
 * @return string
 */
function nabu_render_footer_nav() {
	$entries = nabu_sidenav_top_level();

	if ( ! $entries ) {
		return '';
	}

	$items = '';
	foreach ( $entries as $entry ) {
		$items .= '<li><a href="' . esc_url( $entry['url'] ) . '">' . esc_html( $entry['label'] ) . '</a></li>';
	}

	return sprintf( '<ul %1$s>%2$s</ul>', get_block_wrapper_attributes(), $items );
}

/**
 * Render nabu/legal-links.
 *
 * The top-level pages taken out of the navigation ("Hide in Nav Menu") are
 * the ones that belong in the footer's legal row. Without Nested Pages, the
 * privacy page from Settings → Datenschutz stands in.
 *
 * @return string
 */
function nabu_render_legal_links() {
	$pages = get_posts(
		array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'post_parent'            => 0,
			'posts_per_page'         => 20,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'meta_key'               => '_np_nav_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'             => 'hide', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	if ( ! $pages ) {
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		$pages   = $privacy ? array_filter( array( get_post( $privacy ) ) ) : array();
	}

	$items = '';
	foreach ( $pages as $page ) {
		$items .= '<li><a href="' . esc_url( (string) get_permalink( $page ) ) . '">' . esc_html( nabu_sidenav_label( $page ) ) . '</a></li>';
	}

	$state = function_exists( 'nabu_group_value' ) ? nabu_group_value( 'state_association_link' ) : '';
	if ( '' !== $state ) {
		$items .= '<li>' . $state . '</li>';
	}
	$items .= '<li><a href="https://www.nabu.de" rel="noopener">' . esc_html__( 'NABU Bundesverband', 'nabu-unofficial' ) . '</a></li>';

	return sprintf( '<ul %1$s>%2$s</ul>', get_block_wrapper_attributes(), $items );
}

/**
 * Register the blocks.
 */
function nabu_register_group_blocks() {
	$common = array(
		'api_version'   => 3,
		'category'      => 'theme',
		'editor_script' => 'nabu-blocks-editor',
		'supports'      => array(
			'html'   => false,
			'anchor' => true,
		),
	);

	register_block_type(
		'nabu/social-links',
		$common + array(
			'title'           => __( 'NABU Soziale Netzwerke', 'nabu-unofficial' ),
			'icon'            => 'share',
			'description'     => __( 'Symbole für Mastodon, Instagram, Facebook, YouTube und E-Mail – aus den Gruppen-Einstellungen; leere Felder erscheinen nicht.', 'nabu-unofficial' ),
			'render_callback' => 'nabu_render_social_links',
		)
	);

	register_block_type(
		'nabu/footer-nav',
		$common + array(
			'title'           => __( 'NABU Seitenübersicht', 'nabu-unofficial' ),
			'icon'            => 'list-view',
			'description'     => __( 'Die Hauptseiten der Website als schlichte Liste, für den Fußbereich. Folgt dem Seitenbaum.', 'nabu-unofficial' ),
			'render_callback' => 'nabu_render_footer_nav',
		)
	);

	register_block_type(
		'nabu/legal-links',
		$common + array(
			'title'           => __( 'NABU Rechtslinks', 'nabu-unofficial' ),
			'icon'            => 'shield',
			'description'     => __( 'Impressum, Datenschutz und alle weiteren Seiten, die im Seitenbaum aus der Navigation genommen sind, dazu Landesverband und Bundesverband.', 'nabu-unofficial' ),
			'render_callback' => 'nabu_render_legal_links',
		)
	);
}
add_action( 'init', 'nabu_register_group_blocks' );
