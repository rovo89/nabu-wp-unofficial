<?php
/**
 * Third-party content behind a two-click consent.
 *
 * Maps and videos load only once someone asks for them; until then a
 * placeholder says what would come from whom. The plugin Embed Privacy does
 * that for every embed in editorial content; the theme hands it its own
 * iframes too, so the map on the event page takes the same route. Nothing
 * leaves the site before the click, so there is no need for a consent tool.
 *
 * Without the plugin no iframe is printed, only the link.
 *
 * @package nabu-unofficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Put an iframe behind the Embed Privacy consent.
 *
 * @param string $src   Address of the iframe.
 * @param string $title Title of the iframe (accessibility).
 * @return string Empty without the plugin — never an unprotected iframe.
 */
function nabu_third_party_embed( $src, $title ) {
	if ( '' === $src || ! class_exists( '\epiphyt\Embed_Privacy\data\Replacer' ) ) {
		return '';
	}

	$iframe = sprintf(
		'<div class="nabu-embed"><iframe src="%1$s" title="%2$s" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>',
		esc_url( $src ),
		esc_attr( $title )
	);

	return (string) \epiphyt\Embed_Privacy\data\Replacer::replace_embeds( $iframe );
}

/**
 * Coordinates from editorial input: "51.1657, 10.4515" or a link from Google
 * Maps (`@lat,lon`, `q=lat,lon`) or OpenStreetMap (`mlat`/`mlon`, `#map=…`).
 *
 * @param string $input Field content.
 * @return array{0:float,1:float}|null
 */
function nabu_map_coordinates( $input ) {
	$input = trim( (string) $input );

	if ( '' === $input ) {
		return null;
	}

	$patterns = array(
		'/^(-?\d{1,2}(?:\.\d+)?)\s*[, ]\s*(-?\d{1,3}(?:\.\d+)?)$/',        // 51.1657, 10.4515
		'/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/',                              // Google: /@lat,lon,15z
		'/[?&](?:q|ll|query)=(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/',            // Google: ?q=lat,lon
		'/#map=\d+\/(-?\d{1,2}\.\d+)\/(-?\d{1,3}\.\d+)/',                    // OSM: #map=16/lat/lon
	);

	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $input, $m ) ) {
			return array( (float) $m[1], (float) $m[2] );
		}
	}

	if ( preg_match( '/[?&]mlat=(-?[\d.]+)/', $input, $a ) && preg_match( '/[?&]mlon=(-?[\d.]+)/', $input, $b ) ) {
		return array( (float) $a[1], (float) $b[1] );
	}

	return null;
}

/**
 * Map target of an event: coordinates if given, otherwise the address.
 *
 * Google Maps, because OpenStreetMap embeds need coordinates while Google
 * also finds a plain address. The keyless embed address
 * (`maps.google.com/maps?…&output=embed`) sits behind the consent for the
 * provider "Google Maps".
 *
 * @param string $position Field "Kartenposition".
 * @param string $address  Postal address.
 * @return array{src:string, link:string}|null src = iframe, link = open the map at the provider.
 */
function nabu_map_embed( $position, $address ) {
	$coordinates = nabu_map_coordinates( $position );

	if ( $coordinates ) {
		$q = sprintf( '%1$s,%2$s', $coordinates[0], $coordinates[1] );
	} else {
		// The address only: a location such as "Gasthaus zur Krone,
		// Nebenzimmer" misleads the search. Without an address, no map.
		$q = trim( (string) $address );
		if ( '' === $q ) {
			return null;
		}
	}

	return array(
		'src'  => 'https://maps.google.com/maps?q=' . rawurlencode( $q ) . '&z=15&output=embed',
		'link' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $q ),
	);
}
