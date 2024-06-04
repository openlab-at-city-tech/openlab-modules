import {
	PanelRow,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalDivider as Divider // eslint-disable-line
} from '@wordpress/components'

import { __ } from '@wordpress/i18n'
import { useDispatch, useSelect } from '@wordpress/data'
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'

export default function EditModule() {
	const { editPost } = useDispatch( 'core/editor' )

	const {
		isSharingEnabled,
		moduleAcknowledgements,
		moduleDescription,
		moduleNavTitle,
		postTitle,
		postType
	} = useSelect( ( select ) => {
		return {
			isSharingEnabled: select( 'core/editor' ).getEditedPostAttribute( 'enableSharing' ),
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

	const handleEnableSharingToggle = ( newIsSharingEnabled ) => {
		editPost( { enableSharing: newIsSharingEnabled } )

		if ( newIsSharingEnabled ) {
			// Is there already an openlab-modules/sharing block in the post content?
			const sharingBlock = wp.data.select( 'core/block-editor' ).getBlocks().find( block => block.name === 'openlab-modules/sharing' )

			if ( ! sharingBlock ) {
				// If not, add one. Ideally after openlab-modules/module-navigation, if it exists.
				const moduleNavigationBlock = wp.data.select( 'core/block-editor' ).getBlocks().find( block => block.name === 'openlab-modules/module-navigation' )
				const moduleNavigationClientId = moduleNavigationBlock ? moduleNavigationBlock.clientId : null

				const insertIndex = moduleNavigationClientId ? wp.data.select( 'core/block-editor' ).getBlockIndex( moduleNavigationClientId ) + 1 : null

				const newBlock = wp.blocks.createBlock( 'openlab-modules/sharing', {} )

				// Insert a test paragraph block after the module navigation block
				wp.data.dispatch( 'core/block-editor' ).insertBlocks( wp.blocks.createBlock( 'openlab-modules/sharing' ), insertIndex )

				// Return focus to the Module tab.
				setTimeout( () => {
					wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/document' )
				}, 100 )
			}
		}
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

			<Divider />

			<PanelRow>
				<ToggleControl
					label={ __( 'Share', 'openlab-modules' ) }
					help={ __( 'Enable shared cloning for this Module.', 'openlab-modules' ) }
					checked={ isSharingEnabled }
					onChange={ ( newIsSharingEnabled ) => handleEnableSharingToggle( newIsSharingEnabled ) }
				/>
			</PanelRow>
		</PluginDocumentSettingPanel>
	);
}
