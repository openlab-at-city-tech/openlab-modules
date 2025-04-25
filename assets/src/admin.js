/* global openlabModulesAdmin */
import React from 'react';
import { createRoot } from 'react-dom/client';
import CloneModuleModal from './components/CloneModuleModal';
import './components/clone-module.scss';

document.addEventListener( 'DOMContentLoaded', () => {
  document.querySelectorAll( '.row-actions' ).forEach( ( row ) => {
    const moduleRow = row.closest( '.type-openlab_module' );
    const enableSharingEl = moduleRow?.querySelector( '.enable-sharing' );
    const enableSharing = enableSharingEl && '1' === enableSharingEl.value;

    if ( ! enableSharing ) {
      return;
    }

    const moduleId = moduleRow.getAttribute( 'id' ).replace( 'post-', '' );
    const uniqid = `clone-module-${moduleId}`;
    const nonce = openlabModulesAdmin.nonce; // Assume localized.

    // Insert clone <a> link.
    const cloneLink = document.createElement( 'a' );
    cloneLink.href = '#';
    cloneLink.textContent = openlabModulesAdmin.clone; // "Clone"
    cloneLink.className = 'clone-module-admin-trigger';

		// Before appending, put a pipe separator in the last action's span.
		const lastAction = row.lastChild;
		if ( lastAction && lastAction.nodeName === 'SPAN' ) {
			const pipe = document.createElement( 'span' );
			pipe.innerHTML = ' | ';
			lastAction.appendChild( pipe );
		}

    const actions = row;
    const span = document.createElement( 'span' );
    span.appendChild( cloneLink );
    actions.appendChild( span );

    // Create a mount point for React Modal
    const modalContainer = document.createElement( 'div' );
    modalContainer.id = `clone-module-container-${moduleId}`;
    document.body.appendChild( modalContainer );

    const root = createRoot( modalContainer );

    let modalOpen = false;

    cloneLink.addEventListener( 'click', ( e ) => {
      e.preventDefault();
      if ( modalOpen ) {
        return;
      }
      modalOpen = true;

      root.render(
        <CloneModuleModal
          moduleId={ moduleId }
          nonce={ nonce }
          uniqid={ uniqid }
          isOpen={ true }
          onClose={ () => {
            modalOpen = false;
            root.render( null ); // Unmount
          } }
        />
      );
    } );
  } );
});
