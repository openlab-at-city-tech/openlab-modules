/**
 * The 'Completion Messages' section that appears on openlab_module items.
 */

import { PluginDocumentSettingPanel } from '@wordpress/editor'

import { CheckboxControl, TextareaControl } from '@wordpress/components'
import { useDispatch, useSelect } from '@wordpress/data'
import { __ } from '@wordpress/i18n'

export default function CompletionMessagesModule( {} ) {
	const {
		completionPopupText,
		includePopupTextInCompletionEmail,
		moduleIds,
		postType,
		sendCompletionEmail,
		showCompletionPopup
	} = useSelect( ( select ) => {
		return {
			completionPopupText: select( 'core/editor' ).getEditedPostAttribute( 'completionPopupText' ),
			includePopupTextInCompletionEmail: select( 'core/editor' ).getEditedPostAttribute( 'includePopupTextInCompletionEmail' ),
			moduleIds: select( 'core/editor' ).getEditedPostAttribute( 'moduleIds' ),
			postType: select( 'core/editor' ).getCurrentPostType(),
			sendCompletionEmail: select( 'core/editor' ).getEditedPostAttribute( 'sendCompletionEmail' ),
			showCompletionPopup: select( 'core/editor' ).getEditedPostAttribute( 'showCompletionPopup' )
		}
	} )

	const { editPost } = useDispatch( 'core/editor' )

	if ( ! postType || 'page' !== postType ) {
		return null
	}

	if ( ! moduleIds || moduleIds.length === 0 ) {
		return null
	}

	return (
		<>
			<PluginDocumentSettingPanel
				className="openlab-module-completion-messages"
				name="openlab-module-completion-messages"
				title={ __( 'Completion Messages', 'openlab-modules' ) }
				>

				<CheckboxControl
					label={ __( 'Show completion message popup when activities for this page are completed', 'openlab-modules' ) }
					checked={ showCompletionPopup }
					onChange={ ( newValue ) => {
						editPost( { showCompletionPopup: newValue } )
					} }
					/>

				<TextareaControl
					disabled={ ! showCompletionPopup }
					hideLabelFromVision={ true }
					label={ __( 'Popup Text', 'openlab-modules' ) }
					value={ completionPopupText }
					onChange={ ( newValue ) => {
						editPost( { completionPopupText: newValue } )
					} } />

				<CheckboxControl
					label={ __( 'Send completion email when activities for this page are completed', 'openlab-modules' ) }
					checked={ sendCompletionEmail }
					onChange={ ( newValue ) => {
						editPost( { sendCompletionEmail: newValue } )
					} } />

				<CheckboxControl
					disabled={ ! sendCompletionEmail }
					label={ __( 'Include popup text in the email', 'openlab-modules' ) }
					checked={ includePopupTextInCompletionEmail }
					onChange={ ( newValue ) => {
						editPost( { includePopupTextInCompletionEmail: newValue } )
					} } />

			</PluginDocumentSettingPanel>
		</>
	)
}
