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
		moduleIds,
		postType
	} = useSelect( ( select ) => {
		return {
			completionPopupText: select( 'core/editor' ).getEditedPostAttribute( 'completionPopupText' ),
			moduleIds: select( 'core/editor' ).getEditedPostAttribute( 'moduleIds' ),
			postType: select( 'core/editor' ).getCurrentPostType()
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

				<TextareaControl
					label={ __( 'Popup Text', 'openlab-modules' ) }
					value={ completionPopupText }
					onChange={ ( newValue ) => {
						editPost( { completionPopupText: newValue } )
					} } />


			</PluginDocumentSettingPanel>
		</>
	)
}
