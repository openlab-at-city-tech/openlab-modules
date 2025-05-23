/**
 * The 'Completion Messages' section that appears on openlab_module items.
 */

import { PluginDocumentSettingPanel } from '@wordpress/editor'

import { __, sprintf } from '@wordpress/i18n'
import { useDispatch, useSelect } from '@wordpress/data'
import {
		// eslint-disable-next-line
		__experimentalDivider as Divider,
		PanelRow,
		TextareaControl
	} from '@wordpress/components'
import { useEffect, useState } from '@wordpress/element'

export default function CompletionMessagesModule( {} ) {
	const {
		completionMessageBodyFormat,
		completionMessageCcString,
		completionMessageSubject,
		completionPopupText,
		postTitle,
		postType
	} = useSelect( ( select ) => {
		return {
			completionMessageBodyFormat: select( 'core/editor' ).getEditedPostAttribute( 'completionMessageBodyFormat' ),
			completionMessageCcString: select( 'core/editor' ).getEditedPostAttribute( 'completionMessageCcString' ),
			completionMessageSubject: select( 'core/editor' ).getEditedPostAttribute( 'completionMessageSubject' ),
			completionPopupText: select( 'core/editor' ).getEditedPostAttribute( 'completionPopupText' ),
			postTitle: select( 'core/editor' ).getEditedPostAttribute( 'title' ),
			postType: select( 'core/editor' ).getCurrentPostType()
		}
	} )

	const { editPost } = useDispatch( 'core/editor' )

	const [ subjectDirty, setSubjectDirty ] = useState( false )
	const [ generatedSubject, setGeneratedSubject ] = useState( '' )

	const [ bodyFormatDirty, setBodyFormatDirty ] = useState( false )
	const [ generatedBodyFormat, setGeneratedBodyFormat ] = useState( '' )

	const [ popupTextDirty, setPopupTextDirty ] = useState( false )

	useEffect( () => {
		if ( ! subjectDirty && ! completionMessageSubject && postTitle ) {
			setGeneratedSubject(
				sprintf(
					// translators: %s is the title of the module.
					__( 'Well done! You have completed a section of the module: %s', 'openlab-modules' ),
					postTitle
				)
			)
		}

		if ( ! bodyFormatDirty && ! completionMessageBodyFormat ) {
			setGeneratedBodyFormat(
				// eslint-disable-next-line
				__( 'Hi {{display_name}},\n\nYou have completed the following:\n\nModule: {{module_title}} {{module_url}}\nSection: {{section_title}} {{section_url}}\n\nWell done!', 'openlab-modules' ),
			)
		}
	}, [ postTitle, completionMessageSubject, subjectDirty, completionMessageBodyFormat, bodyFormatDirty ] )

	if ( ! postType || 'openlab_module' !== postType ) {
		return null
	}

	const defaultPopupText = __( 'You have completed the activities on this page. You will receive an email confirming your completion.', 'openlab-modules' )

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

				<PanelRow>
					<p>{ __( 'Configure the email sent to users when they complete the interactive elements in a module section.', 'openlab-modules' ) }</p>
				</PanelRow>

				<Divider />

				<TextareaControl
					label={ __( 'CC', 'openlab-modules' ) }
					help={ __( 'Enter any addresses to be copied on the email, separated by commas.', 'openlab-modules' ) }
					value={ completionMessageCcString }
					onChange={ ( value ) => {
						wp.data.dispatch( 'core/editor' ).editPost( { completionMessageCcString: value } )
					} }
					placeholder={ __( 'Enter email addresses', 'openlab-modules' ) }
					/>

				<TextareaControl
					label={ __( 'Email subject', 'openlab-modules' ) }
					value={ subjectDirty || completionMessageSubject ? completionMessageSubject : generatedSubject }
					onChange={ ( value ) => {
						setSubjectDirty( true )
						editPost( { completionMessageSubject: value } )
					} }
					placeholder={ __( 'Enter email subject', 'openlab-modules' ) }
					/>

				<TextareaControl
					label={ __( 'Email text', 'openlab-modules' ) }
					value={ bodyFormatDirty || completionMessageBodyFormat ? completionMessageBodyFormat : generatedBodyFormat }
					onChange={ ( value ) => {
						setBodyFormatDirty( true )
						editPost( { completionMessageBodyFormat: value } )
					} }
					help={ __( 'Use the following tokens for dynamic values: {{display_name}}, {{module_title}}, {{module_url}}, {{section_title}}, {{section_url}}', 'openlab-modules' ) }
					/>

				<Divider />

				<PanelRow>
					<h3>{ __( 'Popup', 'openlab-modules' ) }</h3>
				</PanelRow>

				<PanelRow>
					<p>{ __( 'Edit the popup message users will see when the interactive activities in a module section are completed. This can also be changed on individual pages.', 'openlab-modules' ) }</p>
				</PanelRow>

				<TextareaControl
					label={ __( 'Message text', 'openlab-modules' ) }
					value={ popupTextDirty || completionPopupText ? completionPopupText : defaultPopupText }
					onChange={ ( value ) => {
						setPopupTextDirty( true )
						editPost( { completionPopupText: value } )
					} }
					placeholder={ __( 'Enter popup text', 'openlab-modules' ) }
					/>

			</PluginDocumentSettingPanel>
		</>
	)
}
