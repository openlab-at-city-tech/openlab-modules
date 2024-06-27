/* global openlabModules, openlabModulesStrings */

(() => {
	document.addEventListener( 'DOMContentLoaded', () => {
		window.moduleProblemCompletionBus = moduleProblemCompletionBus;

		// Handle clicks on the Log In overlay button.
		window.addEventListener( 'click', ( event ) => {
			const { target } = event

			if ( target.classList.contains( 'overlay-button-dismiss' ) ) {
				event.preventDefault();
				window.moduleProblemCompletionBus.dismissAllOverlays();
			} else if ( target.classList.contains( 'overlay-button' ) ) {
				event.preventDefault();
				window.moduleProblemCompletionBus.handleLogInOverlayClick( event );
			}
		})
	})
})()

const moduleProblemCompletionBus = {
	completeRequestSent: false,
	problems: [],

	/**
	 * Adds a problem to the completion bus.
	 *
	 * @param {string} problemId The ID of the problem.
	 * @return {void}
	 */
	addProblem( problemId ) {
		if ( ! this.problems.find( problem => problem.id === problemId ) ) {
			this.problems.push( { id: problemId, complete: false } );
		}
	},

	/**
	 * Marks a problem as 'complete'.
	 *
	 * @param {string} problemId The ID of the problem.
	 * @return {void}
	 */
	setProblemComplete( problemId ) {
		const theProblem = this.problems.find( problem => problem.id === problemId );
		if ( theProblem ) {
			theProblem.complete = true;
			this.checkAllProblemsComplete();
		}
	},

	/**
	 * Checks if all problems are complete.
	 *
	 * @return {void}
	 */
  checkAllProblemsComplete() {
		const allProblemsComplete = this.problems.every( problem => problem.complete );

		if ( allProblemsComplete ) {
			this.markSectionComplete();
		}
	},

	/**
	 * Marks the section as 'complete'.
	 *
	 * @return {void}
	 */
	markSectionComplete() {
		if ( this.completeRequestSent ) {
			return;
		}

		const { nonce, postId } = openlabModules;

		this.sendCompleteStatus( nonce, postId ).then( () => {
			this.completeRequestSent = true;
		})

		this.createSectionCompleteNotice();
	},

	/**
	 * Async callback for sending 'complete' status to the server.
	 *
	 * @param {string} nonce  The nonce for the request.
	 * @param {number} postId The post ID for the request.
	 * @return {Promise} The fetch promise.
	 */
	async sendCompleteStatus( nonce, postId ) {
		const { ajaxUrl } = openlabModules;
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
	},

	/**
	 * Creates a notice to display when the section is complete.
	 *
	 * @return {void}
	 */
	createSectionCompleteNotice() {
		const notice = document.createElement( 'div' );

		const dismissButton = document.createElement( 'button' );
		dismissButton.classList.add( 'module-section-complete-notice-dismiss' );
		dismissButton.innerHTML = '&times; <span class="screen-reader-text">' + openlabModulesStrings.dismiss + '</span>';
		dismissButton.addEventListener( 'click', () => {
			overlay.remove();
		} )

		notice.classList.add( 'module-section-complete-notice' );
		notice.innerHTML = '<p>' + openlabModulesStrings.sectionComplete + '</p>';
		notice.appendChild( dismissButton );

		const overlay = document.createElement( 'div' );
		overlay.classList.add( 'module-section-complete-notice-overlay' );
		overlay.appendChild( notice );

		document.body.appendChild( overlay );
	},

	/**
	 * Add not-logged-in overlay for a problem.
	 *
	 * @param {string} problemId The ID of
	 * @return {void}
	 */
	addOverlay( problemId ) {
		const { isUserLoggedIn } = openlabModules;
		if ( isUserLoggedIn ) {
			return;
		}

		let overlayHTML = '<p>' + openlabModulesStrings.youAreNotLoggedIn + '</p>';
		overlayHTML += '<p>' + openlabModulesStrings.toReceiveCredit + '</p>';
		overlayHTML += '<p>' + '<button class="overlay-button" data-redirect-url="' + openlabModules.loginUrl + '">' + openlabModulesStrings.logIn + '</button>' + '</p>';
		overlayHTML += '<p>' + '<a class="overlay-button-dismiss" href="#">' + openlabModulesStrings.continueWithout + '</a>' + '</p>';

		const problemElement = document.getElementById( problemId );

		// Create a .module-problem-wrapper wrapper if it doesn't exist.
		if ( ! problemElement.closest( '.module-problem-wrapper' ) ) {
			const wrapper = document.createElement( 'div' );
			wrapper.classList.add( 'module-problem-wrapper' );
			problemElement.parentNode.insertBefore( wrapper, problemElement );
			wrapper.appendChild( problemElement );
		}

		const overlay = document.createElement( 'div' );
		overlay.classList.add( 'module-problem-overlay' );
		overlay.innerHTML = overlayHTML;
		problemElement.closest( '.module-problem-wrapper' ).appendChild( overlay );
	},

	/**
	 * Handle clicks on the Log In overlay button.
	 *
	 * @param {Event} event The click event.
	 * @return {void}
	 */
	handleLogInOverlayClick( event ) {
		const { target } = event;

		const { redirectUrl } = target.dataset;
		if ( redirectUrl ) {
			window.location.href = redirectUrl;
		}
	},

	/**
	 * Handle clicks on the Continue Without Logging In overlay button.
	 *
	 * @return {void}
	 */
	dismissAllOverlays() {
		// Remove all overlays.
		const overlays = document.querySelectorAll( '.module-problem-overlay' );
		const overlaysArray = [...overlays];
		overlaysArray.forEach( ( overlay ) => {
			overlay.remove();
		} )
	}
}
