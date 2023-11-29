/* global wwpe, openlabModulesWwpe */
(() => {
	const problemFrames = {};
	let problemFramesInitialized = false;
	let sectionCompleteRequestSent = false;

	if ( 'undefined' === typeof wwpe ) {
		return;
	}

	const safeJSONParse = (str) => {
		try {
			return JSON.parse( str );
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Marks a single problem as 'complete'.
	 *
	 * @param {string} frame The frame ID of the problem.
	 * @return {void}
	 */
	const markProblemComplete = ( frame ) => {
		const iframe = document.getElementById( frame );
		if ( ! iframe ) {
			return;
		}

		// We late-evaluate the list of problemFrames because iframe-resizer takes a while to load.
		if ( ! problemFramesInitialized ) {
			const rendererProblems = document.querySelectorAll( '.renderer-problem' );
			const rendererProblemsArray = [...rendererProblems];
			rendererProblemsArray.forEach( ( problem ) => {
				problemFrames[ problem.id ] = false;
			} )

			problemFramesInitialized = true;
		}

		if ( problemFrames.length === 0 ) {
			return;
		}

		if ( ! problemFrames.hasOwnProperty( frame ) ) {
			return;
		}

		problemFrames[ frame ] = true;
	}

	/**
	 * Checks whether all problems on the current page are complete.
	 *
	 * @return {boolean} Whether all problems are complete.
	 */
	const allProblemsComplete = () => {
		if ( problemFrames.length === 0 ) {
			return false;
		}

		const problemFramesArray = Object.values( problemFrames );
		return problemFramesArray.every( ( problem ) => problem );
	}

	/**
	 * Marks the current section as 'complete'.
	 *
	 * @return {void}
	 */
	const markSectionComplete = () => {
		if ( sectionCompleteRequestSent ) {
			return;
		}

		const { nonce, postId } = openlabModulesWwpe;

		sendCompleteStatus( nonce, postId ).then( () => {
			sectionCompleteRequestSent = true;
			console.log( 'Successfully marked section as complete.' ); // eslint-disable-line no-console
		} )
	}

	/**
	 * Async callback for sending 'complete' status to the server.
	 *
	 * @param {string} nonce  The nonce for the request.
	 * @param {number} postId The post ID for the request.
	 * @return {Promise} The fetch promise.
	 */
	async function sendCompleteStatus( nonce, postId ) {
		const { ajaxUrl } = openlabModulesWwpe;
		const endpointUrl = `${ajaxUrl}?action=mark_module_section_complete`;

		const body = new URLSearchParams();
		body.append( 'nonce', nonce );
		body.append( 'postId', postId );

		const response = await fetch( endpointUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: body.toString(),
		} );

		return response.json();
	}

	/**
	 * Listen for postMessage from the webwork-problem-embed iframe.
	 *
	 * @param {Event} event The postMessage event.
	 * @return {void}
	 */
	window.addEventListener( 'message', ( event ) => {
		// webwork-problem-embed proxies through the WP server.
		if ( event.origin !== window.location.origin ) {
			return;
		}

		const { data } = event;
		if ( 'undefined' === typeof data ) {
			return;
		}

		const dataObj = safeJSONParse( data );
		if ( ! dataObj ) {
			return;
		}

		const { frame, type } = dataObj;
		if ( 'undefined' === typeof type ) {
			return;
		}

		if ( 'webwork.interaction.attempt' === type ) {
			const tryStatus = parseFloat( dataObj.status );

			if ( 1 === tryStatus ) {
				markProblemComplete( frame );

				if ( allProblemsComplete() ) {
					markSectionComplete();
				}
			}
		}
	} )
})()
