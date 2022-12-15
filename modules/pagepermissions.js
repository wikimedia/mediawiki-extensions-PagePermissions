( function ( mw, $, OO ) {
	'use strict';
	var error = mw.config.get( 'permissionsError' ) || {};
	var config = mw.config.get( 'permissionsConfig' ) || {},
		roles = config.roles || {},
		rights = config.rights || {},
		form = new OO.ui.FormLayout( {
			action: mw.Title.newFromText( mw.config.get( 'wgTitle' ), mw.config.get( 'wgNamespaceNumber' ) ).getUrl( { action: 'pagepermissions' } ),
			method: 'post'
		} ),
		i = 0,
		standardRoles = [ 'reader', 'editor', 'manager', 'owner' ],
		formComponents = [],
		submit = new OO.ui.ButtonInputWidget( { label: 'Submit', type: 'submit' } );

	for ( i = 0; i < roles.length; i++ ) {
		var type = roles[ i ];
		var currentRights = rights[ type ];
		var selected = [];

		for ( var j = 0; j < currentRights.length; j++ ) {
			selected.push( currentRights[ j ] );
		}

		var usersMultiselect = new mw.widgets.UsersMultiselectWidget( {
			name: type + '_permission',
			placeholder: mw.msg( 'pagepermissions-usernames-placeholder', type ),
			input: { autocomplete: false },
			selected: selected
		} );

		if ( standardRoles.indexOf( type ) !== -1 ) {
			// eslint-disable-next-line mediawiki/msg-doc
			type = mw.message( 'standardrole-' + type ).text();
		}

		formComponents.push( new OO.ui.FieldLayout( usersMultiselect, {
			tagName: 'fieldset',
			label: type
		} ) );
	}
	formComponents.push( submit );
	form.addItems( formComponents );

	function getDuplicates( arr ) {
		var sortedArray = arr.slice().sort();
		var results = [];
		for ( i = 0; i < sortedArray.length - 1; i++ ) {
			if ( sortedArray[ i + 1 ] === sortedArray[ i ] ) {
				results.push( sortedArray[ i ] );
			}
		}
		return results;
	}

	$( function () {
		/* eslint-disable no-jquery/no-global-selector, no-jquery/no-parse-html-literal,
		no-jquery/no-sizzle */
		$( '#mw-content-text' ).append( '<p class = "errorbox"></p>' );
		$( '#mw-content-text' ).append( form.$element );
		$( 'div.oo-ui-fieldLayout-body' ).css( 'display', 'block' );
		$( 'button[type="submit"]' ).before( '<br>' );
		if ( Object.keys( error ).length ) {
			form.disabled = true;
			$( 'button[type="submit"]' ).css( 'display', 'none' );
		}
		$( 'form' ).on( 'submit', function ( event ) {
			event.preventDefault();
			var formData = new FormData( event.target );
			var usernames = [];
			for ( i = 0; i < roles.length; i++ ) {
				var currentUsernames = formData.get( roles[ i ] + '_permission' ).split( '\n' );
				for ( var k = 0; k < currentUsernames.length; k++ ) {
					if ( currentUsernames[ k ] !== '' ) {
						usernames.push( currentUsernames[ k ] );
					}
				}
			}
			var duplicates = getDuplicates( usernames );
			if ( duplicates.length ) {
				$( '.errorbox:first' ).text( mw.msg( 'duplicate-usernames-error', duplicates.join( ', ' ) ) );
				return false;
			} else {
				$( this ).off( 'submit' ).trigger( 'submit' );
			}
		} );
	} );

}( mediaWiki, jQuery, OO ) );
