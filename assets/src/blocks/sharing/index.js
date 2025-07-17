import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';

const sharingIcon = (
	<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
		<path d="M9 11.8L15.1 7.3C15.2 7.7 15.5 8 16 8H18C18.6 8 19 7.6 19 7V5C19 4.4 18.6 4 18 4H16C15.4 4 15 4.4 15 5V5.4L8.6 10.2C8.4 10.1 8.2 10 8 10H6C5.4 10 5 10.4 5 11V13C5 13.6 5.4 14 6 14H8C8.2 14 8.4 13.9 8.6 13.8L15 18.6V19C15 19.6 15.4 20 16 20H18C18.6 20 19 19.6 19 19V17C19 16.4 18.6 16 18 16H16C15.5 16 15.2 16.3 15.1 16.7L9 12.2V11.8Z" fill="#1E1E1E"/>
	</svg>
)

registerBlockType( metadata.name, {
	title: 'Module Sharing',
	icon: sharingIcon,
	edit: Edit,
	save: () => { return <div></div> }
} );
