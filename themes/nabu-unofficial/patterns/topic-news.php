<?php
/**
 * Title: Meldungen zu einem Thema
 * Slug: nabu-unofficial/topic-news
 * Categories: nabu, posts
 * Description: Die Meldungen einer Kategorie als Kacheln, die neuesten zuerst, mit Blättern. Nach dem Einfügen im Abfrage-Loop unter Filter die Kategorie wählen.
 * Keywords: meldungen, berichte, kategorie, thema
 * Viewport width: 1000
 *
 * @package nabu-unofficial
 */

?>
<!-- wp:heading -->
<h2 class="wp-block-heading">Berichte</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":20,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"layout":{"type":"default"}} -->
<div class="wp-block-query">
	<!-- wp:post-template {"className":"nabu-grid"} -->
		<!-- wp:group {"className":"nabu-card nabu-card--sand","layout":{"type":"default"}} -->
		<div class="wp-block-group nabu-card nabu-card--sand">
			<!-- wp:post-featured-image {"isLink":true} /-->
			<!-- wp:group {"className":"nabu-card__body","layout":{"type":"default"}} -->
			<div class="wp-block-group nabu-card__body">
				<!-- wp:post-date {"format":"j. F Y","className":"nabu-kicker"} /-->
				<!-- wp:post-title {"isLink":true,"level":3} /-->
				<!-- wp:post-excerpt {"excerptLength":18,"showMoreOnNewLine":false} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph --><p>Noch keine Berichte.</p><!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->

	<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
		<!-- wp:query-pagination-previous {"label":"Neuere"} /-->
		<!-- wp:query-pagination-next {"label":"Ältere"} /-->
	<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->
