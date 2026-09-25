<?php
/**
 * Title: Teaser-Karten (drei Spalten)
 * Slug: nabu-unofficial/teaser-grid
 * Categories: nabu, posts
 * Description: Bild oben, getönter Textkörper, blaue Überschrift, Pfeil-Link. Die ganze Karte ist klickbar.
 * Keywords: karten, teaser, raster
 * Viewport width: 1400
 *
 * @package nabu-unofficial
 */

// Root-relative: the address is copied into the page content and has to
// survive a change of domain.
$nabu_base = esc_url( wp_make_link_relative( get_template_directory_uri() ) . '/assets/images' );

$nabu_cards = array(
	array(
		'image' => 'streuobstwiese.jpg',
		'alt'   => 'Alte Obstbäume auf einer Wiese',
		'title' => 'Streuobstwiesen',
		'text'  => 'Platzhalter: Was tut die Gruppe für ihre Streuobstwiesen?',
		'link'  => '/aktivitaeten/streuobstwiese/',
		'class' => '',
	),
	array(
		'image' => 'amphibien.jpg',
		'alt'   => 'Feuchtbiotop mit Schilf',
		'title' => 'Amphibienschutz',
		'text'  => 'Platzhalter: Was tut die Gruppe für Amphibien?',
		'link'  => '/aktivitaeten/amphibienschutz/',
		'class' => 'nabu-card--tint',
	),
	array(
		'image' => 'nistkasten.jpg',
		'alt'   => 'Nistkasten an einem Baumstamm',
		'title' => 'Nistkästen',
		'text'  => 'Platzhalter: Was tut die Gruppe für Höhlenbrüter?',
		'link'  => '/aktivitaeten/nistkaesten/',
		'class' => '',
	),
);
?>
<!-- wp:columns {"className":"nabu-cards","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns nabu-cards">
<?php foreach ( $nabu_cards as $nabu_card ) : ?>
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"className":"nabu-card <?php echo esc_attr( $nabu_card['class'] ); ?>","layout":{"type":"default"}} -->
		<div class="wp-block-group nabu-card <?php echo esc_attr( $nabu_card['class'] ); ?>">
			<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
			<figure class="wp-block-image size-large"><img src="<?php echo $nabu_base . '/' . esc_attr( $nabu_card['image'] ); ?>" alt="<?php echo esc_attr( $nabu_card['alt'] ); ?>"/></figure>
			<!-- /wp:image -->

			<!-- wp:group {"className":"nabu-card__body","layout":{"type":"default"}} -->
			<div class="wp-block-group nabu-card__body">
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading"><a href="<?php echo esc_url( $nabu_card['link'] ); ?>"><?php echo esc_html( $nabu_card['title'] ); ?></a></h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph -->
				<p><?php echo esc_html( $nabu_card['text'] ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"className":"nabu-arrow-link"} -->
				<p class="nabu-arrow-link"><a href="<?php echo esc_url( $nabu_card['link'] ); ?>">Mehr dazu</a></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns -->
