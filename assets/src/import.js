/* global EventSource, jQuery, openlabModulesImport */
(function( $ ) {
	const { __ } = wp.i18n;

	const $uploadSubmitButton = $( '#upload-submit' );

	const evtSource = new EventSource( openlabModulesImport.url );

	const updateImportStatus = function(data) {
		const message = $('#import-status-message').find('strong');

		if ( ! data.error ) {
			message.text( __( 'Step 3: Import Complete. Check out your site!', 'openlab-modules' ) );
		} else {
			message.html( __( 'Import unsuccessful.', 'openlab-modules' ) );
		}
	};

	evtSource.onmessage = function ( message ) {
		const data = JSON.parse( message.data );
		switch ( data.action ) {
			case 'complete':
				evtSource.close();
				updateImportStatus(data);
				break;
		}
	};

	evtSource.addEventListener( 'log', function ( theMessage ) {
		const data = JSON.parse( theMessage.data );
		const row = document.createElement('tr');
		const level = document.createElement( 'td' );
		level.appendChild( document.createTextNode( data.level ) );
		row.appendChild( level );

		const messageDiv = document.createElement( 'td' );
		messageDiv.appendChild( document.createTextNode( data.message ) );
		row.appendChild( messageDiv );

		$( '#import-log' ).append( row );
	});

	// Validate zip input.
	$( '#importzip' ).on( 'change', function( el ) {
		const theFile = el.target.files[0];
		let error = '';

		const isZip = 'application/zip' === theFile.type || 'application/x-zip-compressed' === theFile.type;

		// File type.
		if ( ! isZip ) {
			error = __( 'Please select an OpenLab Modules Export file (.zip).', 'openlab-modules' );
		}

		if ( ! error ) {
			const maxUploadSize = parseInt( openlabModulesImport.maxUploadSize );
			if ( theFile.size > maxUploadSize ) {
				error = openlabModulesImport.strings.errorSize;
			}
		}

		if ( error ) {
			$( '#ol-import-error' ).html( error );
			$uploadSubmitButton.prop( 'disabled', true );
		} else {
			$( '#ol-import-error' ).html( '' );
			$uploadSubmitButton.prop( 'disabled', false );
		}
	} );

	$uploadSubmitButton.on( 'click', () => {
		$uploadSubmitButton.val( __( "Uploading…", 'openlab-modules' ) ).attr( 'disabled', true );
		$uploadSubmitButton.closest( 'form' ).submit();
	} );
})(jQuery);
