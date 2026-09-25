( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	function withSettings( PostFeaturedImage ) {
		return function ( props ) {
			var meta = wp.data.useSelect( function ( select ) {
				return select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			}, [] );
			var editPost = wp.data.useDispatch( 'core/editor' ).editPost;
			var image = el( PostFeaturedImage, props );

			if ( ! meta || ! ( '_nabu_image_focus' in meta ) || ! props.featuredImageId || ! props.media || ! props.media.source_url ) {
				return image;
			}

			var focus = meta._nabu_image_focus;
			var value = focus && focus.id === props.featuredImageId ? { x: focus.x, y: focus.y } : { x: 0.5, y: 0.5 };

			return el(
				wp.element.Fragment,
				null,
				image,
				el( wp.components.CheckboxControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Über dem Text zeigen', 'nabu-unofficial-content' ),
					help: __( 'In den Kacheln erscheint das Bild immer. Abschalten, wenn es auch im Text steht, etwa in einer Galerie.', 'nabu-unofficial-content' ),
					checked: ! meta._nabu_image_hidden,
					onChange: function ( checked ) {
						editPost( { meta: { _nabu_image_hidden: ! checked } } );
					},
				} ),
				el( wp.components.FocalPointPicker, {
					__nextHasNoMarginBottom: true,
					label: __( 'Bildausschnitt', 'nabu-unofficial-content' ),
					help: __( 'Wo das Bild beschnitten wird, in den Kacheln und über dem Text, bleibt dieser Punkt zu sehen.', 'nabu-unofficial-content' ),
					url: props.media.source_url,
					value: value,
					onChange: function ( point ) {
						editPost( {
							meta: {
								_nabu_image_focus: {
									id: props.featuredImageId,
									x: Math.round( point.x * 100 ) / 100,
									y: Math.round( point.y * 100 ) / 100,
								},
							},
						} );
					},
				} )
			);
		};
	}

	wp.hooks.addFilter( 'editor.PostFeaturedImage', 'nabu/featured-image', withSettings );
} )( window.wp );
