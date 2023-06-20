import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	title: 'Module List',
	icon: 'editor-ul',
	edit: Edit
} );
