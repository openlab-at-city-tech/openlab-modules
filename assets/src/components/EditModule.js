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
	const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType() )

	if ( ! postType || 'openlab_module' !== postType ) {
		return null
	}

	const {
		moduleAcknowledgements,
		moduleDescription,
		postTitle
	} = useSelect( ( select ) => {
		return {
			moduleAcknowledgements: select( 'core/editor' ).getEditedPostAttribute( 'meta' ).module_acknowledgements,
			moduleDescription: select( 'core/editor' ).getEditedPostAttribute( 'meta' ).module_description,
			postTitle: select( 'core/editor' ).getEditedPostAttribute( 'title' )
		}
	}, [] )

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

			<Divider />

			<PanelRow>
				<TextareaControl
					label={ __( 'Acknowledgements', 'openlab-modules' ) }
					onChange={ ( newAcknowledgements ) => editPostMeta( { module_acknowledgements: newAcknowledgements } ) }
					value={ moduleAcknowledgements }
				/>
			</PanelRow>
		</PluginDocumentSettingPanel>
	);
}
