/**
 * Set up store
 */
import './store'

/**
 * Blocks.
 */
import './blocks/module-list'
import './blocks/module-navigation'
import './blocks/placeholder-text'
import './blocks/sharing'

/**
 * Components.
 */
import { registerPlugin } from '@wordpress/plugins';
import { useSelect } from '@wordpress/data';
import { Fragment } from '@wordpress/element';

import EditModule from './components/EditModule';
import ModulePages from './components/ModulePages';
import PageModules from './components/PageModules';

// Create a component that conditionally renders plugins based on the editor context
const OpenlabModulesRegisterPlugins = () => {
  const isSiteEditor = useSelect( ( select ) => {
    const editSite = select( 'core/edit-site' )
		return !! editSite;
  }, [] );

  return (
    <Fragment>
      { ! isSiteEditor && (
        <>
          <EditModule />
          <ModulePages />
          <PageModules />
        </>
      ) }
    </Fragment>
  );
};

// Register the component as a plugin
registerPlugin( 'openlab-modules', {
  render: OpenlabModulesRegisterPlugins,
  icon: 'users',
} );

// Ensure the editor is ready before registering the plugin
wp.domReady( () => {
  wp.data.dispatch( 'core/editor' ).editPost(); // Ensure editor is initialized
} );
