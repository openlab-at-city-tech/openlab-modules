/**
 * Set up store
 */
import './store'

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
