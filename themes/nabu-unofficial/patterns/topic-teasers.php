<?php
/**
 * Title: Thementeaser farbig (drei Spalten)
 * Slug: nabu-unofficial/topic-teasers
 * Categories: nabu, call-to-action
 * Description: Drei farbige Kacheln mit hellen Konturbuttons, für die Wege auf die wichtigsten Seiten.
 * Keywords: themen, farbig, teaser
 * Viewport width: 1400
 *
 * @package nabu-unofficial
 */

$nabu_topics = array(
	array( 'bg' => 'nabu-blue', 'title' => 'Über uns', 'text' => 'Wer wir sind, wer im Vorstand sitzt und wann wir uns treffen.', 'link' => '/ueber-uns/', 'label' => 'Die Gruppe' ),
	array( 'bg' => 'nabu-darkgreen', 'title' => 'Mitmachen', 'text' => 'Pflegeeinsätze, Kartierungen, Öffentlichkeitsarbeit – es gibt mehr zu tun als Hände.', 'link' => '/mitmachen/', 'label' => 'Aktiv werden' ),
	array( 'bg' => 'nabu-orange', 'title' => 'Aktivitäten', 'text' => 'Was wir das Jahr über tun – draußen auf den Flächen und im Ort.', 'link' => '/aktivitaeten/', 'label' => 'Aktivitäten ansehen' ),
);
?>
<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns">
<?php foreach ( $nabu_topics as $nabu_topic ) : ?>
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"backgroundColor":"<?php echo esc_attr( $nabu_topic['bg'] ); ?>","textColor":"white","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"7px"}},"layout":{"type":"default"}} -->
		<div class="wp-block-group has-white-color has-<?php echo esc_attr( $nabu_topic['bg'] ); ?>-background-color has-text-color has-background" style="border-radius:7px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)">
			<!-- wp:heading {"level":2,"textColor":"white","fontSize":"large"} -->
			<h2 class="wp-block-heading has-white-color has-text-color has-large-font-size"><?php echo esc_html( $nabu_topic['title'] ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"textColor":"white"} -->
			<p class="has-white-color has-text-color"><?php echo esc_html( $nabu_topic['text'] ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"is-style-nabu-ghost nabu-btn-arrow"} -->
				<div class="wp-block-button is-style-nabu-ghost nabu-btn-arrow"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $nabu_topic['link'] ); ?>"><?php echo esc_html( $nabu_topic['label'] ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns -->
