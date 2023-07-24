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

import { PostPicker } from './PostPicker'

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

	const toggleAddMode = ( mode ) => {
		if ( mode === addMode ) {
			setAddMode( '' )
		} else {
			setAddMode( mode )
		}
	}

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
			url: newPage.link,
			status: 'publish'
		}

		const newModulePages = Object.assign( {}, modulePages, { [ newPage.id ]: newModulePage } )

		dispatch( 'openlab-modules' ).setModulePages( postId, newModulePages )
	}

	const fetchParams = { excludeModulePages: '1' }

	const returnIcon = (
		<svg xmlns="http://www.w3.org/2000/svg" height="48" viewBox="0 -960 960 960" width="48">
			<path d="M359-240 120-479l239-239 43 43-167 167h545v-172h60v231H236l166 166-43 43Z" fill="#3c7cb6" />
		</svg>
	)

	return (
		<>
			<PluginDocumentSettingPanel
				className="openlab-modules-add-page-to-module"
				name="openlab-modules-add-page-to-module"
				title={ __( 'Add Page to Module', 'openlab-modules' ) }
				>

				<fieldset>
					<PanelRow>
						<legend>{ __( 'Add a page to this module by choosing one of the following options:', 'openlab-modules' ) }</legend>
					</PanelRow>

					<div className="add-mode">
						<PanelRow>
							<div className={ 'create' === addMode ? 'add-mode-toggle add-mode-toggle-active' : 'add-mode-toggle' }>
								<Button
									onClick={ () => toggleAddMode( 'create' ) }
									text={ __( 'Create New Page', 'openlab-modules' ) }
								/>
							</div>
						</PanelRow>

						{ 'create' === addMode && (
							<div className="add-mode-content">
									<div className="add-mode-create-fields">
										<TextControl
											className="add-mode-text-field add-mode-create-title"
											onChange={ ( newTitle ) => setCreateTitle( newTitle ) }
											hideLabelFromVision={ true }
											label={ __( 'Add page title and press enter.', 'openlab-modules' ) }
											placeholder={ __( 'Add page title and press enter.', 'openlab-modules' ) }
											value={ createTitle }
										/>

										<Button
											className="add-mode-create-submit"
											disabled={ 0 === createTitle.length }
											onClick={ onCreateClick }
										>
											{ createInProgress ?
												( <>
														<div className="progress-spinner">&nbsp;</div>
														<div className="screen-reader-text">
															{ __( 'Page creation in progress', 'openlab-modules' ) }
														</div>
													</>
												) :
												( <>
														{returnIcon}
														<div className="screen-reader-text">
															{ __( 'Click to create page', 'openlab-modules' ) }
														</div>
													</>
												)
											}
										</Button>
									</div>
							</div>
						) }
					</div>

					<div className="add-mode">
						<PanelRow>
							<div className={ 'existing' === addMode ? 'add-mode-toggle add-mode-toggle-active' : 'add-mode-toggle' }>
								<Button
									onClick={ () => toggleAddMode( 'existing' ) }
									text={ __( 'Add Existing Page', 'openlab-modules' ) }
								/>
							</div>
						</PanelRow>

						{ 'existing' === addMode && (
							<div className="add-mode-content">
								<PanelRow>
									<PostPicker
										fetchParams={fetchParams}
										hideLabelFromVision={ true }
										onSelectPost={ onAddExistingPage }
										label={ __( 'Search for an existing page.', 'openlab-modules' ) }
										placeholder={ __( 'Search for an existing page.', 'openlab-modules' ) }
										postTypes={ [ 'pages' ] }
									/>
								</PanelRow>
							</div>
						) }
					</div>

				</fieldset>
			</PluginDocumentSettingPanel>

			<PluginDocumentSettingPanel
				name="openlab-modules-module-pages"
				title={ __( 'Module Pages', 'openlab-modules' ) }
				>

				<PanelRow>
					{ sortedOptions.length > 0 && (
						<SortableMultiSelect
							options={sortedOptions}
							onChange={onSort}
						/>
					) }

					{ sortedOptions.length === 0 && (
						<p>{ __( 'This module has no pages yet. Add or create a new page using the tools below.', 'openlab-modules' ) }</p>
					) }
				</PanelRow>
			</PluginDocumentSettingPanel>

		</>
	);
}
