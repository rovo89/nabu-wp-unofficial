<?php
/**
 * Title: Termine auf Sandband
 * Slug: nabu-unofficial/events-band
 * Categories: nabu
 * Description: Die nächsten Termine der Gruppe auf sandfarbenem Band. Braucht das Plugin „NABU Unofficial: Inhalte“.
 * Keywords: termine, veranstaltungen, kalender
 * Viewport width: 1400
 *
 * @package nabu-unofficial
 */

?>
<!-- wp:group {"align":"full","className":"nabu-band","backgroundColor":"nabu-sand","layout":{"type":"constrained","contentSize":"940px"}} -->
<div class="wp-block-group alignfull nabu-band has-nabu-sand-background-color has-background">
	<!-- wp:heading {"level":2} -->
	<h2 class="wp-block-heading">Nächste Termine</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"className":"is-style-nabu-subtitle"} -->
	<p class="is-style-nabu-subtitle">Alle Termine sind offen, Gäste willkommen.</p>
	<!-- /wp:paragraph -->

	<!-- wp:nabu/events {"count":4,"range":"upcoming"} /-->

	<!-- wp:paragraph {"className":"nabu-arrow-link","style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
	<p class="nabu-arrow-link" style="margin-top:var(--wp--preset--spacing--40)"><a href="<?php echo esc_url( nabu_events_url() ); ?>">Alle Termine</a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
