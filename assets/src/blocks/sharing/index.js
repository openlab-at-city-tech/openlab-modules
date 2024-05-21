import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	title: 'Module Sharing',
	icon: 'editor-ul',
	edit: Edit,
	save: () => { return <div></div> }
} );
