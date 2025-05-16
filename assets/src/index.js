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

import './components/clone-module.scss';

/**
 * Components.
 */
import { registerPlugin } from '@wordpress/plugins';
import { useSelect } from '@wordpress/data';
import { Fragment } from '@wordpress/element';

import EditModule from './components/EditModule';
import ModulePages from './components/ModulePages';
import PageModules from './components/PageModules';
import CompletionMessagesModule from './components/CompletionMessagesModule';
import CompletionMessagesPage from './components/CompletionMessagesPage';

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
				<CompletionMessagesModule />
				<CompletionMessagesPage />
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
