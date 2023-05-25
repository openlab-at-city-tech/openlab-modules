import {
	Button,
	PanelRow,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalDivider as Divider
} from '@wordpress/components'

import { __ } from '@wordpress/i18n'
import { useDispatch, useSelect } from '@wordpress/data'
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'

import { select } from '@wordpress/data'

export default function EditModule( {
	isSelected
} ) {

	const {
		moduleDescription,
		postType,
		postTitle
	} = useSelect( ( select ) => {
		const metas = select( 'core/editor' ).getEditedPostAttribute( 'meta' )

		return {
			moduleDescription: metas ? select( 'core/editor' ).getEditedPostAttribute( 'meta' ).module_description : '',
			postType: select( 'core/editor' ).getCurrentPostType(),
			postTitle: select( 'core/editor' ).getEditedPostAttribute( 'title' )
		}
	}, [] )

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
					onChange={ ( newTitle ) => editPost( { title: newTitle } ) }
					value={ postTitle }
				/>
			</PanelRow>

			<Divider />

			<PanelRow>
				<TextareaControl
					label={ __( 'Description', 'openlab-modules' ) }
					onChange={ ( newDescription ) => editPostMeta( { module_description: newDescription } ) }
					value={ moduleDescription }
				/>
			</PanelRow>
		</PluginDocumentSettingPanel>
	);
}
