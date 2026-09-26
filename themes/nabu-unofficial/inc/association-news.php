<?php
/**
 * News of the federal or the state association as cards
 * (nabu/association-news). The companion plugin fetches the feeds and keeps
 * the images; without it the block prints nothing.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render news cards of an association.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function nabu_render_association_news( $attributes ) {
	$attributes = wp_parse_args(
		$attributes,
		array(
			'source'   => 'federal',
			'count'    => 4,
			'newsOnly' => false,
		)
	);

	if ( ! function_exists( 'nabu_association_news' ) ) {
		return '';
	}

	$source = nabu_association_source( $attributes['source'] );
	$items  = $source ? nabu_association_news( $attributes['source'], (int) $attributes['count'], (bool) $attributes['newsOnly'] ) : array();

	if ( ! $items ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="nabu-events__empty">' . esc_html__( 'Meldungen des Verbands – zurzeit nicht abrufbar oder kein Landesverband eingestellt.', 'nabu-unofficial' ) . '</p>';
		}
		return '';
	}

	$cards = '';
	foreach ( $items as $item ) {
		$image = '';
		if ( $item['image'] ) {
			// The small copy for tiles in a row, the original for a tile alone
			// on a phone. Items fetched before the copies existed have none.
			$small  = ! empty( $item['image_small'] ) && ! empty( $item['image_width'] );
			$srcset = $small ? sprintf(
				' srcset="%1$s %2$dw, %3$s %4$dw" sizes="auto, %5$s"',
				esc_url( $item['image_small'] ),
				NABU_NEWS_IMAGE_SMALL,
				esc_url( $item['image'] ),
				(int) $item['image_width'],
				esc_attr( NABU_CARD_SIZES_SMALL )
			) : '';
			$image  = sprintf(
				'<figure class="wp-block-post-featured-image"><img src="%1$s"%2$s alt="%3$s" loading="lazy" decoding="async"></figure>',
				esc_url( $small ? $item['image_small'] : $item['image'] ),
				$srcset,
				esc_attr( $item['alt'] )
			);
		}

		$cards .= sprintf(
			'<li><div class="nabu-card">%1$s<div class="nabu-card__body"><p class="nabu-kicker"><time datetime="%2$s">%3$s</time></p><h4 class="wp-block-post-title"><a href="%4$s">%5$s</a></h4>%6$s</div></div></li>',
			$image,
			esc_attr( wp_date( 'Y-m-d', $item['date'] ) ),
			esc_html( wp_date( 'j. F Y', $item['date'] ) ),
			esc_url( $item['link'] ),
			esc_html( $item['title'] ),
			$item['excerpt'] ? '<p>' . esc_html( wp_trim_words( $item['excerpt'], 16 ) ) . '</p>' : ''
		);
	}

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'nabu-association-news' ) );

	return sprintf(
		'<div %1$s><h3 class="wp-block-heading nabu-association-news__title">%2$s</h3><ul class="wp-block-post-template nabu-grid nabu-grid--small">%3$s</ul><p class="nabu-arrow-link"><a href="%4$s">%5$s</a></p></div>',
		$wrapper,
		esc_html( $source['name'] ),
		$cards,
		esc_url( $source['url'] ),
		/* translators: %s: name of the association. */
		esc_html( sprintf( __( 'Mehr bei %s', 'nabu-unofficial' ), 'federal' === $attributes['source'] ? 'NABU.de' : $source['name'] ) )
	);
}

/**
 * Register the block.
 */
function nabu_register_association_news_block() {
	register_block_type(
		'nabu/association-news',
		array(
			'api_version'     => 3,
			'title'           => __( 'NABU Verbandsmeldungen', 'nabu-unofficial' ),
			'category'        => 'theme',
			'icon'            => 'rss',
			'description'     => __( 'Die neuesten Meldungen des Bundes- oder Landesverbands als Kacheln.', 'nabu-unofficial' ),
			'editor_script'   => 'nabu-blocks-editor',
			'attributes'      => array(
				'source'   => array(
					'type'    => 'string',
					'default' => 'federal',
				),
				'count'    => array(
					'type'    => 'number',
					'default' => 4,
				),
				'newsOnly' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'supports'        => array(
				'html'  => false,
				'align' => array( 'wide' ),
			),
			'render_callback' => 'nabu_render_association_news',
		)
	);
}
add_action( 'init', 'nabu_register_association_news_block' );
