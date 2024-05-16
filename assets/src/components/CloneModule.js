import React, { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import he from 'he';

const CloneModule = ( props ) => {
  const { uniqid } = props;

  const [ userSites, setUserSites ] = useState( [] );
  const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ selectedSite, setSelectedSite ] = useState( null );

  const handleCloneButtonClick = () => {
    setIsModalOpen( true );
  };

  useEffect( () => {
    if ( isModalOpen && uniqid ) {
      fetchSites();
    }
  }, [ isModalOpen, uniqid ] );

  const fetchSites = async () => {
    let page = 1;

    try {
      let hasMore = true;
      let allSites = [];
      while ( hasMore ) {
        const response = await apiFetch( { path: `/cboxol/v1/sites?page=${page}` } );

        const { results, pagination } = response;
        const { more } = pagination;

        hasMore = more;
        page++;

        allSites = [ ...allSites, ...results ];
				setUserSites( allSites );
      }
    } catch ( error ) {
      console.error( 'Error fetching sites:', error );
    }
  };

  const closeModal = () => {
    setIsModalOpen( false );
  };

  return (
    <>
      <div className="wp-block-openlab-modules-sharing">
        <button
          className="clone-module-button clone-module-button-reset"
          onClick={ handleCloneButtonClick }
        >
          { __( 'Clone Module', 'openlab-modules' ) }
        </button>
      </div>

      { isModalOpen && (
        <div id={`clone-module-modal-${uniqid}`} className="clone-module-modal">
          <div className="clone-module-modal-content">
            <button
              className="close-clone-module-modal clone-module-button-reset"
              onClick={ closeModal }
            >
              <span className="screen-reader-text">
                { __( 'Cancel module cloning', 'openlab-modules' ) }
              </span>
            </button>

            <h2>{ __( 'Clone Module', 'openlab-modules' ) }</h2>

            <label htmlFor={ `clone-module-destination-select-${uniqid}` }>
              { __( 'Select a site to clone this module to:', 'openlab-modules' ) }
            </label>

            <select
              className="clone-module-destination-select"
              id={ `clone-module-destination-select-${uniqid}` }
							value={ selectedSite ? selectedSite : '' }
							onChange={ ( e ) => setSelectedSite( e.target.value ) }
            >
              <option value="">
                { userSites.length === 0 ? __( 'Loading&hellip;', 'openlab-modules' ) : __( 'Select a site', 'openlab-modules' ) }
              </option>

              { userSites.map( ( site ) => (
                <option key={ site.id } value={ site.id }>
                  { he.decode( site.text ) }
                </option>
              )) }
            </select>

						<div className="clone-module-actions">
							<button
								className="clone-module-button-cancel clone-module-button-reset"
								onClick={ closeModal }
							>
								{ __( 'Cancel', 'openlab-modules' ) }
							</button>

							<button
								className="clone-module-button-submit"
								disabled={ ! selectedSite }
							>
								{ __( 'Clone Module', 'openlab-modules' ) }
							</button>
						</div>
          </div>
        </div>
      )}
    </>
  );
};

export default CloneModule;
