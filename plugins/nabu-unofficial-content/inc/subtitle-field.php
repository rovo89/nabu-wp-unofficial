<?php
/**
 * Subtitle for posts (German UI: "Unterüberschrift"), shown between title and
 * featured image. A classic meta box, like the event details: it works in
 * the block editor without custom JavaScript.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the meta for REST and WP-CLI.
 */
function nabu_subtitle_register_meta() {
	register_post_meta(
		'post',
		'_nabu_subtitle',
		array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => '',
			'auth_callback' => static function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'nabu_subtitle_register_meta' );

/**
 * Register the meta box, in the sidebar like the event details.
 */
function nabu_subtitle_meta_box() {
	add_meta_box(
		'nabu-subtitle',
		__( 'Unterüberschrift', 'nabu-unofficial-content' ),
		'nabu_subtitle_meta_box_render',
		'post',
		'side',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes', 'nabu_subtitle_meta_box' );

/**
 * Render the meta box.
 *
 * @param WP_Post $post Current post.
 */
function nabu_subtitle_meta_box_render( $post ) {
	wp_nonce_field( 'nabu_subtitle_save', 'nabu_subtitle_nonce' );

	$value = get_post_meta( $post->ID, '_nabu_subtitle', true );

	printf(
		'<input type="text" id="nabu-subtitle" name="nabu_subtitle" value="%s" class="widefat">',
		esc_attr( $value )
	);
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Erscheint zwischen Titel und Beitragsbild. Leer lassen, wenn der Titel reicht.', 'nabu-unofficial-content' )
	);
}

/**
 * Save the meta box.
 *
 * @param int $post_id Post ID.
 */
function nabu_subtitle_meta_box_save( $post_id ) {
	if ( ! isset( $_POST['nabu_subtitle_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nabu_subtitle_nonce'] ) ), 'nabu_subtitle_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$value = isset( $_POST['nabu_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['nabu_subtitle'] ) ) : '';

	if ( '' === $value ) {
		delete_post_meta( $post_id, '_nabu_subtitle' );
	} else {
		update_post_meta( $post_id, '_nabu_subtitle', $value );
	}
}
add_action( 'save_post_post', 'nabu_subtitle_meta_box_save' );
