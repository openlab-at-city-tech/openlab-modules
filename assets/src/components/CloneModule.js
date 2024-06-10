import React, { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import he from 'he';

const CloneModule = ( props ) => {
  const { moduleId, nonce, uniqid } = props;

  const [ userSites, setUserSites ] = useState( [] );
  const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ selectedSite, setSelectedSite ] = useState( null );
	const [ cloneInProgress, setCloneInProgress ] = useState( false );
	const [ cloneResult, setCloneResult ] = useState( null );
	const [ moduleWithSameNameExistsOnTargetSite, setModuleWithSameNameExistsOnTargetSite ] = useState( false );

  const handleCloneButtonClick = () => {
    setIsModalOpen( true );
  };

  useEffect( () => {
    if ( isModalOpen && uniqid ) {
      fetchSites();
    }

		const handleKeydown = ( event ) => {
			if ( event.key === 'Escape' ) {
				closeModal();
			}
		}

		if ( isModalOpen ) {
			document.addEventListener( 'keydown', handleKeydown );
		}

		return () => {
			document.removeEventListener( 'keydown', handleKeydown );
		}
  }, [ isModalOpen, uniqid ] );

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
		const response = await apiFetch( { path: `/wp/v2/openlab-module/${moduleId}` } );

		const title = response.title.rendered;

		// Look for a matching module on the target site.
		const searchTerm = encodeURIComponent( title );
		const endpoint = targetSite.url + '/wp-json/wp/v2/openlab-module/?search=' + searchTerm;
		const response2 = await apiFetch( { url: endpoint } );

		if ( response2.length === 0 ) {
			return;
		}

		setModuleWithSameNameExistsOnTargetSite( true );
	};

	const closeModal = () => {
		setCloneResult( null );
		setSelectedSite( null );
		setIsModalOpen( false );
	};

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

  return (
    <>
      <div className="wp-block-openlab-modules-sharing">
        <button
          className="clone-module-button clone-module-button-reset"
          onClick={ handleCloneButtonClick }
        >
          { __( 'Clone this Module', 'openlab-modules' ) }
        </button>
      </div>

      { isModalOpen && (
        <div id={`clone-module-modal-${uniqid}`} className="clone-module-modal">
          <dialog className="clone-module-modal-content">
						<div className="dialog__header" aria-labelledby="dialog-title">
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
									{ __( 'Before you clone this module, make sure the OpenLab Modules plugin is activated on the site you are cloning the module to. You will also need to have an Administrator or Editor role on the site. The site will then appear in the dropdown below', 'openlab-modules' ) }
								</p>

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
									} }
								>
									<option value="">
										{
											userSites.length === 0 ? __( 'Loading...', 'openlab-modules' ) : __( '- Select a site -', 'openlab-modules' ) // eslint-disable-line
										}
									</option>

									{ userSites.map( ( site ) => (
										<option key={ site.id } value={ site.id }>
											{ he.decode( site.text ) }
										</option>
									)) }
								</select>

								{ moduleWithSameNameExistsOnTargetSite && (
									<p className="clone-module-error">
										{ __( 'Warning: A module with the same name already exists on the target site.', 'openlab-modules' ) }
									</p>
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
										disabled={ ! selectedSite || cloneInProgress}
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
													href={ cloneResult.clone_url }
												>{ __( 'Visit the cloned module', 'openlab-modules' ) }</a>
											</p>
										</>
									) : (
										<p>
											{ __( 'There was a problem cloning the module.', 'openlab-modules' ) }
										</p>
									) }

									<button
										className="clone-module-button-reset"
										onClick={ closeModal }
									>
										{ __( 'Close', 'openlab-modules' ) }
									</button>
								</div>
							) }
						</div>
          </dialog>
        </div>
      )}
    </>
  );
};

export default CloneModule;
