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

// Add Edit Module controls.
import EditModule from './components/EditModule'
registerPlugin(
	'openlab-module-edit-module-component',
	{
		icon: 'users',
		render: EditModule
	}
)

// Add Module Pages controls.
import ModulePages from './components/ModulePages'
registerPlugin(
	'openlab-module-module-pages-component',
	{
		icon: 'users',
		render: ModulePages
	}
)

// Add Module controls to pages.
import PageModules from './components/PageModules'
registerPlugin(
	'openlab-module-pages-modules-component',
	{
		icon: 'users',
		render: PageModules
	}
)
