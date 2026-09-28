<?php
/**
 * Group settings (German UI: "NABU Gruppe").
 *
 * Everything that differs from one local group to the next lives here and in
 * no theme file: name, address, contact, state association, register entry,
 * bank account, meeting, social links, the membership number and the address
 * of the events archive.
 * Stored in the option `nabu_group`, edited under Settings → NABU Gruppe and
 * exposed as the block bindings source `nabu/group`, so the footer, the
 * contact block and the legal pages show one value instead of three copies.
 *
 * @package nabu-unofficial-content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NABU state associations. In Bavaria the LBV is NABU's partner. `short` is
 * the form for tight spots such as the service navigation, where the
 * association uses one.
 *
 * @return array<string, array{name:string, url:string, short?:string}>
 */
function nabu_group_state_associations() {
	return array(
		'bw' => array( 'name' => 'NABU Baden-Württemberg', 'url' => 'https://baden-wuerttemberg.nabu.de', 'short' => 'NABU BW' ),
		'by' => array( 'name' => 'LBV Bayern (NABU-Partner)', 'url' => 'https://www.lbv.de', 'short' => 'LBV Bayern' ),
		'be' => array( 'name' => 'NABU Berlin', 'url' => 'https://berlin.nabu.de' ),
		'bb' => array( 'name' => 'NABU Brandenburg', 'url' => 'https://brandenburg.nabu.de' ),
		'hb' => array( 'name' => 'NABU Bremen', 'url' => 'https://bremen.nabu.de' ),
		'hh' => array( 'name' => 'NABU Hamburg', 'url' => 'https://hamburg.nabu.de' ),
		'he' => array( 'name' => 'NABU Hessen', 'url' => 'https://hessen.nabu.de' ),
		'mv' => array( 'name' => 'NABU Mecklenburg-Vorpommern', 'url' => 'https://mecklenburg-vorpommern.nabu.de' ),
		'ni' => array( 'name' => 'NABU Niedersachsen', 'url' => 'https://niedersachsen.nabu.de' ),
		'nw' => array( 'name' => 'NABU Nordrhein-Westfalen', 'url' => 'https://nrw.nabu.de', 'short' => 'NABU NRW' ),
		'rp' => array( 'name' => 'NABU Rheinland-Pfalz', 'url' => 'https://rlp.nabu.de', 'short' => 'NABU RLP' ),
		'sl' => array( 'name' => 'NABU Saarland', 'url' => 'https://saarland.nabu.de' ),
		'sn' => array( 'name' => 'NABU Sachsen', 'url' => 'https://sachsen.nabu.de' ),
		'st' => array( 'name' => 'NABU Sachsen-Anhalt', 'url' => 'https://sachsen-anhalt.nabu.de' ),
		'sh' => array( 'name' => 'NABU Schleswig-Holstein', 'url' => 'https://schleswig-holstein.nabu.de' ),
		'th' => array( 'name' => 'NABU Thüringen', 'url' => 'https://thueringen.nabu.de' ),
	);
}

/**
 * Field definitions. The key is the key inside the option and the argument
 * the bindings source takes.
 *
 * @return array<string, array<string, mixed>>
 */
function nabu_group_fields() {
	return array(
		'name'                    => array( 'section' => 'group', 'label' => __( 'Name der Gruppe', 'nabu-unofficial-content' ), 'type' => 'text', 'help' => __( 'Zum Beispiel „NABU Musterstadt“. Leer: der Titel der Website.', 'nabu-unofficial-content' ) ),
		'legal_name'              => array( 'section' => 'group', 'label' => __( 'Offizielle Bezeichnung', 'nabu-unofficial-content' ), 'type' => 'text', 'help' => __( 'Für das Impressum, etwa „Naturschutzbund Deutschland e. V., Ortsgruppe Musterstadt“. Leer: der Name der Gruppe.', 'nabu-unofficial-content' ) ),
		'state_association'       => array( 'section' => 'group', 'label' => __( 'Landesverband', 'nabu-unofficial-content' ), 'type' => 'select' ),
		'state_association_short' => array( 'section' => 'group', 'label' => __( 'Landesverband, kurz', 'nabu-unofficial-content' ), 'type' => 'text', 'help' => __( 'Für die kleine Navigation oben, etwa „NABU BW“. Leer: die Kurzform des Verbands, wenn er eine hat, sonst der volle Name.', 'nabu-unofficial-content' ) ),
		'representative'          => array( 'section' => 'group', 'label' => __( 'Vertreten durch', 'nabu-unofficial-content' ), 'type' => 'text', 'help' => __( 'Der vertretungsberechtigte Vorstand, für das Impressum.', 'nabu-unofficial-content' ) ),
		'member_number'           => array( 'section' => 'group', 'label' => __( 'VW-Nummer', 'nabu-unofficial-content' ), 'type' => 'member_number', 'help' => __( 'Die Werbernummer der Gruppe, zugleich ihre Gruppennummer beim NABU, etwa „12345“. Die Adresse /mitglied-werden/ führt dann auf das Beitrittsformular des NABU für die Gruppe, und neue Mitglieder werden ihr zugeordnet. Leer: das allgemeine Formular.', 'nabu-unofficial-content' ) ),
		'meeting'                 => array( 'section' => 'group', 'label' => __( 'Treffen', 'nabu-unofficial-content' ), 'type' => 'textarea', 'help' => __( 'Zum Beispiel „Jeden ersten Donnerstag um 19:30 Uhr im Nebenzimmer des Gasthauses“.', 'nabu-unofficial-content' ) ),

		'street'                  => array( 'section' => 'contact', 'label' => __( 'Straße und Hausnummer', 'nabu-unofficial-content' ), 'type' => 'text' ),
		'postal_code'             => array( 'section' => 'contact', 'label' => __( 'Postleitzahl', 'nabu-unofficial-content' ), 'type' => 'text' ),
		'city'                    => array( 'section' => 'contact', 'label' => __( 'Ort', 'nabu-unofficial-content' ), 'type' => 'text' ),
		'phone'                   => array( 'section' => 'contact', 'label' => __( 'Telefon', 'nabu-unofficial-content' ), 'type' => 'text', 'help' => __( 'So, wie die Nummer angezeigt werden soll, etwa „01234 567890“.', 'nabu-unofficial-content' ) ),
		'email'                   => array( 'section' => 'contact', 'label' => __( 'E-Mail', 'nabu-unofficial-content' ), 'type' => 'email' ),

		'register_court'          => array( 'section' => 'legal', 'label' => __( 'Registergericht', 'nabu-unofficial-content' ), 'type' => 'text', 'help' => __( 'Nur bei eingetragenem Verein, etwa „Amtsgericht Musterstadt“.', 'nabu-unofficial-content' ) ),
		'register_number'         => array( 'section' => 'legal', 'label' => __( 'Registernummer', 'nabu-unofficial-content' ), 'type' => 'text' ),

		'account_holder'          => array( 'section' => 'donations', 'label' => __( 'Kontoinhaber', 'nabu-unofficial-content' ), 'type' => 'text' ),
		'iban'                    => array( 'section' => 'donations', 'label' => __( 'IBAN', 'nabu-unofficial-content' ), 'type' => 'text' ),
		'bic'                     => array( 'section' => 'donations', 'label' => __( 'BIC', 'nabu-unofficial-content' ), 'type' => 'text' ),
		'bank'                    => array( 'section' => 'donations', 'label' => __( 'Bank', 'nabu-unofficial-content' ), 'type' => 'text' ),

		'mastodon_url'            => array( 'section' => 'social', 'label' => __( 'Mastodon', 'nabu-unofficial-content' ), 'type' => 'url' ),
		'instagram_url'           => array( 'section' => 'social', 'label' => __( 'Instagram', 'nabu-unofficial-content' ), 'type' => 'url' ),
		'facebook_url'            => array( 'section' => 'social', 'label' => __( 'Facebook', 'nabu-unofficial-content' ), 'type' => 'url' ),
		'youtube_url'             => array( 'section' => 'social', 'label' => __( 'YouTube', 'nabu-unofficial-content' ), 'type' => 'url' ),

		'events_slug'             => array( 'section' => 'urls', 'label' => __( 'Adresse der Terminübersicht', 'nabu-unofficial-content' ), 'type' => 'slug', 'default' => 'termine', 'help' => __( 'Der Teil der Adresse nach der Domain, etwa „termine“ für /termine/.', 'nabu-unofficial-content' ) ),
	);
}

/**
 * Sections of the settings page.
 *
 * @return array<string, string>
 */
function nabu_group_sections() {
	return array(
		'group'     => __( 'Gruppe', 'nabu-unofficial-content' ),
		'contact'   => __( 'Anschrift und Kontakt', 'nabu-unofficial-content' ),
		'legal'     => __( 'Vereinsregister', 'nabu-unofficial-content' ),
		'donations' => __( 'Spendenkonto', 'nabu-unofficial-content' ),
		'social'    => __( 'Soziale Netzwerke', 'nabu-unofficial-content' ),
		'urls'      => __( 'Adressen', 'nabu-unofficial-content' ),
	);
}

/*
 * The theme brings a minimal nabu_group_get() so it renders without this
 * plugin. Plugins load first, so this version normally wins; only in the
 * request that activates the plugin is the theme's already defined.
 */
if ( ! function_exists( 'nabu_group_get' ) ) {
	/**
	 * Read one raw setting, with its default.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	function nabu_group_get( $key ) {
		$option = get_option( 'nabu_group', array() );
		$fields = nabu_group_fields();
		$value  = is_array( $option ) && isset( $option[ $key ] ) ? (string) $option[ $key ] : '';

		if ( '' === $value && isset( $fields[ $key ]['default'] ) ) {
			$value = (string) $fields[ $key ]['default'];
		}
		if ( '' === $value && 'name' === $key ) {
			$value = (string) get_bloginfo( 'name', 'display' );
		}

		return $value;
	}
}

/**
 * Sanitize the whole option on save.
 *
 * @param mixed $input Submitted values.
 * @return array<string, string>
 */
function nabu_group_sanitize( $input ) {
	$input  = is_array( $input ) ? $input : array();
	$output = array();

	foreach ( nabu_group_fields() as $key => $field ) {
		$raw = isset( $input[ $key ] ) ? (string) wp_unslash( $input[ $key ] ) : '';

		switch ( $field['type'] ) {
			case 'url':
				$value = esc_url_raw( trim( $raw ) );
				break;
			case 'email':
				$value = sanitize_email( $raw );
				break;
			case 'textarea':
				$value = sanitize_textarea_field( $raw );
				break;
			case 'select':
				$value = isset( nabu_group_state_associations()[ $raw ] ) ? $raw : '';
				break;
			case 'slug':
				$value = sanitize_title( $raw );
				break;
			case 'member_number':
				// Taken as given, "12345" or "VW0012345"; stored without padding.
				$value = ltrim( (string) preg_replace( '/\D/', '', $raw ), '0' );
				break;
			default:
				$value = sanitize_text_field( $raw );
		}

		$output[ $key ] = $value;
	}

	// The archive slug is part of the rewrite rules.
	$old      = get_option( 'nabu_group', array() );
	$old_slug = ( is_array( $old ) ? ( $old['events_slug'] ?? '' ) : '' ) ?: 'termine';
	if ( ( $output['events_slug'] ?: 'termine' ) !== $old_slug ) {
		update_option( 'nabu_group_flush_rewrite', '1' );
	}

	return $output;
}

/**
 * Flush the rewrite rules once after activation or a change of the archive
 * slug, on the next request, when the post type is registered with it.
 */
function nabu_group_maybe_flush() {
	if ( '1' === get_option( 'nabu_group_flush_rewrite' ) ) {
		delete_option( 'nabu_group_flush_rewrite' );
		flush_rewrite_rules();
	}
}
add_action( 'init', 'nabu_group_maybe_flush', 99 );

/**
 * Register the option and the settings page.
 */
function nabu_group_register_settings() {
	register_setting(
		'nabu_group',
		'nabu_group',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'nabu_group_sanitize',
			'default'           => array(),
		)
	);

	foreach ( nabu_group_sections() as $section => $title ) {
		add_settings_section( 'nabu_group_' . $section, $title, '__return_null', 'nabu_group' );
	}

	foreach ( nabu_group_fields() as $key => $field ) {
		add_settings_field(
			'nabu_group_' . $key,
			$field['label'],
			'nabu_group_render_field',
			'nabu_group',
			'nabu_group_' . $field['section'],
			array(
				'key'       => $key,
				'field'     => $field,
				'label_for' => 'nabu_group_' . $key,
			)
		);
	}
}
add_action( 'admin_init', 'nabu_group_register_settings' );

/**
 * Render one field.
 *
 * @param array $args Field arguments.
 */
function nabu_group_render_field( $args ) {
	$key   = $args['key'];
	$field = $args['field'];
	$id    = 'nabu_group_' . $key;
	$name  = 'nabu_group[' . $key . ']';
	$raw   = get_option( 'nabu_group', array() );
	$value = is_array( $raw ) && isset( $raw[ $key ] ) ? (string) $raw[ $key ] : '';

	if ( 'select' === $field['type'] ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		echo '<option value="">' . esc_html__( '— bitte wählen —', 'nabu-unofficial-content' ) . '</option>';
		foreach ( nabu_group_state_associations() as $code => $association ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $code ), selected( $value, $code, false ), esc_html( $association['name'] ) );
		}
		echo '</select>';
	} elseif ( 'textarea' === $field['type'] ) {
		printf( '<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
	} else {
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text"%5$s>',
			esc_attr( in_array( $field['type'], array( 'url', 'email' ), true ) ? $field['type'] : 'text' ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			isset( $field['default'] ) ? ' placeholder="' . esc_attr( $field['default'] ) . '"' : ''
		);
	}

	if ( ! empty( $field['help'] ) ) {
		echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
	}
}

/**
 * Add the page under Settings.
 */
function nabu_group_admin_menu() {
	add_options_page(
		__( 'NABU Gruppe', 'nabu-unofficial-content' ),
		__( 'NABU Gruppe', 'nabu-unofficial-content' ),
		'manage_options',
		'nabu_group',
		'nabu_group_render_page'
	);
}
add_action( 'admin_menu', 'nabu_group_admin_menu' );

/**
 * Render the settings page.
 */
function nabu_group_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'NABU Gruppe', 'nabu-unofficial-content' ) . '</h1>';
	echo '<p>' . esc_html__( 'Diese Angaben stehen im Kopf- und Fußbereich, im Kontaktblock und auf den Rechtsseiten – einmal gepflegt, überall gleich.', 'nabu-unofficial-content' ) . '</p>';
	echo '<form action="options.php" method="post">';
	settings_fields( 'nabu_group' );
	do_settings_sections( 'nabu_group' );
	submit_button();
	echo '</form></div>';
}

/**
 * Keys the bindings source takes besides the fields: values that
 * nabu_group_value() derives from them.
 *
 * @return string[]
 */
function nabu_group_composed_keys() {
	return array(
		'address',
		'legal_address',
		'contact_lines',
		'contact_lines_labelled',
		'state_association_name',
		'state_association_url',
		'state_association_link',
		'legal_form',
		'bank_account',
		'meeting_line',
		'events_url',
	);
}

/**
 * A setting ready for output, raw or composed from several fields.
 *
 * @param string $key Field key or one of nabu_group_composed_keys().
 * @return string Escaped; HTML for composed keys.
 */
function nabu_group_value( $key ) {
	$associations = nabu_group_state_associations();
	$association  = $associations[ nabu_group_get( 'state_association' ) ] ?? null;
	$city_line    = trim( nabu_group_get( 'postal_code' ) . ' ' . nabu_group_get( 'city' ) );

	switch ( $key ) {
		case 'address':
			return implode( '<br>', array_map( 'esc_html', array_filter( array( nabu_group_get( 'name' ), nabu_group_get( 'street' ), $city_line ) ) ) );

		case 'legal_address':
			// The imprint names the official designation, not the short name.
			$name = nabu_group_get( 'legal_name' ) ?: nabu_group_get( 'name' );
			return implode( '<br>', array_map( 'esc_html', array_filter( array( $name, nabu_group_get( 'street' ), $city_line ) ) ) );

		case 'contact_lines':
		case 'contact_lines_labelled':
			$labelled = 'contact_lines_labelled' === $key;
			$lines    = array();
			$phone    = nabu_group_get( 'phone' );
			$email    = nabu_group_get( 'email' );
			if ( $phone ) {
				$lines[] = ( $labelled ? esc_html__( 'Telefon:', 'nabu-unofficial-content' ) . ' ' : '' )
					. '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
			}
			if ( $email ) {
				$lines[] = ( $labelled ? esc_html__( 'E-Mail:', 'nabu-unofficial-content' ) . ' ' : '' )
					. '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
			}
			return implode( '<br>', $lines );

		case 'state_association_name':
			return $association ? esc_html( $association['name'] ) : '';

		case 'state_association_short':
			return $association ? esc_html( nabu_group_get( 'state_association_short' ) ?: ( $association['short'] ?? $association['name'] ) ) : '';

		case 'state_association_url':
			return $association ? esc_url( $association['url'] ) : '';

		case 'state_association_link':
			return $association ? '<a href="' . esc_url( $association['url'] ) . '" rel="noopener">' . esc_html( $association['name'] ) . '</a>' : '';

		case 'legal_form':
			// Most groups are not registered associations of their own but
			// local branches of NABU; the imprint says which case applies.
			$register = array_filter(
				array(
					/* translators: %s: register court. */
					nabu_group_get( 'register_court' ) ? sprintf( __( 'Registergericht: %s', 'nabu-unofficial-content' ), nabu_group_get( 'register_court' ) ) : '',
					/* translators: %s: register number. */
					nabu_group_get( 'register_number' ) ? sprintf( __( 'Registernummer: %s', 'nabu-unofficial-content' ), nabu_group_get( 'register_number' ) ) : '',
				)
			);
			$form     = $register
				/* translators: %s: register court and number. */
				? sprintf( __( 'Die Gruppe ist ein eingetragener Verein (%s).', 'nabu-unofficial-content' ), implode( ', ', $register ) )
				: __( 'Die Gruppe ist ein nicht im Vereinsregister eingetragener Verein.', 'nabu-unofficial-content' );
			$membership = $association
				? sprintf(
					/* translators: %s: link to the state association. */
					esc_html__( 'Sie ist eine örtliche Gruppe des NABU (Naturschutzbund Deutschland) e. V. und gehört zum Landesverband %s.', 'nabu-unofficial-content' ),
					'<a href="' . esc_url( $association['url'] ) . '" rel="noopener">' . esc_html( $association['name'] ) . '</a>'
				)
				: esc_html__( 'Sie ist eine örtliche Gruppe des NABU (Naturschutzbund Deutschland) e. V.', 'nabu-unofficial-content' );
			return esc_html( $form ) . ' ' . $membership;

		case 'bank_account':
			$lines = array_filter(
				array(
					esc_html( nabu_group_get( 'account_holder' ) ),
					nabu_group_get( 'iban' ) ? 'IBAN: ' . esc_html( nabu_group_get( 'iban' ) ) : '',
					nabu_group_get( 'bic' ) ? 'BIC: ' . esc_html( nabu_group_get( 'bic' ) ) : '',
					esc_html( nabu_group_get( 'bank' ) ),
				)
			);
			return implode( '<br>', $lines );

		case 'meeting_line':
			$meeting = nabu_group_get( 'meeting' );
			return '' === $meeting ? '' : '<strong>' . esc_html__( 'Regelmäßig:', 'nabu-unofficial-content' ) . '</strong> ' . esc_html( $meeting );

		case 'events_url':
			return esc_url( home_url( '/' . nabu_group_get( 'events_slug' ) . '/' ) );

		default:
			return esc_html( nabu_group_get( $key ) );
	}
}

/**
 * NABU's membership form, the group's own when its number is set.
 *
 * The form takes the number as `werbername`, "VW" and seven digits, and
 * credits the new member to the group.
 *
 * @return string Unescaped URL.
 */
function nabu_group_membership_url() {
	$number = nabu_group_get( 'member_number' );

	if ( '' === $number ) {
		return 'https://www.nabu.de/spenden-und-mitmachen/mitglied-werden/';
	}

	return 'https://www.nabu.de/spenden-und-mitmachen/mitglied-werden/gruppen.html?werbername=VW' . str_pad( $number, 7, '0', STR_PAD_LEFT );
}

/**
 * The address /mitglied-werden/ leads to the membership form.
 *
 * Content links there instead of to NABU's form: the address stays valid
 * when the number changes, on a page as on a flyer or a QR code. A page with
 * that address takes precedence; only a request that would end in a 404 is
 * redirected, and before redirect_canonical(), which would guess some
 * similarly named post instead.
 */
function nabu_group_membership_redirect() {
	global $wp;

	if ( ! is_404() || 'mitglied-werden' !== $wp->request ) {
		return;
	}

	// Temporary: the number may change, and browsers keep permanent redirects.
	wp_redirect( nabu_group_membership_url(), 302, 'NABU Unofficial' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- nabu.de is the target.
	exit;
}
add_action( 'template_redirect', 'nabu_group_membership_redirect', 5 );

/**
 * The settings a lawful site cannot do without.
 *
 * `name` falls back to the site title and is never empty. Bank details are
 * optional: without them the donation account simply does not appear.
 *
 * @return string[] Labels of the missing fields.
 */
function nabu_group_missing_fields() {
	$fields  = nabu_group_fields();
	$missing = array();

	foreach ( array( 'street', 'postal_code', 'city', 'email', 'representative', 'state_association' ) as $key ) {
		if ( '' === trim( nabu_group_get( $key ) ) ) {
			$missing[] = $fields[ $key ]['label'];
		}
	}

	return $missing;
}

/**
 * Point out missing group data in the back end.
 *
 * The site renders without it — an empty field prints nothing rather than a
 * placeholder — but an imprint without an address does not satisfy § 5 DDG.
 */
function nabu_group_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'settings_page_nabu_group' === $screen->id ) {
		return;
	}

	$missing = nabu_group_missing_fields();
	if ( ! $missing ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		wp_kses(
			sprintf(
				/* translators: 1: list of missing fields, 2: URL of the settings page. */
				__( 'Diese Angaben der Gruppe fehlen noch: <strong>%1$s</strong>. Ohne sie bleiben Fußbereich, Kontaktseite und Impressum unvollständig – ein Impressum ohne Anschrift genügt § 5 DDG nicht. <a href="%2$s">Jetzt eintragen</a>.', 'nabu-unofficial-content' ),
				esc_html( implode( ', ', $missing ) ),
				esc_url( admin_url( 'options-general.php?page=nabu_group' ) )
			),
			array(
				'strong' => array(),
				'a'      => array( 'href' => array() ),
			)
		)
	);
}
add_action( 'admin_notices', 'nabu_group_admin_notice' );

/**
 * Block bindings source `nabu/group`.
 *
 *     <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"nabu/group","args":{"key":"address"}}}}} -->
 *     <p></p>
 *     <!-- /wp:paragraph -->
 *
 * In the editor a bound block shows the value and cannot be edited; the
 * value is changed under Settings → NABU Gruppe.
 */
function nabu_group_register_bindings() {
	register_block_bindings_source(
		'nabu/group',
		array(
			'label'              => __( 'NABU Gruppe', 'nabu-unofficial-content' ),
			'get_value_callback' => static function ( array $args ) {
				$key = isset( $args['key'] ) ? (string) $args['key'] : '';
				return '' === $key ? null : nabu_group_value( $key );
			},
		)
	);
}
add_action( 'init', 'nabu_group_register_bindings' );

/**
 * The editor side of the bindings source. WordPress hands only its name to
 * the editor; the script adds the values, as the site prints them.
 */
function nabu_group_enqueue_editor_values() {
	$values = array();
	foreach ( array_merge( array_keys( nabu_group_fields() ), nabu_group_composed_keys() ) as $key ) {
		$values[ $key ] = nabu_group_value( $key );
	}

	wp_enqueue_script(
		'nabu-group-bindings',
		plugins_url( 'assets/js/group-bindings.js', __DIR__ ),
		array( 'wp-blocks' ),
		(string) filemtime( dirname( __DIR__ ) . '/assets/js/group-bindings.js' ),
		true
	);
	wp_add_inline_script(
		'nabu-group-bindings',
		'var nabuGroupValues = ' . wp_json_encode( $values, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'nabu_group_enqueue_editor_values' );

/**
 * Navigation links can take their label from a binding as well as their
 * address: the service navigation names the state association that way.
 * A link whose bound label comes out empty is not rendered at all.
 *
 * @param string[] $attributes Bindable attributes.
 * @param string   $block_type Block name.
 * @return string[]
 */
function nabu_group_bindable_navigation_label( $attributes, $block_type ) {
	if ( 'core/navigation-link' === $block_type ) {
		$attributes[] = 'label';
	}
	return $attributes;
}
add_filter( 'block_bindings_supported_attributes', 'nabu_group_bindable_navigation_label', 10, 2 );

/**
 * Drop blocks that came out empty because the setting behind them is unset.
 *
 * Not every group has a phone number, and not every one is a registered
 * association. A paragraph bound to an empty setting would leave a blank
 * line with margins; a block with the class `nabu-optional` that holds
 * nothing but headings would leave a heading over nothing. Both disappear.
 *
 * @param string $block_content Rendered markup.
 * @param array  $block         Block data.
 * @return string
 */
function nabu_group_hide_empty_blocks( $block_content, $block ) {
	$source = $block['attrs']['metadata']['bindings']['content']['source'] ?? '';

	if ( 'nabu/group' === $source && '' === trim( wp_strip_all_tags( $block_content ) ) ) {
		return '';
	}

	$classes = $block['attrs']['className'] ?? '';

	if ( is_string( $classes ) && false !== strpos( $classes, 'nabu-optional' ) ) {
		$without_headings = (string) preg_replace( '#<h[1-6][^>]*>.*?</h[1-6]>#is', '', $block_content );

		if ( '' === trim( wp_strip_all_tags( $without_headings ) ) ) {
			return '';
		}
	}

	return $block_content;
}
add_filter( 'render_block', 'nabu_group_hide_empty_blocks', 20, 2 );

/**
 * Site-wide structured data: the group as an organisation.
 */
function nabu_group_json_ld() {
	$data = array_filter(
		array(
			'@context'  => 'https://schema.org',
			'@type'     => 'Organization',
			'name'      => nabu_group_get( 'name' ),
			'url'       => home_url( '/' ),
			'email'     => nabu_group_get( 'email' ) ?: null,
			'telephone' => nabu_group_get( 'phone' ) ?: null,
			'address'   => nabu_group_get( 'street' ) ? array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => nabu_group_get( 'street' ),
					'postalCode'      => nabu_group_get( 'postal_code' ) ?: null,
					'addressLocality' => nabu_group_get( 'city' ) ?: null,
					'addressCountry'  => 'DE',
				)
			) : null,
			'sameAs'    => array_values(
				array_filter(
					array(
						nabu_group_get( 'mastodon_url' ),
						nabu_group_get( 'instagram_url' ),
						nabu_group_get( 'facebook_url' ),
						nabu_group_get( 'youtube_url' ),
					)
				)
			) ?: null,
			'logo'      => has_custom_logo() ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' ) : null,
		)
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'nabu_group_json_ld' );
