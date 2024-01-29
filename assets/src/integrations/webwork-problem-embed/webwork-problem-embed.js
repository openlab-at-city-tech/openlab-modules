/* global wwpe, openlabModulesWwpe, openlabModulesWwpeStrings */

import './webwork-problem-embed.scss';

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

		createSectionCompleteNotice();
	}

	/**
	 * Creates a notice to display when the section is complete.
	 *
	 * @return {void}
	 */
	const createSectionCompleteNotice = () => {
		const notice = document.createElement( 'div' );

		const dismissButton = document.createElement( 'button' );
		dismissButton.classList.add( 'wwpe-section-complete-notice-dismiss' );
		dismissButton.innerHTML = '&times; <span class="screen-reader-text">' + openlabModulesWwpeStrings.dismiss + '</span>';
		dismissButton.addEventListener( 'click', () => {
			overlay.remove();
		} )

		notice.classList.add( 'wwpe-section-complete-notice' );
		notice.innerHTML = '<p>' + openlabModulesWwpeStrings.sectionComplete + '</p>';
		notice.appendChild( dismissButton );

		const overlay = document.createElement( 'div' );
		overlay.classList.add( 'wwpe-section-complete-notice-overlay' );
		overlay.appendChild( notice );

		document.body.appendChild( overlay );
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

	// Add overlays to Renderer problems for non-authenticated users.
	window.addEventListener( 'load', () => {
		const { isUserLoggedIn } = openlabModulesWwpe;
		if ( isUserLoggedIn ) {
			return;
		}

		let overlayHTML = '<p>' + openlabModulesWwpeStrings.youAreNotLoggedIn + '</p>';
		overlayHTML += '<p>' + openlabModulesWwpeStrings.toReceiveCredit + '</p>';
		overlayHTML += '<p>' + '<button class="overlay-button" data-redirect-url="' + openlabModulesWwpe.loginUrl + '">' + openlabModulesWwpeStrings.logIn + '</button>' + '</p>';
		overlayHTML += '<p>' + '<a class="overlay-button-dismiss" href="#">' + openlabModulesWwpeStrings.continueWithout + '</a>' + '</p>';

		const rendererProblems = document.querySelectorAll( '.renderer-problem' );
		const rendererProblemsArray = [...rendererProblems];
		rendererProblemsArray.forEach( ( problem ) => {
			const overlay = document.createElement( 'div' );
			overlay.classList.add( 'renderer-problem-overlay' );
			overlay.innerHTML = overlayHTML;
			problem.closest( '.wwpe-problem-wrapper' ).appendChild( overlay );
		} )
	} )

	// Handle clicks on the Log In overlay button.
	window.addEventListener( 'click', ( event ) => {
		const { target } = event;
		if ( ! target.classList.contains( 'overlay-button' ) ) {
			return;
		}

		event.preventDefault();

		const { redirectUrl } = target.dataset;
		if ( redirectUrl ) {
			window.location.href = redirectUrl;
		}
	} )

	// Handle clicks on the Continue Without Logging In overlay button.
	window.addEventListener( 'click', ( event ) => {
		const { target } = event;
		if ( ! target.classList.contains( 'overlay-button-dismiss' ) ) {
			return;
		}

		event.preventDefault();

		// Remove all overlays.
		const overlays = document.querySelectorAll( '.renderer-problem-overlay' );
		const overlaysArray = [...overlays];
		overlaysArray.forEach( ( overlay ) => {
			overlay.remove();
		} )
	} )
})()
