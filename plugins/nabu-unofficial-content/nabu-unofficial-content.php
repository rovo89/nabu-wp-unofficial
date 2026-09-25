<?php
/**
 * Plugin Name:       NABU Unofficial: Inhalte
 * Plugin URI:        https://github.com/rovo89/nabu-wp-unofficial
 * Description:       Inhaltstypen und Zusatzfelder für die Website einer NABU-Gruppe: Termine mit Berichten, Meldungen der NABU-Verbände, Bildnachweise, Einstellungen zum Beitragsbild, Unterüberschriften, die Beschreibung für Suchmaschinen und die Gruppendaten. Als Plugin und nicht im Theme, damit die Inhalte einen Theme-Wechsel überleben.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Robert Vollmer
 * Author URI:        https://github.com/rovo89
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nabu-unofficial-content
 * Update URI:        false
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/inc/group.php';
require_once __DIR__ . '/inc/post-types.php';
require_once __DIR__ . '/inc/event-permalinks.php';
require_once __DIR__ . '/inc/events-page.php';
require_once __DIR__ . '/inc/event-meta.php';
require_once __DIR__ . '/inc/event-report.php';
require_once __DIR__ . '/inc/association-news.php';
require_once __DIR__ . '/inc/image-credit-field.php';
require_once __DIR__ . '/inc/featured-image-fields.php';
require_once __DIR__ . '/inc/subtitle-field.php';
require_once __DIR__ . '/inc/meta-description.php';

/**
 * Build the rewrite rules of the events archive on the next request. Not in
 * this one: while the plugin is being activated, the theme's fallback of
 * nabu_group_get() may be in charge, which knows only the default address.
 */
function nabu_content_activate() {
	update_option( 'nabu_group_flush_rewrite', '1' );
}
register_activation_hook( __FILE__, 'nabu_content_activate' );

/**
 * Drop the rewrite rules of the events archive. The content stays.
 */
function nabu_content_deactivate() {
	unregister_post_type( 'nabu_event' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nabu_content_deactivate' );
