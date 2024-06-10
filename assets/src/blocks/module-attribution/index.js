import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	title: 'Module Attribution',
	icon: 'editor-ul',
	edit: Edit,
	save: () => null,
} );
