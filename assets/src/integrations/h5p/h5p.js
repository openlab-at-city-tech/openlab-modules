(() => {
	window.addEventListener( 'message', ( event ) => {
		const { data } = event;

		if ( ! data ) {
			return;
		}

		const { objectId, source, verb } = data;

		if ( 'h5p-postmessage' !== source ) {
			return;
		}

		console.log( 'h5p integration', verb, objectId, data );

		switch ( verb ) {
			case 'attempted' :
				window.moduleProblemCompletionBus.addProblem( objectId );
				break;

			case 'completed' :
				window.moduleProblemCompletionBus.setProblemComplete( objectId );
				break;

		}
	} );
})();
