import React, { useState } from 'react';
import CloneModuleModal from './CloneModuleModal';

const CloneModuleFrontend = ( { moduleId, nonce, uniqid } ) => {
  const [ isModalOpen, setIsModalOpen ] = useState( false );

  return (
    <div className="wp-block-openlab-modules-sharing">
      <button
        className="clone-module-button clone-module-button-reset"
        onClick={ () => setIsModalOpen( true ) }
      >
        Clone this Module
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
