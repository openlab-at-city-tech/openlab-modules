import { __, sprintf } from '@wordpress/i18n';

import {
	InspectorControls,
	useBlockProps
} from '@wordpress/block-editor';

import {
	Panel,
	PanelBody,
	PanelRow,
	SelectControl
} from '@wordpress/components'

import { useSelect, useDispatch } from '@wordpress/data'
import { useEffect } from '@wordpress/element'

/**
 * Editor styles.
 */
import './editor.scss';

/**
 * Edit function.
 *
 * @return {WPElement} Element to render.
 */
export default function edit( {
	attributes,
	isSelected,
	setAttributes,
} ) {
	const { moduleId } = attributes

	const blockProps = () => {
		let classNames = []

		return useBlockProps( {
			className: classNames
		} )
	}

	const {
		allModules,
		currentPostId,
		currentPostTitle,
		isNewModule,
		thisModulePageIds,
		thisModulePages
	} = useSelect( ( select ) => {
		const allModules = select( 'core' ).getEntityRecords(
			'postType',
			'openlab_module',
			{
				order: 'asc',
				orderby: 'title',
				per_page: 100,
				status: 'any'
			}
		)

		const thisModulePages = select( 'openlab-modules' ).getModulePages( moduleId )

		const thisModulePageIds = select( 'openlab-modules' ).getModulePageIds( moduleId ) || []

		const currentPostId = select( 'core/editor' ).getCurrentPostId()

		const editedPostId = select( 'core/editor' ).getEditedPostAttribute( 'id' )

		const postStatus = select( 'core/editor' ).getEditedPostAttribute( 'status' )
		const postType = select( 'core/editor' ).getCurrentPostType()
		const isNewModule = postStatus && 'auto-draft' === postStatus && postType && 'openlab_module' === postType

		const currentPostTitle = select( 'core/editor' ).getEditedPostAttribute( 'title' )

		return {
			allModules,
			currentPostId,
			currentPostTitle,
			isNewModule,
			thisModulePageIds,
			thisModulePages
		}
	}, [ moduleId ] )

	// When inserting into a new module, this block should be associated with the new module.
	useEffect( () => {
		if ( ! moduleId && isNewModule && currentPostId ) {
			setAttributes({ moduleId: currentPostId });
		}
	}, [ currentPostId ] );

	const optionLabel = ( title, status ) => {
			switch ( status ) {
				case 'publish' :
					return title

				case 'trash' :
					return sprintf( __( '%s (Trash)', 'openlab-modules' ), title )

				case 'draft' :
					return sprintf( __( '%s (Draft)', 'openlab-modules' ), title )
			}
	}

	const moduleOptions = allModules ? allModules.map( ( module ) => {
		return {
			label: optionLabel( module.title.rendered, module.status ),
			value: module.id.toString()
		}
	} ) : []

	// During module creation, the new module should appear as one of the dropdown options.
	if ( isNewModule ) {
		moduleOptions.unshift(
			{
				label: currentPostTitle,
				value: currentPostId
			}
		)
	}

	moduleOptions.unshift(
		{
			label: __( '- Select Module -', 'openlab-modules' ),
			value: '',
		}
	)

	// When this block appears in the context of the associated module, the title should live-update.
	const selectedModuleTitle = () => {
		if ( isNewModule || ( currentPostId && moduleId === currentPostId ) ) {
			return currentPostTitle
		} else {
			const selectedModuleObject = allModules ? allModules.find( ( module ) => module.id === moduleId ) : null
			return selectedModuleObject ? selectedModuleObject.title.rendered : ''
		}
	}

	const modulePagesForDisplay = []

	modulePagesForDisplay.push( {
		id: moduleId,
		url: '',
		title: __( 'Module Home', 'openlab-modules' ),
		statusCode: 'publish',
		statusEl: <></>
	} )

	for ( const modulePageId of thisModulePageIds ) {
		if ( thisModulePages && thisModulePages.hasOwnProperty( modulePageId ) ) {
			const statusEl = ( postStatus ) => {
				switch ( postStatus ) {
					case 'publish' :
						return (
							<></>
						)

					case 'trash' :
						return (
							<span className="module-page-status module-page-status-trash">{ __( 'This page is in the trash and will not appear on the frontend.', 'openlab-modules' ) }</span>
						)

					case 'draft' :
						return (
							<span className="module-page-status module-page-status-draft">{ __( 'This page is in draft status and will not appear on the frontend.', 'openlab-modules' ) }</span>
						)

					default :
						const elClassName = 'module-page-status module-page-status-' + postStatus
						return (
							<span className={ elClassName }>{ __( 'Draft', 'openlab-modules' ) }</span>
						)
				}
			}

			const thisPage = thisModulePages[ modulePageId ]

			modulePagesForDisplay.push( {
				id: thisPage.id,
				url: thisPage.url,
				title: thisPage.title,
				statusCode: thisPage.status,
				statusEl: statusEl( thisPage.status )
			} )
		}
	}

	const dispatch = useDispatch()

	const onAddClick = () => {
		wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/document' )

		setTimeout( () => {
			const addPagePanel = document.querySelector( '.openlab-modules-add-page-to-module' )
			if (addPagePanel) {
					wp.data.dispatch( 'core/block-editor' ).clearSelectedBlock();
					addPagePanel.classList.add('highlight');
					setTimeout(() => {
						addPagePanel.classList.remove('highlight');
					}, 5000);
			}
		}, 100 )
	}

	return (
		<>
			<InspectorControls>
				<Panel>
					<PanelBody title={ __( 'Navigation Settings', 'openlab-modules' ) }>
						<SelectControl
							help={ __( 'Select the Module whose navigation should be displayed.', 'openlab-modules' ) }
							label={ __( 'Module', 'openlab-modules' ) }
							onChange={ ( moduleId ) => setAttributes( { moduleId: parseInt( moduleId, 10 ) } ) }
							options={ moduleOptions }
							value={ moduleId.toString() }
						/>
					</PanelBody>
				</Panel>
			</InspectorControls>

			<div { ...blockProps() }>
				<div className="openlab-modules-module-navigation">
					<p className="openlab-modules-module-navigation-heading">
						{ sprintf( __( 'Contents for Module: %s' ), selectedModuleTitle() ) }
					</p>

					<ul className="openlab-modules-module-navigation-list" role="list">
						{ modulePagesForDisplay.map( (module) => {
							const pageClassName = 'publish' !== module.statusCode ? 'module-page-has-non-publish-status module-page-has-status-' + module.StatusCode : 'module-page-has-publish-status'

							return (
								<li key={'module-page-' + module.id} className={ pageClassName }>
									<a href={module.url}>
										{module.title}
									</a>

									{module.statusEl}
								</li>
							)
						} ) }
					</ul>
				</div>

				{ isSelected && (
					<>
						<p className="openlab-modules-gloss">{ __( 'This navigation is dynamically generated based on the pages belonging to the Module.', 'openlab-modules' ) }</p>

						<p className="openlab-modules-gloss">
							<button
								className="add-a-page-link"
								onClick={onAddClick}
							>{ __( 'Add a page to this module', 'openlab-modules' ) }
							</button>
						</p>
					</>
				) }
			</div>
		</>
	)
}
