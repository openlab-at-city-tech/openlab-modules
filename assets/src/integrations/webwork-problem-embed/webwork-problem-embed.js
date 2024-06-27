/* global wwpe, openlabModules, openlabModulesStrings */

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
			const rendererProblemsArray = [...rendererProblems];
			rendererProblemsArray.forEach( ( problem ) => {
				window.moduleProblemCompletionBus.addProblem( problem.id );
				window.moduleProblemCompletionBus.addOverlay( problem.id );
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

	window.addEventListener( 'DOMContentLoaded', () => {
		setTimeout( identifyRendererProblems, 3000 );
	} )
})()
