/**
 * Set up store
 */
import './store'

/**
 * Blocks.
 */
import './blocks/module-attribution'
import './blocks/module-list'
import './blocks/module-navigation'
import './blocks/placeholder-text'
import './blocks/sharing'

/**
 * Components.
 */
import { registerPlugin } from '@wordpress/plugins';
import { select } from '@wordpress/data';

const isSinglePost = () => {
	const post = select( 'core/editor' ).getCurrentPost();
	return !! post;
}

// Add Edit Module controls.
import EditModule from './components/EditModule'
if ( isSinglePost() ) {
	registerPlugin(
		'openlab-module-edit-module-component',
		{
			icon: 'users',
			render: EditModule
		}
	)
}

// Add Module Pages controls.
import ModulePages from './components/ModulePages'
if ( isSinglePost() ) {
	registerPlugin(
		'openlab-module-module-pages-component',
		{
			icon: 'users',
			render: ModulePages
		}
	)
}

// Add Module controls to pages.
import PageModules from './components/PageModules'
if ( isSinglePost() ) {
	registerPlugin(
		'openlab-module-pages-modules-component',
		{
			icon: 'users',
			render: PageModules
		}
	)
}
