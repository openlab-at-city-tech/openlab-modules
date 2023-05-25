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
