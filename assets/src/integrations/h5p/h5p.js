(() => {
	window.addEventListener( 'DOMContentLoaded', () => {
		// Identify iframes with H5P content.
		// Those with src that contains h5p_embed
		const h5pIframes = document.querySelectorAll( 'iframe[src*="h5p_embed"]' );
		const h5pIframesArray = [...h5pIframes];

		h5pIframesArray.forEach( ( iframe ) => {
			// If the element doesn't have an ID, assign one.
			if ( ! iframe.id ) {
				iframe.id = `h5p-${Math.random().toString( 36 ).substring( 7 )}`;
			}

			window.moduleProblemCompletionBus.addOverlay( iframe.id );
		} )
	} )

	window.addEventListener( 'message', ( event ) => {
		const { data } = event;

		if ( ! data ) {
			return;
		}

		const { objectId, source, verb } = data;

		if ( 'h5p-postmessage' !== source ) {
			return;
		}

		switch ( verb ) {
			// H5P fires 'attempted' when the problem loads in the client.
			case 'attempted' :
				window.moduleProblemCompletionBus.addProblem( objectId );
				break;

			case 'completed' :
				window.moduleProblemCompletionBus.setProblemComplete( objectId );
				break;

		}
	} );
})();
