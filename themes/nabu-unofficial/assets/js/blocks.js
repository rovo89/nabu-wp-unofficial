/**
 * Editor side of the theme's blocks.
 *
 * Title, icon, attributes and supports come from the registration in PHP,
 * which WordPress hands to the editor; this file adds the edit views: the
 * server rendering, plus settings in the sidebar where a block has any.
 * Written without a build step, so the theme needs no node_modules.
 */
( function ( blocks, element, blockEditor, components, ServerSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	/**
	 * Register a server-rendered block.
	 *
	 * @param {string}    name          Block name.
	 * @param {string}    [panelTitle]  Title of the settings panel.
	 * @param {Function}  [controls]    Returns the panel's controls for the given props.
	 */
	function register( name, panelTitle, controls ) {
		blocks.registerBlockType( name, {
			edit: function ( props ) {
				var panel = controls
					? el(
						blockEditor.InspectorControls,
						{},
						el.apply( null, [ components.PanelBody, { title: panelTitle } ].concat( controls( props ) ) )
					)
					: null;

				// A block without settings renders the same whatever its attributes.
				return el(
					'div',
					blockEditor.useBlockProps(),
					panel,
					el( ServerSideRender, { block: name, attributes: controls ? props.attributes : null } )
				);
			},
			save: function () {
				return null;
			},
		} );
	}

	/**
	 * onChange handler that stores one attribute.
	 *
	 * @param {Object}   props   Block props.
	 * @param {string}   key     Attribute name.
	 * @param {Function} [parse] Converts the control's value.
	 * @return {Function}
	 */
	function setter( props, key, parse ) {
		return function ( value ) {
			var attributes = {};
			attributes[ key ] = parse ? parse( value ) : value;
			props.setAttributes( attributes );
		};
	}

	function toInt( value ) {
		return parseInt( value, 10 );
	}

	function headingOptions( levels ) {
		return levels.map( function ( level ) {
			return { label: 'H' + level, value: String( level ) };
		} );
	}

	[
		'nabu/breadcrumb',
		'nabu/section-intro',
		'nabu/sidenav',
		'nabu/social-links',
		'nabu/footer-nav',
		'nabu/legal-links',
		'nabu/event-report',
	].forEach( function ( name ) {
		register( name );
	} );

	register( 'nabu/page-title', __( 'Seitentitel', 'nabu-unofficial' ), function ( props ) {
		return [
			el( components.SelectControl, {
				label: __( 'Überschriftenebene', 'nabu-unofficial' ),
				value: String( props.attributes.level ),
				options: headingOptions( [ 1, 2, 3 ] ),
				onChange: setter( props, 'level', toInt ),
			} ),
		];
	} );

	register( 'nabu/events', __( 'Terminliste', 'nabu-unofficial' ), function ( props ) {
		var a = props.attributes;

		return [
			el( components.RangeControl, {
				label: __( 'Anzahl', 'nabu-unofficial' ),
				value: a.count,
				min: 1,
				max: 20,
				onChange: setter( props, 'count' ),
			} ),
			el( components.SelectControl, {
				label: __( 'Zeitraum', 'nabu-unofficial' ),
				value: a.range,
				options: [
					{ label: __( 'Kommende Termine', 'nabu-unofficial' ), value: 'upcoming' },
					{ label: __( 'Vergangene Termine', 'nabu-unofficial' ), value: 'past' },
					{ label: __( 'Alle Termine', 'nabu-unofficial' ), value: 'all' },
				],
				onChange: setter( props, 'range' ),
			} ),
			el( components.SelectControl, {
				label: __( 'Überschriftenebene der Titel', 'nabu-unofficial' ),
				help: __( 'Muss zur Umgebung passen – nach einer H1 folgt H2, nicht H3.', 'nabu-unofficial' ),
				value: String( a.headingLevel ),
				options: headingOptions( [ 2, 3, 4 ] ),
				onChange: setter( props, 'headingLevel', toInt ),
			} ),
			el( components.SelectControl, {
				label: __( 'Darstellung', 'nabu-unofficial' ),
				help: __( 'Kacheln zeigen das Beitragsbild des Termins; ohne Bild steht das Datum im Kopf. Für die Seitenspalte und lange Listen ist die Liste besser.', 'nabu-unofficial' ),
				value: a.layout,
				options: [
					{ label: __( 'Liste', 'nabu-unofficial' ), value: 'list' },
					{ label: __( 'Kacheln', 'nabu-unofficial' ), value: 'cards' },
				],
				onChange: setter( props, 'layout' ),
			} ),
			el( components.ToggleControl, {
				label: __( 'Kurzbeschreibung anzeigen', 'nabu-unofficial' ),
				checked: a.showExcerpt,
				onChange: setter( props, 'showExcerpt' ),
			} ),
			el( components.TextControl, {
				label: __( 'Text, wenn keine Termine anstehen', 'nabu-unofficial' ),
				value: a.emptyText,
				onChange: setter( props, 'emptyText' ),
			} ),
		];
	} );

	register( 'nabu/event-details', __( 'Termindetails', 'nabu-unofficial' ), function ( props ) {
		return [
			el( components.SelectControl, {
				label: __( 'Darstellung', 'nabu-unofficial' ),
				value: props.attributes.layout,
				options: [
					{ label: __( 'Kasten mit allen Angaben', 'nabu-unofficial' ), value: 'box' },
					{ label: __( 'Datumszeile (über dem Titel)', 'nabu-unofficial' ), value: 'line' },
				],
				onChange: setter( props, 'layout' ),
			} ),
		];
	} );

	register( 'nabu/association-news', __( 'Verbandsmeldungen', 'nabu-unofficial' ), function ( props ) {
		var a = props.attributes;

		return [
			el( components.SelectControl, {
				label: __( 'Verband', 'nabu-unofficial' ),
				value: a.source,
				options: [
					{ label: __( 'Bundesverband', 'nabu-unofficial' ), value: 'federal' },
					{ label: __( 'Landesverband (aus den Gruppendaten)', 'nabu-unofficial' ), value: 'state' },
				],
				onChange: setter( props, 'source' ),
			} ),
			el( components.RangeControl, {
				label: __( 'Anzahl', 'nabu-unofficial' ),
				value: a.count,
				min: 1,
				max: 8,
				onChange: setter( props, 'count' ),
			} ),
			el( components.ToggleControl, {
				label: __( 'Nur Meldungen, keine Themenseiten', 'nabu-unofficial' ),
				checked: a.newsOnly,
				onChange: setter( props, 'newsOnly' ),
			} ),
		];
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
);
