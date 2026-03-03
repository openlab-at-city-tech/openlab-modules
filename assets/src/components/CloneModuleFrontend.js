import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import CloneModuleModal from './CloneModuleModal';

const CloneModuleFrontend = ( { moduleId, nonce, uniqid } ) => {
  const [ isModalOpen, setIsModalOpen ] = useState( false );

  return (
    <div className="wp-block-openlab-modules-sharing">
      <button
        className="clone-module-button clone-module-button-reset"
        onClick={ () => setIsModalOpen( true ) }
      >
        { __( 'Clone this Module', 'openlab-modules' ) }
      </button>

      <CloneModuleModal
        moduleId={ moduleId }
        nonce={ nonce }
        uniqid={ uniqid }
        isOpen={ isModalOpen }
        onClose={ () => setIsModalOpen( false ) }
      />
    </div>
  );
};

export default CloneModuleFrontend;
