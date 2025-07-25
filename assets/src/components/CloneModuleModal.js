import React, { useState, useEffect, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import he from 'he';

const CloneModuleModal = ( { moduleId, nonce, uniqid, isOpen, onClose } ) => {
  const [ userSites, setUserSites ] = useState( [] );
  const [ hasFetchedSites, setHasFetchedSites ] = useState( false );
  const [ selectedSite, setSelectedSite ] = useState( null );
  const [ cloneInProgress, setCloneInProgress ] = useState( false );
  const [ cloneResult, setCloneResult ] = useState( null );
  const [ moduleWithSameNameExistsOnTargetSite, setModuleWithSameNameExistsOnTargetSite ] = useState( false );
  const [ requiredPluginsMissing, setRequiredPluginsMissing ] = useState( [] );

  useEffect( () => {
    if ( isOpen && uniqid ) {
      fetchSites();
    }
  }, [ isOpen, uniqid ] );

	useEffect( () => {
		if ( isOpen ) {
			const firstFocusable = document.querySelector( '.clone-module-modal-content select' );
			if ( firstFocusable ) {
				firstFocusable.focus();
			}
		}
	}, [ isOpen ] );

	useEffect( () => {
		if ( ! isOpen ) {
			return;
		}

		const handleKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				closeModal();
			}
		};

		window.addEventListener( 'keydown', handleKeyDown );

		return () => {
			window.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [ isOpen ] );

	const closeModal = useCallback( () => {
		setCloneResult( null );
		setSelectedSite( null );
		onClose();
		setModuleWithSameNameExistsOnTargetSite( false );
		setRequiredPluginsMissing( [] );
	}, [ onClose ] );

  const fetchSites = async () => {
    let page = 1;

    try {
      let hasMore = true;
      let allSites = [];
      while ( hasMore ) {
        const response = await apiFetch( { path: `/openlab-modules/v1/my-sites?page=${page}` } );

        const { results, pagination } = response;
        const { more } = pagination;

        hasMore = more;
        page++;

        allSites = [ ...allSites, ...results ];
				setUserSites( allSites );
				setHasFetchedSites( true );
      }
    } catch ( error ) {}
  };

	const checkForExistingModuleBySameName = async ( siteId ) => {
		setModuleWithSameNameExistsOnTargetSite( false );

		// Get out of userSites
		const targetSite = userSites.find( ( site ) => site.id === siteId );

		if ( ! targetSite ) {
			return;
		}

		// Get the current module name.
		const response = await apiFetch( { path: `/wp/v2/openlab_module/${moduleId}` } );

		const title = response.title.rendered;

		// Look for a matching module on the target site.
		const searchTerm = encodeURIComponent( title );
		const endpoint = targetSite.url + '/wp-json/wp/v2/openlab_module/?search=' + searchTerm;
		const response2 = await apiFetch( { url: endpoint } );

		if ( response2.length === 0 ) {
			return;
		}

		// Only match those with a title that exactly matches.
		const matchingModules = response2.filter( ( module ) => {
			return module.title.rendered === title;
		} );

		if ( matchingModules.length === 0 ) {
			return;
		}

		setModuleWithSameNameExistsOnTargetSite( true );
	};

	const checkForRequiredPlugins = async ( siteId ) => {
		setRequiredPluginsMissing( [] );

		// Get out of userSites
		const targetSite = userSites.find( ( site ) => site.id === siteId );

		if ( ! targetSite ) {
			return;
		}

		const response = await apiFetch( { path: `/openlab-modules/v1/check-module-requirements/${moduleId}?destinationSiteId=${targetSite.id}` } );

		if ( response.success ) {
			return;
		}

		setRequiredPluginsMissing( response.requirements.plugins );
	}

	const handleContinueClick = async () => {
		setCloneInProgress( true );

		try {
			const response = await apiFetch(
				{
					path: `/openlab-modules/v1/clone-module/${moduleId}`,
					method: 'POST',
					data: {
						nonce,
						destinationSiteId: selectedSite,
					},
				}
			);

			setCloneInProgress( false );
			setCloneResult( response );
		} catch ( error ) {}
	}

  if ( ! isOpen ) {
    return null;
  }

  return (
    <div id={`clone-module-modal-${uniqid}`} className="clone-module-modal" role="presentation" aria-hidden="false">
			<div className="clone-module-modal-backdrop" aria-hidden="true"></div>
      <div className="clone-module-modal-content" role="dialog" aria-modal="true">
					<div className="dialog__header">
						<h1 id="dialog-title">{ __( 'Clone this Module', 'openlab-modules' ) }</h1>

						<button
							className="close-clone-module-modal clone-module-button-reset"
							onClick={ closeModal }
							aria-label={ __( 'Cancel module cloning', 'openlab-modules' ) }
						>×</button>
					</div>

					<div className="dialog__body">
						{ ! cloneResult && ( <div className="clone-module-form">
							<p>
								{ __( 'Before you clone this module, make sure the OpenLab Modules plugin is activated on the site you are cloning the module to. You will also need to have an Administrator or Editor role on the site. The site will then appear in the dropdown below.', 'openlab-modules' ) }
							</p>

							{ userSites.length === 0 && (
								<p><strong>{ __( 'There are currently no sites available that fit these requirements.', 'openlab-modules' ) }</strong></p>
							) }

							{ userSites.length > 0 && (
								<>
									<label htmlFor={ `clone-module-destination-select-${uniqid}` }>
										{ __( 'Select a site to clone this module to:', 'openlab-modules' ) }
									</label>

									<select
										className="clone-module-destination-select"
										id={ `clone-module-destination-select-${uniqid}` }
										value={ selectedSite ? selectedSite : '' }
										onChange={ ( e ) => {
											setSelectedSite( e.target.value )
											checkForExistingModuleBySameName( e.target.value )
											checkForRequiredPlugins( e.target.value )
										} }
									>
										<option value="">
											{ __( '- Select a site -', 'openlab-modules' ) }
										</option>

										{ userSites.map( ( site ) => (
											<option key={ site.id } value={ site.id }>
												{ site.isCurrentSite
													? sprintf( __( 'This site: %s' ), he.decode( site.text ) )
													: he.decode( site.text )
												}
											</option>
										)) }
									</select>
								</>
							) }

							{ moduleWithSameNameExistsOnTargetSite && (
								<p className="clone-module-error">
									{ __( 'Warning: A module with the same name already exists on the target site.', 'openlab-modules' ) }
								</p>
							) }

							{ requiredPluginsMissing.length > 0 && (
								<div className="clone-module-notice">
									<p>
										{ __( 'This module requires a number of plugins that are not currently active on the target site. Make sure to activate these plugins to maintain full functionality in the cloned module.', 'openlab-modules' ) }
									</p>

									<ul>
										{ requiredPluginsMissing.map( ( plugin ) => (
											<li key={ plugin }>
												{ plugin }
											</li>
										) ) }
									</ul>
								</div>
							) }

							<div className="clone-module-actions">
								<button
									className="clone-module-button-cancel clone-module-button-reset"
									onClick={ closeModal }
								>
									{ __( 'Cancel', 'openlab-modules' ) }
								</button>

								<button
									className="clone-module-button-submit"
									disabled={ ! selectedSite || cloneInProgress || userSites.length === 0 }
									onClick={ handleContinueClick }
								>
									{ /* eslint-disable-next-line */ }
									{ cloneInProgress ? __( 'Cloning...', 'openlab-modules' ) : __( 'Continue', 'openlab-modules' ) }
								</button>
							</div>
						</div> ) }

						{ cloneResult && (
							<div className="clone-module-result">
								{ cloneResult.success ? (
									<>
										<p>
											{ __( 'The module was successfully cloned.', 'openlab-modules' ) }
										</p>

										<p>
											<a
												href={ cloneResult.clone_edit_url }
											>{ __( 'Edit the cloned module', 'openlab-modules' ) }</a>
										</p>
									</>
								) : (
									<p>
										{ __( 'There was a problem cloning the module.', 'openlab-modules' ) }
									</p>
								) }

								<button
									className="clone-module-button-submit"
									onClick={ closeModal }
								>
									{ __( 'Close', 'openlab-modules' ) }
								</button>
							</div>
						) }
					</div>
      </div>
    </div>
  );
};

export default CloneModuleModal;
