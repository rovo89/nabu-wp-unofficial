<?php
/**
 * Title: Titelbild mit Handlungsaufruf
 * Slug: nabu-unofficial/hero
 * Categories: nabu, banner
 * Description: Breites Naturfoto, weiße Überschrift auf Abdunklung, ein orangener Handlungsaufruf.
 * Keywords: hero, titelbild, banner, startseite
 * Viewport width: 1400
 *
 * @package nabu-unofficial
 */

// Root-relative: the address is copied into the page content and has to
// survive a change of domain.
$nabu_img = esc_url( wp_make_link_relative( get_template_directory_uri() ) . '/assets/images/hero.jpg' );

// The place from the group settings, as editable text once inserted.
$nabu_city   = nabu_group_get( 'city' );
$nabu_around = $nabu_city ? sprintf( 'rund um %s', $nabu_city ) : 'in unserer Gemeinde';

?>
<!-- wp:cover {"url":"<?php echo $nabu_img; ?>","alt":"Streuobstwiese am Ortsrand im Morgenlicht","dimRatio":100,"gradient":"scrim","align":"full","className":"nabu-hero","layout":{"type":"constrained","contentSize":"940px"}} -->
<div class="wp-block-cover alignfull nabu-hero"><img class="wp-block-cover__image-background" alt="Streuobstwiese am Ortsrand im Morgenlicht" src="<?php echo $nabu_img; ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim wp-block-cover__gradient-background has-background-gradient has-scrim-gradient-background"></span><div class="wp-block-cover__inner-container">
	<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"600"}},"textColor":"white","fontSize":"xx-large"} -->
	<h1 class="wp-block-heading has-white-color has-text-color has-xx-large-font-size" style="font-weight:600">Natur vor der Haustür</h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"textColor":"white","fontSize":"lead"} -->
	<p class="has-white-color has-text-color has-lead-font-size">Wir kümmern uns um Streuobstwiesen, Hecken und Amphibien <?php echo esc_html( $nabu_around ); ?>.</p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)">
		<!-- wp:button {"className":"is-style-nabu-cta nabu-btn-arrow"} -->
		<div class="wp-block-button is-style-nabu-cta nabu-btn-arrow"><a class="wp-block-button__link wp-element-button" href="/mitmachen/">Mitmachen</a></div>
		<!-- /wp:button -->

		<!-- wp:button {"className":"is-style-nabu-ghost"} -->
		<div class="wp-block-button is-style-nabu-ghost"><a class="wp-block-button__link wp-element-button" href="/aktivitaeten/">Unsere Aktivitäten</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->

	<!-- wp:paragraph {"className":"nabu-credit"} -->
	<p class="nabu-credit">Foto: Platzhalter – vor dem Livegang durch ein eigenes Bild ersetzen</p>
	<!-- /wp:paragraph -->
</div></div>
<!-- /wp:cover -->
