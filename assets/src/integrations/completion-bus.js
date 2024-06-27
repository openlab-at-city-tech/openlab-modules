/* global openlabModules, openlabModulesStrings */

(() => {
	document.addEventListener( 'DOMContentLoaded', () => {
		window.moduleProblemCompletionBus = moduleProblemCompletionBus;
	})
})()

const moduleProblemCompletionBus = {
	completeRequestSent: false,
	problems: [],

	addProblem( problemId ) {
		if ( ! this.problems.find( problem => problem.id === problemId ) ) {
			this.problems.push( { id: problemId, complete: false } );
		}
	},

	setProblemComplete( problemId ) {
		const theProblem = this.problems.find( problem => problem.id === problemId );
		if ( theProblem ) {
			theProblem.complete = true;
			this.checkAllProblemsComplete();
		}
	},

  checkAllProblemsComplete() {
		const allProblemsComplete = this.problems.every( problem => problem.complete );

		if ( allProblemsComplete ) {
			this.markSectionComplete();
		}
	},

	markSectionComplete() {
		if ( this.completeRequestSent ) {
			return;
		}

		const { nonce, postId } = openlabModules;

		this.sendCompleteStatus( nonce, postId ).then( () => {
			this.completeRequestSent = true;
			console.log( 'Successfully marked section as complete.' );
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
	}
}
