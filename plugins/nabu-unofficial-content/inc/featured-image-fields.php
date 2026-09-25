<?php
/**
 * Settings of the featured image, under the image in the editor.
 *
 * "Über dem Text zeigen": whether the image heads the post on its own page;
 * cards and tiles show it either way. "Bildausschnitt": cards and article
 * heads crop the image to a fixed format, and the chosen point stays in view.
 * The point is stored together with the image it belongs to, so a new
 * featured image starts from the centre again. The theme applies both.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the meta on every post type with a featured image. After the
 * post types, which register at the default priority.
 */
function nabu_featured_image_register_meta() {
	$auth = static function () {
		return current_user_can( 'edit_posts' );
	};

	foreach ( get_post_types_by_support( array( 'thumbnail', 'custom-fields' ) ) as $post_type ) {
		register_post_meta(
			$post_type,
			'_nabu_image_hidden',
			array(
				'type'          => 'boolean',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => false,
				'auth_callback' => $auth,
			)
		);
		register_post_meta(
			$post_type,
			'_nabu_image_focus',
			array(
				'type'          => 'object',
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'id' => array( 'type' => 'integer' ),
							'x'  => array(
								'type'    => 'number',
								'minimum' => 0,
								'maximum' => 1,
							),
							'y'  => array(
								'type'    => 'number',
								'minimum' => 0,
								'maximum' => 1,
							),
						),
					),
				),
				'auth_callback' => $auth,
			)
		);
	}
}
add_action( 'init', 'nabu_featured_image_register_meta', 20 );

/**
 * Whether the featured image is left out above the post's text.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function nabu_image_hidden( $post_id ) {
	return (bool) get_post_meta( $post_id, '_nabu_image_hidden', true );
}

/**
 * The focus of a post's featured image, as CSS percentages.
 *
 * @param int $post_id Post ID.
 * @return string Such as "50% 30%"; empty for the centre or none.
 */
function nabu_image_focus( $post_id ) {
	$focus = get_post_meta( $post_id, '_nabu_image_focus', true );

	if ( ! is_array( $focus ) || (int) ( $focus['id'] ?? 0 ) !== (int) get_post_thumbnail_id( $post_id ) ) {
		return '';
	}

	$x = round( 100 * max( 0, min( 1, (float) ( $focus['x'] ?? 0.5 ) ) ) );
	$y = round( 100 * max( 0, min( 1, (float) ( $focus['y'] ?? 0.5 ) ) ) );

	return 50.0 === $x && 50.0 === $y ? '' : $x . '% ' . $y . '%';
}

/**
 * The settings under the featured image in the editor.
 */
function nabu_featured_image_enqueue_editor() {
	wp_enqueue_script(
		'nabu-featured-image',
		plugins_url( 'assets/js/featured-image.js', __DIR__ ),
		array( 'wp-components', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n' ),
		(string) filemtime( dirname( __DIR__ ) . '/assets/js/featured-image.js' ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'nabu_featured_image_enqueue_editor' );
