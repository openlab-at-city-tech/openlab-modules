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

import { useSelect } from '@wordpress/data'

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
		thisModulePageIds,
		thisModulePages
	} = useSelect( ( select ) => {
		const allModules = select( 'core' ).getEntityRecords(
			'postType',
			'openlab_module',
			{
				order: 'asc',
				orderby: 'title',
				per_page: 100
			}
		)

		const thisModulePages = select( 'openlab-modules' ).getModulePages( moduleId )

		const thisModulePageIds = select( 'openlab-modules' ).getModulePageIds( moduleId ) || []

		return {
			allModules,
			thisModulePageIds,
			thisModulePages
		}
	}, [ moduleId ] )

	const moduleOptions = allModules ? allModules.map( ( module ) => {
		return {
			label: module.title.rendered,
			value: module.id.toString()
		}
	} ) : []

	const selectedModuleObject = allModules ? allModules.find( ( module ) => module.id === moduleId ) : null
	const selectedModuleTitle = selectedModuleObject ? selectedModuleObject.title.rendered : ''

	const modulePagesForDisplay = []
	for ( const modulePageId of thisModulePageIds ) {
		if ( thisModulePages.hasOwnProperty( modulePageId ) ) {
			modulePagesForDisplay.push( {
				id: thisModulePages[ modulePageId ].id,
				url: thisModulePages[ modulePageId ].url,
				title: thisModulePages[ modulePageId ].title
			} )
		}
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
						{ sprintf( __( 'Contents for Module: %s' ), selectedModuleTitle ) }
					</p>

					<ul className="openlab-modules-module-navigation-list" role="list">
						{modulePagesForDisplay.map((module)=>
							<li key={'module-page-' + module.id}>
								<a href={module.url}>{module.title}</a>
							</li>
						)}
					</ul>
				</div>

				{ isSelected && (
					<p className="openlab-modules-gloss">{ __( 'This navigation is dynamically generated based on the pages belonging to the Module.', 'openlab-modules' ) }</p>
				) }
			</div>
		</>
	)
}
