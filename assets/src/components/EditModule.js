import {
	PanelRow,
	TextControl,
	TextareaControl,
	__experimentalDivider as Divider // eslint-disable-line
} from '@wordpress/components'

import { __ } from '@wordpress/i18n'
import { useDispatch, useSelect } from '@wordpress/data'
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'

export default function EditModule() {
	const { editPost } = useDispatch( 'core/editor' )

	const {
		moduleAcknowledgements,
		moduleDescription,
		moduleNavTitle,
		postTitle,
		postType
	} = useSelect( ( select ) => {
		return {
			moduleAcknowledgements: select( 'core/editor' ).getEditedPostAttribute( 'meta' ).module_acknowledgements,
			moduleDescription: select( 'core/editor' ).getEditedPostAttribute( 'meta' ).module_description,
			moduleNavTitle: select( 'core/editor' ).getEditedPostAttribute( 'moduleNavTitle' ),
			postTitle: select( 'core/editor' ).getEditedPostAttribute( 'title' ),
			postType: select( 'core/editor' ).getCurrentPostType()
		}
	}, [] )

	if ( ! postType || 'openlab_module' !== postType ) {
		return null
	}

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
				<TextControl
					label={ __( 'Navigation Title', 'openlab-modules' ) }
					help={ __( 'The title of the module home page, for use in the Module Navigation block', 'openlab-modules' ) }
					onChange={ ( newNavTitle ) => editPost( { moduleNavTitle: newNavTitle } ) }
					value={ moduleNavTitle }
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
