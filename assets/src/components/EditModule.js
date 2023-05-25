import {
	Button,
	PanelRow,
	TextControl,
	ToggleControl
} from '@wordpress/components'

import { __ } from '@wordpress/i18n'
import { useDispatch, useSelect } from '@wordpress/data'
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'

import { select } from '@wordpress/data'

export default function EditModule( {
	isSelected
} ) {

	const { postType, postTitle } = useSelect( ( select ) => ( {
		postType: select( 'core/editor' ).getCurrentPostType(),
		postTitle: select( 'core/editor' ).getEditedPostAttribute( 'title' )
	} ), [] )

	if ( 'openlab_module' !== postType ) {
		return null;
	}

	const { editPost } = useDispatch( 'core/editor' )

	const editPostMeta = ( metaToUpdate ) => {
		editPost( { meta: metaToUpdate } )
	}

	return (
		<PluginDocumentSettingPanel
			name="openlab-modules-edit-module"
			title={ __( 'Edit Module', 'openlab-modules' ) }
			>

			<PanelRow>
				<TextControl
					label={ __( 'Name', 'openlab-modules' ) }
					onChange={ ( postTitle ) => editPost( { title: postTitle } ) }
					value={ postTitle }
				/>
			</PanelRow>
		</PluginDocumentSettingPanel>
	);
}
