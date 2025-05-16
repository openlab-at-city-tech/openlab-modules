/**
 * The 'Completion Messages' section that appears on openlab_module items.
 */

import { PluginDocumentSettingPanel } from '@wordpress/editor'

import { __ } from '@wordpress/i18n'
import { useSelect } from '@wordpress/data'
import { PanelRow, TextareaControl } from '@wordpress/components'

export default function CompletionMessagesModule( {} ) {
	const {
		completionMessageCcString,
		postType
	} = useSelect( ( select ) => {
		return {
			completionMessageCcString: select( 'core/editor' ).getEditedPostAttribute( 'completionMessageCcString' ),
			postType: select( 'core/editor' ).getCurrentPostType()
		}
	} )

	if ( ! postType || 'openlab_module' !== postType ) {
		return null
	}

	return (
		<>
			<PluginDocumentSettingPanel
				className="openlab-module-completion-messages"
				name="openlab-module-completion-messages"
				title={ __( 'Completion Messages', 'openlab-modules' ) }
				>

				<PanelRow>
					<h3>{ __( 'Email', 'openlab-modules' ) }</h3>
				</PanelRow>

				<TextareaControl
					label={ __( 'CC', 'openlab-modules' ) }
					help={ __( 'Enter any addresses to be copied on the email, separated by commas.', 'openlab-modules' ) }
					value={ completionMessageCcString }
					onChange={ ( value ) => {
						wp.data.dispatch( 'core/editor' ).editPost( { completionMessageCcString: value } )
					} }
					placeholder={ __( 'Enter email addresses', 'openlab-modules' ) }
					/>

			</PluginDocumentSettingPanel>
		</>
	)
}
