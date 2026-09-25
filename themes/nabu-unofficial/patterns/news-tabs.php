<?php
/**
 * Title: Aktuelles und Termine (Reiter)
 * Slug: nabu-unofficial/news-tabs
 * Categories: nabu
 * Description: Das NABU-Reiterelement, aber nur mit eigenen Inhalten – keine Facebook-/YouTube-Einbettung. Ohne JavaScript bleiben beide Abschnitte untereinander lesbar.
 * Keywords: reiter, tabs, aktuelles, termine
 * Viewport width: 1400
 *
 * @package nabu-unofficial
 */

?>
<!-- wp:group {"align":"wide","className":"nabu-tabs","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide nabu-tabs">

	<!-- wp:group {"tagName":"section","layout":{"type":"default"}} -->
	<section class="wp-block-group">
		<!-- wp:heading {"level":2} -->
		<h2 class="wp-block-heading">Aktuelle Meldungen</h2>
		<!-- /wp:heading -->

		<!-- wp:query {"queryId":21,"query":{"perPage":3,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"layout":{"type":"default"}} -->
		<div class="wp-block-query">
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
		</div>
		<!-- /wp:query -->

		<!-- wp:paragraph {"className":"nabu-arrow-link"} -->
		<p class="nabu-arrow-link"><a href="/aktuelles/">Alle Meldungen</a></p>
		<!-- /wp:paragraph -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","layout":{"type":"default"}} -->
	<section class="wp-block-group">
		<!-- wp:heading {"level":2} -->
		<h2 class="wp-block-heading">Nächste Termine</h2>
		<!-- /wp:heading -->

		<!-- wp:nabu/events {"count":4,"range":"upcoming"} /-->

		<!-- wp:paragraph {"className":"nabu-arrow-link"} -->
		<p class="nabu-arrow-link"><a href="<?php echo esc_url( nabu_events_url() ); ?>">Alle Termine</a></p>
		<!-- /wp:paragraph -->
	</section>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
