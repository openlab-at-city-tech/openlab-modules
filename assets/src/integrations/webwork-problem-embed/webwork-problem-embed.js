/* global wwpe, openlabModulesWwpe, openlabModulesWwpeStrings */

import './webwork-problem-embed.scss';

(() => {
	let problemFramesInitialized = false;

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

		window.moduleProblemCompletionBus.setProblemComplete( frame );
	}

	/**
	 * Identifies all Renderer problems and adds them to the moduleProblemCompletionBus.
	 *
	 * We run this on a timeout, to give iFrameResizer a chance to finish.
	 */
	const identifyRendererProblems = () => {
		if ( ! problemFramesInitialized ) {
			const rendererProblems = document.querySelectorAll( '.renderer-problem' );
			console.log( 'rendererProblems', rendererProblems );
			const rendererProblemsArray = [...rendererProblems];
			rendererProblemsArray.forEach( ( problem ) => {
				window.moduleProblemCompletionBus.addProblem( problem.id );
			} )

			problemFramesInitialized = true;
		}
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
			}
		}
	} )

	// Add overlays to Renderer problems for non-authenticated users.
	window.addEventListener( 'load', () => {
		// Run this on a timeout, to give iFrameResizer a chance to finish.
		setTimeout( () => {
			identifyRendererProblems();
		}, 3000 );

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
