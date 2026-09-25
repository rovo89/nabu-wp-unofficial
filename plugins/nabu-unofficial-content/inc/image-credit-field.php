<?php
/**
 * Image credit on the media file (German UI: "Bildnachweis").
 *
 * NABU credits every photo in its caption: "Foto: NABU/F. Mustermann". The
 * credit is stored on the file, not the post, so it travels with the image
 * wherever it is used; the theme prints it.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the meta.
 */
function nabu_image_credit_register_meta() {
	register_post_meta(
		'attachment',
		'_nabu_image_credit',
		array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => '',
			'auth_callback' => static function () {
				return current_user_can( 'upload_files' );
			},
		)
	);
}
add_action( 'init', 'nabu_image_credit_register_meta' );

/**
 * Add the field to the attachment details.
 *
 * @param array   $form_fields Fields.
 * @param WP_Post $post        Attachment.
 * @return array
 */
function nabu_image_credit_field( $form_fields, $post ) {
	$form_fields['nabu_image_credit'] = array(
		'label' => __( 'Bildnachweis', 'nabu-unofficial-content' ),
		'input' => 'text',
		'value' => get_post_meta( $post->ID, '_nabu_image_credit', true ),
		'helps' => __( 'Zum Beispiel „Foto: NABU/F. Mustermann“. Erscheint automatisch an der Bildunterschrift.', 'nabu-unofficial-content' ),
	);
	return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'nabu_image_credit_field', 10, 2 );

/**
 * Save the field.
 *
 * @param array $post       Attachment data.
 * @param array $attachment Submitted fields.
 * @return array
 */
function nabu_image_credit_save( $post, $attachment ) {
	if ( isset( $attachment['nabu_image_credit'] ) ) {
		$value = sanitize_text_field( $attachment['nabu_image_credit'] );
		if ( '' === $value ) {
			delete_post_meta( $post['ID'], '_nabu_image_credit' );
		} else {
			update_post_meta( $post['ID'], '_nabu_image_credit', $value );
		}
	}
	return $post;
}
add_filter( 'attachment_fields_to_save', 'nabu_image_credit_save', 10, 2 );

/**
 * Notice in the media library when alt text or credit is missing.
 *
 * Not enforced — images without alt text can still be saved — but the gap is
 * visible in the back end instead of only in an audit.
 *
 * @param array   $form_fields Fields.
 * @param WP_Post $post        Attachment.
 * @return array
 */
function nabu_image_credit_notice( $form_fields, $post ) {
	if ( 0 !== strpos( (string) get_post_mime_type( $post ), 'image/' ) ) {
		return $form_fields;
	}

	$missing = array();

	if ( '' === trim( (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ) ) {
		$missing[] = __( 'Alternativtext', 'nabu-unofficial-content' );
	}
	if ( '' === trim( (string) get_post_meta( $post->ID, '_nabu_image_credit', true ) ) ) {
		$missing[] = __( 'Bildnachweis', 'nabu-unofficial-content' );
	}

	if ( $missing ) {
		$form_fields['nabu_image_credit_notice'] = array(
			'label' => '',
			'input' => 'html',
			'html'  => '<p style="color:#8b1319;margin:0">' . sprintf(
				/* translators: %s: list of missing items. */
				esc_html__( 'Es fehlt noch: %s', 'nabu-unofficial-content' ),
				esc_html( implode( ', ', $missing ) )
			) . '</p>',
		);
	}

	return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'nabu_image_credit_notice', 20, 2 );
