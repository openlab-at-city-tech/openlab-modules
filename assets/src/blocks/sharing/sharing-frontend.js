import React from 'react';
import { createRoot } from 'react-dom/client';
import CloneModule from '../../components/CloneModule';

const App = ( { moduleId, nonce, uniqid } ) => {
    return (
        <div>
            <CloneModule
							moduleId={ moduleId }
							nonce={ nonce }
							uniqid={ uniqid }
						/>
        </div>
    );
};

const containers = document.querySelectorAll( '.clone-module-container' );

containers.forEach( ( container ) => {
	const root = createRoot( container )

	// Pass the dataset.uniqid as a prop to the CloneModule component
	root.render(
		<App
			moduleId={ container.dataset.moduleId }
			nonce={ container.dataset.nonce }
			uniqid={ container.dataset.uniqid }
		/>
	);
} )
