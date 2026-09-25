<?php
/**
 * Title: Startseite (vollständig)
 * Slug: nabu-unofficial/front-page
 * Categories: nabu, featured
 * Description: Titelbild, die nächsten Termine, Meldungen der Gruppe, Aktivitäten, Neues vom NABU und ein Handlungsband.
 * Keywords: startseite, homepage, front page
 * Viewport width: 1400
 *
 * @package nabu-unofficial
 */

// Root-relative: the address is copied into the page content and has to
// survive a change of domain.
$nabu_img = esc_url( wp_make_link_relative( get_template_directory_uri() ) . '/assets/images/hero.jpg' );

// Name and place from the group settings, as editable text once inserted.
$nabu_group_name = nabu_group_get( 'name' );
$nabu_city       = nabu_group_get( 'city' );
$nabu_around     = $nabu_city ? sprintf( 'rund um %s', $nabu_city ) : 'in unserer Gemeinde';

?>
<!-- wp:cover {"url":"<?php echo $nabu_img; ?>","alt":"Streuobstwiese am Ortsrand im Morgenlicht","dimRatio":100,"gradient":"scrim","align":"full","className":"nabu-hero","layout":{"type":"constrained","contentSize":"940px"}} -->
<div class="wp-block-cover alignfull nabu-hero"><img class="wp-block-cover__image-background" alt="Streuobstwiese am Ortsrand im Morgenlicht" src="<?php echo $nabu_img; ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim wp-block-cover__gradient-background has-background-gradient has-scrim-gradient-background"></span><div class="wp-block-cover__inner-container">
	<!-- wp:heading {"level":1,"textColor":"white","fontSize":"xx-large"} -->
	<h1 class="wp-block-heading has-white-color has-text-color has-xx-large-font-size">Natur vor der Haustür</h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"textColor":"white","fontSize":"lead"} -->
	<p class="has-white-color has-text-color has-lead-font-size">Wir sind der <?php echo esc_html( $nabu_group_name ); ?> und kümmern uns um Streuobstwiesen, Hecken und Amphibien <?php echo esc_html( $nabu_around ); ?>.</p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)">
		<!-- wp:button {"className":"is-style-nabu-cta nabu-btn-arrow"} -->
		<div class="wp-block-button is-style-nabu-cta nabu-btn-arrow"><a class="wp-block-button__link wp-element-button" href="/mitmachen/">Mitmachen</a></div>
		<!-- /wp:button -->

		<!-- wp:button {"className":"is-style-nabu-ghost"} -->
		<div class="wp-block-button is-style-nabu-ghost"><a class="wp-block-button__link wp-element-button" href="/ueber-uns/">Über uns</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->

	<!-- wp:paragraph {"className":"nabu-credit"} -->
	<p class="nabu-credit">Foto: Platzhalter – vor dem Livegang durch ein eigenes Bild ersetzen</p>
	<!-- /wp:paragraph -->
</div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","className":"nabu-band","layout":{"type":"constrained","contentSize":"940px"}} -->
<div class="wp-block-group alignfull nabu-band">
	<!-- wp:heading {"level":2} -->
	<h2 class="wp-block-heading">Nächste Termine</h2>
	<!-- /wp:heading -->

	<!-- wp:nabu/events {"count":3,"range":"upcoming","layout":"cards","align":"wide"} /-->

	<!-- wp:group {"className":"nabu-events-foot","layout":{"type":"default"}} -->
	<div class="wp-block-group nabu-events-foot">
		<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"nabu/group","args":{"key":"meeting_line"}}}}} -->
		<p></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"nabu-arrow-link"} -->
		<p class="nabu-arrow-link"><a href="<?php echo esc_url( nabu_events_url() ); ?>">Alle Termine</a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"nabu-band","backgroundColor":"nabu-sand","layout":{"type":"constrained","contentSize":"940px"}} -->
<div class="wp-block-group alignfull nabu-band has-nabu-sand-background-color has-background">
	<!-- wp:heading {"level":2} -->
	<h2 class="wp-block-heading">Aus der Gruppe</h2>
	<!-- /wp:heading -->

	<!-- wp:query {"queryId":21,"query":{"perPage":3,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"align":"wide","layout":{"type":"default"}} -->
	<div class="wp-block-query alignwide">
		<!-- wp:post-template {"className":"nabu-grid"} -->
			<!-- wp:group {"className":"nabu-card","layout":{"type":"default"}} -->
			<div class="wp-block-group nabu-card">
				<!-- wp:post-featured-image {"isLink":true} /-->
				<!-- wp:group {"className":"nabu-card__body","layout":{"type":"default"}} -->
				<div class="wp-block-group nabu-card__body">
					<!-- wp:post-date {"format":"j. F Y","className":"nabu-kicker"} /-->
					<!-- wp:post-title {"isLink":true,"level":3} /-->
					<!-- wp:post-excerpt {"excerptLength":20,"showMoreOnNewLine":false} /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph --><p>Hier erscheinen die Meldungen der Gruppe.</p><!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->
	</div>
	<!-- /wp:query -->

	<!-- wp:paragraph {"className":"nabu-arrow-link"} -->
	<p class="nabu-arrow-link"><a href="/aktuelles/">Alle Meldungen</a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"nabu-band","layout":{"type":"constrained","contentSize":"940px"}} -->
<div class="wp-block-group alignfull nabu-band">
	<!-- wp:heading {"level":2} -->
	<h2 class="wp-block-heading">Woran wir arbeiten</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"className":"is-style-nabu-subtitle"} -->
	<p class="is-style-nabu-subtitle">Was bei uns das Jahr über läuft.</p>
	<!-- /wp:paragraph -->

	<!-- wp:query {"queryId":31,"query":{"perPage":3,"pages":1,"offset":0,"postType":"page","parents":[0],"order":"asc","orderBy":"menu_order","inherit":false},"align":"wide","layout":{"type":"default"}} -->
	<div class="wp-block-query alignwide">
		<!-- wp:post-template {"className":"nabu-grid"} -->
			<!-- wp:group {"className":"nabu-card nabu-card--sand","layout":{"type":"default"}} -->
			<div class="wp-block-group nabu-card nabu-card--sand">
				<!-- wp:post-featured-image {"isLink":true} /-->
				<!-- wp:group {"className":"nabu-card__body","layout":{"type":"default"}} -->
				<div class="wp-block-group nabu-card__body">
					<!-- wp:post-title {"isLink":true,"level":3} /-->
					<!-- wp:post-excerpt {"excerptLength":22,"showMoreOnNewLine":false} /-->
					<!-- wp:read-more {"content":"Mehr dazu","className":"nabu-arrow-link"} /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph --><p>Unsere Aktivitäten stellen wir hier in Kürze vor.</p><!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->
	</div>
	<!-- /wp:query -->

	<!-- wp:paragraph {"className":"nabu-arrow-link","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
	<p class="nabu-arrow-link" style="margin-top:var(--wp--preset--spacing--40)"><a href="/aktivitaeten/">Alle Aktivitäten</a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"nabu-band nabu-optional","backgroundColor":"nabu-sand","layout":{"type":"constrained","contentSize":"940px"}} -->
<div class="wp-block-group alignfull nabu-band nabu-optional has-nabu-sand-background-color has-background">
	<!-- wp:heading {"level":2} -->
	<h2 class="wp-block-heading">Neues vom NABU</h2>
	<!-- /wp:heading -->

	<!-- wp:nabu/association-news {"source":"federal","count":4,"align":"wide"} /-->

	<!-- wp:nabu/association-news {"source":"state","count":4,"newsOnly":true,"align":"wide"} /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"nabu-band","backgroundColor":"nabu-orange","textColor":"white","layout":{"type":"constrained","contentSize":"940px"}} -->
<div class="wp-block-group alignfull nabu-band has-white-color has-nabu-orange-background-color has-text-color has-background">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group">
		<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:heading {"level":2,"textColor":"white"} -->
			<h2 class="wp-block-heading has-white-color has-text-color">Werden Sie Mitglied</h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"textColor":"white"} -->
			<p class="has-white-color has-text-color">Ihr Beitrag finanziert die Arbeit vor Ort. Mitmachen können Sie auch ohne Mitgliedschaft.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button {"className":"is-style-nabu-ghost nabu-btn-arrow"} -->
			<div class="wp-block-button is-style-nabu-ghost nabu-btn-arrow"><a class="wp-block-button__link wp-element-button" href="/mitmachen/">Mitglied werden</a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
