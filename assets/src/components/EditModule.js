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
import { useCallback, useEffect } from '@wordpress/element'

export default function EditModule() {
	const { editPost } = useDispatch( 'core/editor' )

	const {
		isSharingEnabled,
		moduleAcknowledgements,
		moduleDescription,
		moduleNavTitle,
		postId,
		postStatus,
		postTitle,
		postType
	} = useSelect( ( select ) => {
		return {
			isSharingEnabled: select( 'core/editor' ).getEditedPostAttribute( 'enableSharing' ),
			moduleAcknowledgements: select( 'core/editor' ).getEditedPostAttribute( 'meta' ).module_acknowledgements,
			moduleDescription: select( 'core/editor' ).getEditedPostAttribute( 'meta' ).module_description,
			moduleNavTitle: select( 'core/editor' ).getEditedPostAttribute( 'moduleNavTitle' ),
			postId: select( 'core/editor' ).getCurrentPostId(),
			postStatus: select( 'core/editor' ).getEditedPostAttribute( 'status' ),
			postTitle: select( 'core/editor' ).getEditedPostAttribute( 'title' ),
			postType: select( 'core/editor' ).getCurrentPostType()
		}
	}, [] )

	const handleEnableSharingToggle = useCallback( ( newIsSharingEnabled ) => {
		editPost( { enableSharing: newIsSharingEnabled } )

		if ( newIsSharingEnabled ) {
			// Is there already an openlab-modules/sharing block in the post content?
			const sharingBlock = wp.data.select( 'core/block-editor' ).getBlocks().find( block => block.name === 'openlab-modules/sharing' )

			if ( ! sharingBlock ) {
				// If not, add one. Ideally before openlab-modules/module-navigation, if it exists.
				const moduleNavigationBlock = wp.data.select( 'core/block-editor' ).getBlocks().find( block => block.name === 'openlab-modules/module-navigation' )
				const moduleNavigationClientId = moduleNavigationBlock ? moduleNavigationBlock.clientId : null

				const moduleNavigationBlockIndex = wp.data.select( 'core/block-editor' ).getBlockIndex( moduleNavigationClientId )
				const insertIndex = moduleNavigationBlockIndex ? moduleNavigationBlockIndex : 0

				// Insert the block.
				wp.data.dispatch( 'core/block-editor' ).insertBlocks( wp.blocks.createBlock( 'openlab-modules/sharing' ), insertIndex )

				// Return focus to the Module tab.
				setTimeout( () => {
					wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/document' )
				}, 100 )
			}
		}
	}, [ editPost ] )

	// Check if this is a new post and toggle sharing if so.
	useEffect(() => {
		setTimeout(	() => {
			if ( postId === null || postId === 0 || 'auto-draft' === postStatus ) {
				handleEnableSharingToggle( isSharingEnabled );
			}
		}, 1000 )
	}, [ isSharingEnabled, postId, postStatus, handleEnableSharingToggle ] );

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

			<Divider />

			<PanelRow>
				<ToggleControl
					label={ __( 'Enable shared cloning', 'openlab-modules' ) }
					help={ __( 'Allow others to clone this Module.', 'openlab-modules' ) }
					checked={ isSharingEnabled }
					onChange={ ( newIsSharingEnabled ) => handleEnableSharingToggle( newIsSharingEnabled ) }
				/>
			</PanelRow>
		</PluginDocumentSettingPanel>
	);
}
