import {
	Button,
	PanelRow,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalDivider as Divider
} from '@wordpress/components'

import { __ } from '@wordpress/i18n'
import { dispatch, select, useDispatch, useSelect } from '@wordpress/data'
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'

import { PostPicker } from 'gutenberg-post-picker'

import SortableMultiSelect from './SortableMultiSelect'

import { useState } from '@wordpress/element'

import apiFetch from '@wordpress/api-fetch'

import './module-pages.scss'

export default function EditModule( {
	isSelected
} ) {
	const [ addMode, setAddMode ] = useState( '' )
	const [ createTitle, setCreateTitle ] = useState( '' )
	const [ createInProgress, setCreateInProgress ] = useState( false )

	const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType() )

	if ( ! postType || 'openlab_module' !== postType ) {
		return null
	}

	const {
		modulePageIds,
		modulePages,
		postId
	} = useSelect( ( select ) => {
		const postId = select( 'core/editor' ).getCurrentPostId()

		const modulePages = select( 'openlab-modules' ).getModulePages( postId )

		const modulePageIdsRaw = select( 'core/editor' ).getEditedPostAttribute( 'meta' ).module_page_ids

		const modulePageIds = modulePageIdsRaw ? JSON.parse( modulePageIdsRaw ) : []

		return {
			modulePageIds,
			modulePages: modulePages ?? [],
			postId
		}
	}, [] )

	const { editPost } = useDispatch( 'core/editor' )

	const editPostMeta = ( metaToUpdate ) => {
		editPost( { meta: metaToUpdate } )
	}

	const sortedOptions = []
	for ( const pageId of modulePageIds ) {
		if ( ! modulePages.hasOwnProperty( pageId ) ) {
			continue
		}

		sortedOptions.push( modulePages[ pageId ] )
	}

	const onSort = ( newlySortedOptions ) => {
		const sortedIds = []

		for ( const option of newlySortedOptions ) {
			sortedIds.push( option.id )
		}

		editPostMeta( { module_page_ids: JSON.stringify( sortedIds ) } )

		// We mirror the page order in our own store to ensure accuracy of navigation block.
		dispatch( 'openlab-modules' ).setModulePageIds( postId, sortedIds )
	}

	const onAddExistingPage = ( newPage ) => {
		// Don't allow existing pages to be added.
		const existingIndex = modulePageIds.indexOf( newPage.id )
		if ( -1 !== existingIndex ) {
			return
		}

		addPage( newPage )
	}

	const onCreateClick = () => {
		setCreateInProgress( true )

		const postData = {
			title: createTitle,
			content: '',
			status: 'publish',
			type: 'page'
		}

		apiFetch({
			path: '/wp/v2/pages',
			method: 'POST',
			data: postData
		})
			.then((response) => {
				// Handle successful response
				const { id, title, link } = response

				addPage( response )

				setCreateTitle( '' )
				setCreateInProgress( false )
				setAddMode( '' )
			})
			.catch((error) => {
				// Handle error
				console.error('Failed to create post:', error)

				setCreateInProgress( false )
			})
	}

	const addPage = ( newPage ) => {
		const newModulePageIds = [ ...modulePageIds, newPage.id ]

		editPostMeta( { module_page_ids: JSON.stringify( newModulePageIds ) } )

		// We mirror the page order in our own store to ensure accuracy of navigation block.
		dispatch( 'openlab-modules' ).setModulePageIds( postId, newModulePageIds )

		const newModulePage = {
			editUrl: newPage.editUrl,
			id: newPage.id,
			title: newPage.title.rendered,
			url: newPage.link
		}

		const newModulePages = Object.assign( {}, modulePages, { [ newPage.id ]: newModulePage } )

		dispatch( 'openlab-modules' ).setModulePages( postId, newModulePages )
	}


	return (
		<>
			<PluginDocumentSettingPanel
				name="openlab-modules-module-pages"
				title={ __( 'Module Pages', 'openlab-modules' ) }
				>

				<PanelRow>
					<SortableMultiSelect
						options={sortedOptions}
						onChange={onSort}
					/>
				</PanelRow>
			</PluginDocumentSettingPanel>

			<PluginDocumentSettingPanel
				className="openlab-modules-add-page-to-module"
				name="openlab-modules-add-page-to-module"
				title={ __( 'Add Page to Module', 'openlab-modules' ) }
				>

				<fieldset>
					<PanelRow>
						<legend>{ __( 'Add a page to this module by choosing one of the following options', 'openlab-modules' ) }</legend>
					</PanelRow>

					<PanelRow>
						<div className="add-mode-toggle">
							<Button
								onClick={ () => setAddMode( 'create' ) }
								text={ __( 'Create New Page', 'openlab-modules' ) }
								variant="primary"
							/>

							{ 'create' === addMode && (
								<span
									className="add-mode-cancel"
									onClick={ () => setAddMode( '' ) }
								>{'\u2716'}</span>
							) }
						</div>
					</PanelRow>

					{ 'create' === addMode && (
						<>
							<PanelRow>
								<TextControl
									className="add-mode-text-field add-mode-create-title"
									onChange={ ( newTitle ) => setCreateTitle( newTitle ) }
									hideLabelFromVision={ true }
									label={ __( 'Enter a title for the new page', 'openlab-modules' ) }
									placeholder={ __( 'Enter a title for the new page', 'openlab-modules' ) }
									value={ createTitle }
								/>

								<Button
									disabled={ 0 === createTitle.length }
									onClick={ onCreateClick }
									variant="primary"
								>
									{ createInProgress ? <span className="progress-spinner"></span> : __( 'Create', 'openlab-modules' ) }
								</Button>
							</PanelRow>
						</>
					) }

					<PanelRow>
						<div className="add-mode-toggle">
							<Button
								onClick={ () => setAddMode( 'existing' ) }
								text={ __( 'Add Existing Page', 'openlab-modules' ) }
								variant="primary"
							/>

							{ 'existing' === addMode && (
								<span
									className="add-mode-cancel"
									onClick={ () => setAddMode( '' ) }
								>{'\u2716'}</span>
							) }
						</div>
					</PanelRow>

					{ 'existing' === addMode && (
						<PanelRow>
							<PostPicker
								hideLabelFromVision={ true }
								onSelectPost={ onAddExistingPage }
								label={ __( 'Search for an existing page.', 'openlab-modules' ) }
								placeholder={ __( 'Search for an existing page.', 'openlab-modules' ) }
								postTypes={ [ 'pages' ] }
							/>
						</PanelRow>
					) }

				</fieldset>
			</PluginDocumentSettingPanel>
		</>
	);
}
