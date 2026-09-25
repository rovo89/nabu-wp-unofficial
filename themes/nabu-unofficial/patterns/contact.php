<?php
/**
 * Title: Kontaktblock
 * Slug: nabu-unofficial/contact
 * Categories: nabu, contact
 * Description: Anschrift, Kontakt und Hinweis auf die Treffen – aus Einstellungen → NABU Gruppe. Zwei Spalten, auf Sand.
 * Keywords: kontakt, adresse, ansprechpartner
 * Viewport width: 1400
 *
 * @package nabu-unofficial
 */

?>
<!-- wp:group {"align":"full","className":"nabu-band","backgroundColor":"nabu-sand","layout":{"type":"constrained","contentSize":"940px"}} -->
<div class="wp-block-group alignfull nabu-band has-nabu-sand-background-color has-background">
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|60","left":"var:preset|spacing|60"}}}} -->
	<div class="wp-block-columns">

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading">So erreichen Sie uns</h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"nabu/group","args":{"key":"address"}}}}} -->
			<p></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"nabu/group","args":{"key":"contact_lines_labelled"}}}}} -->
			<p></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size">Wir sind ehrenamtlich unterwegs – auf E-Mails antworten wir meist innerhalb einer Woche.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading">Wann wir uns treffen</h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"nabu/group","args":{"key":"meeting"}}}}} -->
			<p></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph -->
			<p>Wer neu ist, kommt einfach dazu – Anmeldung nicht nötig.</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"is-style-nabu-ghost-dark nabu-btn-arrow"} -->
				<div class="wp-block-button is-style-nabu-ghost-dark nabu-btn-arrow"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( nabu_events_url() ); ?>">Termine ansehen</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->

	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
