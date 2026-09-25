/**
 * Editor side of the bindings source nabu/group.
 *
 * Bound blocks show the group's values, handed over by the plugin as
 * nabuGroupValues, instead of the source's name. They stay read-only: the
 * values are changed under Settings → NABU Gruppe.
 */
( function ( blocks, values ) {
	'use strict';

	blocks.registerBlockBindingsSource( {
		name: 'nabu/group',
		getValues: function ( options ) {
			var result = {};

			Object.keys( options.bindings ).forEach( function ( attribute ) {
				var args = options.bindings[ attribute ].args || {};
				result[ attribute ] = values[ args.key ];
			} );

			return result;
		},
	} );
} )( window.wp.blocks, window.nabuGroupValues );
