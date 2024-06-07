(() => {
	document.addEventListener('DOMContentLoaded', () => {
		console.log('about to listen');
		window.addEventListener('message', function(event) {
			console.log( event.data );
				if (event.data && event.data.event === 'H5PCompletion') {
						console.log('H5P content completed on Site B!');
						console.log('Content ID:', event.data.contentId);
						console.log('Score:', event.data.score);
						// Perform additional actions here
				}
		}, false);
	});
})()
