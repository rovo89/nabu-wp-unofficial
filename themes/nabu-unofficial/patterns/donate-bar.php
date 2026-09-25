<?php
/**
 * Title: Spendenleiste (fixiert, abweisbar)
 * Slug: nabu-unofficial/donate-bar
 * Categories: nabu, call-to-action
 * Description: Am unteren Rand fixierte Spendenleiste. Abweisbar, die Entscheidung wird gespeichert, höchstens 15 % der Fensterhöhe. Sparsam einsetzen.
 * Keywords: spenden, leiste, sticky
 * Inserter: true
 *
 * @package nabu-unofficial
 */

?>
<!-- wp:html -->
<div class="nabu-donatebar" data-nabu-donatebar hidden>
	<p>Unsere Arbeit lebt von Spenden – jeder Betrag hilft.</p>
	<a class="nabu-donatebar__cta" href="/mitmachen/#spenden">Jetzt spenden</a>
	<button type="button" class="nabu-donatebar__close" aria-label="Spendenhinweis schließen">×</button>
</div>
<!-- /wp:html -->
