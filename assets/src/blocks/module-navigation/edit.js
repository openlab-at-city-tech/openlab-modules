import { __, sprintf } from '@wordpress/i18n';

import he from 'he';

import {
	InspectorControls,
	useBlockProps
} from '@wordpress/block-editor';

import {
	Button,
	CheckboxControl,
	Panel,
	PanelBody,
	PanelRow,
	Popover,
	SelectControl
} from '@wordpress/components'

import { useSelect } from '@wordpress/data'
import { useEffect, useState } from '@wordpress/element'
import { addFilter } from '@wordpress/hooks';

/**
 * Editor styles.
 */
import './editor.scss';

/**
 * Module Page List Item Component.
 *
 * @param {Object}   props                Component props.
 * @param {Object}   props.module         Module data.
 * @param {number}   props.activePopover  Active popover ID.
 * @param {Function} props.togglePopover  Function to toggle popover.
 */
const ModulePageListItem = ( { module, activePopover, togglePopover } ) => {
	const pageClassName = 'publish' !== module.statusCode ? 'module-page-has-non-publish-status module-page-has-status-' + module.statusCode : 'module-page-has-publish-status';

	return (
		<li key={'module-page-' + module.id} className={ pageClassName }>
			<a
				className="module-navigation-editor-link"
				onClick={() => togglePopover( module.id )}
				href="#"
			>
				{module.title}

				{activePopover === module.id && (
					<Popover>
						<div className="module-navigation-link-popover">
							<div className="module-navigation-link-popover-title">
								<span className="dashicons dashicons-excerpt-view"></span>
								<a href={module.url} target="_blank">{module.title}</a>
							</div>

							<div className="module-navigation-link-popover-excerpt">
								{module.excerpt}
							</div>

							<div className="module-navigation-link-popover-actions">
								<Button
									className="module-navigation-link-edit"
									href={module.editUrl}
									variant="secondary"
									target="_blank"
								>{ __( 'Edit Page', 'openlab-module-builder' ) }</Button>

								<Button
									className="module-navigation-link-visit"
									href={module.url}
									variant="secondary"
									target="_blank"
								>{ __( 'Visit Page', 'openlab-module-builder' ) }</Button>

							</div>
						</div>
					</Popover>
				)}
			</a>

			{module.statusEl}
		</li>
	);
};

/**
 * Edit function.
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {boolean}  props.isSelected    Whether the block is selected.
 * @param {Function} props.setAttributes Set attributes.
 */
export default function Edit( {
	attributes,
	isSelected,
	setAttributes,
} ) {
	const { moduleId, showModuleDescription, listStyle } = attributes
	const [ activePopover, setActivePopover ] = useState( null );

	const closePopoverOnClick = ( event ) => {
		if ( event.target.closest( '.module-navigation-link-popover' ) ) {
			return;
		}

		if ( event.target.closest( '.module-navigation-editor-link' ) ) {
			return;
		}

		setActivePopover( null );
	}

	const closePopoverOnEsc = ( event ) => {
		if ( 27 === event.keyCode ) {
			setActivePopover( null );
		}
	}

	const togglePopover = ( toggleModuleId ) => {
		if ( activePopover === toggleModuleId ) {
			setActivePopover( null );

			document.removeEventListener( 'click', closePopoverOnClick );
		} else {
			setActivePopover( toggleModuleId );

			// Catch clicks outside of the popover.
			setTimeout( () => {
				document.addEventListener( 'click', closePopoverOnClick );
			}, 500 )

			// Catch 'esc' keypresses.
			document.addEventListener( 'keydown', closePopoverOnEsc );
		}
	};

	const {
		allModules,
		currentPostId,
		currentPostTitle,
		editedModuleNavTitle,
		isModule,
		isNewModule,
		pageModuleId,
		rawThisModulePageIds,
		rawThisModulePages
	} = useSelect( ( select ) => {
		const _allModules = select( 'core' ).getEntityRecords(
			'postType',
			'openlab_module',
			{
				context: 'edit',
				order: 'asc',
				orderby: 'title',
				per_page: 100,
				status: 'any'
			}
		)

		const postStatus = select( 'core/editor' ).getEditedPostAttribute( 'status' )
		const postType = select( 'core/editor' ).getCurrentPostType()

		// get moduleIds property of the current post
		const thisPostModuleIds = select( 'core/editor' ).getEditedPostAttribute( 'moduleIds' )

		const thisPostId = select( 'core/editor' ).getCurrentPostId()

		const thisPageModuleIdCb = () => {
			if ( moduleId > 0 ) {
				return moduleId
			}

			if ( 'openlab_module' === postType ) {
				return thisPostId
			}

			if ( thisPostModuleIds ) {
				return thisPostModuleIds[ 0 ]
			}

			return 0
		}

		const thisPageModuleId = thisPageModuleIdCb()

		return {
			allModules: _allModules,
			currentPostId: thisPostId,
			currentPostTitle: select( 'core/editor' ).getEditedPostAttribute( 'title' ),
			editedModuleNavTitle: select( 'core/editor' ).getEditedPostAttribute( 'moduleNavTitle' ),
			isModule: postType && 'openlab_module' === postType,
			isNewModule: postStatus && 'auto-draft' === postStatus && postType && 'openlab_module' === postType,
			pageModuleId: thisPageModuleId,
			rawThisModulePageIds: select( 'openlab-module-builder' ).getModulePageIds( thisPageModuleId ) || null,
			rawThisModulePages: select( 'openlab-module-builder' ).getModulePages( thisPageModuleId ) || null
		}
	}, [ moduleId ] )

	const thisModulePageIds = rawThisModulePageIds || []
	const thisModulePages = rawThisModulePages || {}

	// If a moduleId is passed as an attribute, trust it. Otherwise, fall back
	// on the contextually correct module ID.
	const selectedModuleId = moduleId > 0 ? moduleId : pageModuleId

	// If a nonzero selectedModuleId has been calculated, set the corresponding
	// block attribute. This ensures that newly created blocks will have their
	// moduleId attribute set to the correct value.
	useEffect( () => {
		if ( selectedModuleId > 0 && 0 === moduleId ) {
			setAttributes( { moduleId: selectedModuleId } )
		}
	}, [ selectedModuleId, moduleId, setAttributes ] )

	// Set default styles on mount if not already set
  useEffect(() => {
    if (!attributes.style) {
      setAttributes({
        style: {
          border: {
            color: "#eee",
            radius: "2px",
            style: "solid",
            width: "2px"
          },
          spacing: {
            padding: {
              top: "20px",
              right: "20px",
              bottom: "20px",
              left: "20px"
            }
          }
        }
      });
    }
  }, []);

	const optionLabel = ( title, status ) => {
			switch ( status ) {
				case 'publish' :
					return title

				case 'trash' :
					// translators: %s: module title
					return sprintf( __( '%s (Trash)', 'openlab-module-builder' ), title )

				case 'draft' :
					// translators: %s: module title
					return sprintf( __( '%s (Draft)', 'openlab-module-builder' ), title )
			}
	}

	const moduleOptions = allModules ? allModules.map( ( module ) => {
		return {
			label: optionLabel( module.title.raw, module.status ),
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
			label: __( '- Select Module -', 'openlab-module-builder' ),
			value: '',
		}
	)

	const selectedModuleObject = allModules ? allModules.find( ( module ) => module.id === selectedModuleId ) : null

	// When this block appears in the context of the associated module, the title should live-update.
	const selectedModuleTitle = () => {
		if ( isNewModule || ( currentPostId && selectedModuleId === currentPostId ) ) {
			return currentPostTitle
		}

		return selectedModuleObject ? selectedModuleObject.title.raw : ''
	}

	const modulePagesForDisplay = []

	const selectedModuleNavTitle = () => {
		if ( isModule && ( currentPostId && selectedModuleId === currentPostId ) ) {
			return editedModuleNavTitle
		}

		return selectedModuleObject ? selectedModuleObject.moduleNavTitle : ''
	}

	const selectedModuleUrl = () => {
		return selectedModuleObject ? selectedModuleObject.link : ''
	}

	modulePagesForDisplay.push( {
		editUrl: selectedModuleObject ? selectedModuleObject.editUrl.replace( '&amp;', '&' ) : '',
		excerpt: selectedModuleObject ? he.decode( selectedModuleObject.excerptForPopover ) : '',
		id: selectedModuleId,
		url: selectedModuleUrl(),
		title: selectedModuleNavTitle(),
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
							<span className="module-page-status module-page-status-trash">{ __( 'This page is in the trash and will not appear on the frontend.', 'openlab-module-builder' ) }</span>
						)

					case 'draft' :
						return (
							<span className="module-page-status module-page-status-draft">{ __( 'This page is in draft status and will not appear on the frontend.', 'openlab-module-builder' ) }</span>
						)

					default :
						const elClassName = 'module-page-status module-page-status-' + postStatus
						return (
							<span className={ elClassName }>{ __( 'Draft', 'openlab-module-builder' ) }</span>
						)
				}
			}

			const thisPage = thisModulePages[ modulePageId ]

			modulePagesForDisplay.push( {
				editUrl: thisPage.editUrl.replace( '&amp;', '&' ),
				excerpt: he.decode( thisPage.excerptForPopover ),
				id: thisPage.id,
				url: thisPage.url,
				title: he.decode( thisPage.title ),
				statusCode: thisPage.status,
				statusEl: statusEl( thisPage.status )
			} )
		}
	}

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
					<PanelBody title={ __( 'Settings', 'openlab-module-builder' ) }>
						<PanelRow>
							<SelectControl
								help={ __( 'Select the module to display in the TOC.', 'openlab-module-builder' ) }
								label={ __( 'Module', 'openlab-module-builder' ) }
								onChange={ ( newModuleId ) => setAttributes( { moduleId: parseInt( newModuleId, 10 ) } ) }
								options={ moduleOptions }
								value={ selectedModuleId.toString() }
							/>
						</PanelRow>

						<PanelRow>
							<CheckboxControl
								label={ __( 'Module Description', 'openlab-module-builder' ) }
								help={ __( 'Include the Module Description in the TOC. This can be edited on the Module Settings.', 'openlab-module-builder' ) }
								checked={ showModuleDescription }
								onChange={ ( newShowModuleDescription ) => setAttributes( { showModuleDescription: newShowModuleDescription } ) }
							/>
						</PanelRow>

						<PanelRow>
							<SelectControl
								label={ __( 'List Style', 'openlab-module-builder' ) }
								help={ __( 'Choose between an unordered or ordered list.', 'openlab-module-builder' ) }
								value={ listStyle }
								options={ [
									{ label: __( 'Unordered List', 'openlab-module-builder' ), value: 'unordered' },
									{ label: __( 'Ordered List', 'openlab-module-builder' ), value: 'ordered' }
								] }
								onChange={ ( newListStyle ) => setAttributes( { listStyle: newListStyle } ) }
							/>
						</PanelRow>
					</PanelBody>
				</Panel>
			</InspectorControls>

			<div { ...useBlockProps() }>
				<div className="openlab-modules-module-navigation">
					<p className="openlab-modules-module-navigation-heading">
						{ /* translators: %s: module title */ }
						{ sprintf( __( 'MODULE: %s', 'openlab-module-builder' ), selectedModuleTitle() ) }
					</p>

					{ showModuleDescription && selectedModuleObject && selectedModuleObject.meta.module_description && (
						<p className="openlab-modules-module-description">
							{ he.decode( selectedModuleObject.meta.module_description ) }
						</p>
					) }

					{ 'ordered' === listStyle ? (
						<ol className="openlab-modules-module-navigation-list">
							{ modulePagesForDisplay.map( (module) => (
								<ModulePageListItem
									key={'module-page-' + module.id}
									module={module}
									activePopover={activePopover}
									togglePopover={togglePopover}
								/>
							) ) }
						</ol>
					) : (
						<ul className="openlab-modules-module-navigation-list">
							{ modulePagesForDisplay.map( (module) => (
								<ModulePageListItem
									key={'module-page-' + module.id}
									module={module}
									activePopover={activePopover}
									togglePopover={togglePopover}
								/>
							) ) }
						</ul>
					) }
				</div>

				{ isSelected && (
					<>
						<p className="openlab-modules-gloss">
							<button
								className="add-a-page-link"
								onClick={onAddClick}
							>{ __( 'Add Page to Module (in the module settings panel)', 'openlab-module-builder' ) }
							</button>
						</p>

						<p className="openlab-modules-gloss">{ __( 'This Table of Contents (TOC) is dynamically generated based on the pages belonging to the module.', 'openlab-module-builder' ) }</p>
					</>
				) }
			</div>
		</>
	)
}

addFilter(
    'blocks.registerBlockType',
    'openlab-modules/details-navigator-label',
    (settings, name) => {
        if (name !== 'core/details') {
            return settings;
        }

        return {
            ...settings,
            __experimentalLabel: (attributes, { context }) => {
                // Only modify the label in the list view/navigator
                if (context === 'list-view') {
                    // Check for your custom label attribute
                    if (attributes.label) {
                        return attributes.label;
                    }
                    // Or always return 'Details' when we have class
										// 'is-style-module-acknowledgements-detail'
                    if (attributes.className?.includes('is-style-module-acknowledgements-detail')) {
                        return 'Details';
                    }
                }
                // Default behavior for other contexts
                return attributes.summary || '';
            }
        };
    }
);
