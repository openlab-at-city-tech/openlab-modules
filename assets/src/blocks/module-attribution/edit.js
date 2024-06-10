import { __, sprintf } from '@wordpress/i18n';

import ServerSideRender from '@wordpress/server-side-render';

import {
	InspectorControls,
	useBlockProps
} from '@wordpress/block-editor';

import {
	Panel,
	PanelBody,
	SelectControl
} from '@wordpress/components'

import { useSelect } from '@wordpress/data'
import { useEffect } from '@wordpress/element'

/**
 * Editor styles.
 */
import './editor.scss';

/**
 * Edit function.
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Set attributes.
 */
export default function Edit( {
	attributes,
	setAttributes,
} ) {
	const { moduleId } = attributes

	const {
		allModules,
		currentPostId,
		currentPostTitle,
		isModule,
		isNewModule,
		pageModuleId
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
			thisModulePageIds: select( 'openlab-modules' ).getModulePageIds( thisPageModuleId ) || [],
		}
	}, [ moduleId ] )

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

	const optionLabel = ( title, status ) => {
			switch ( status ) {
				case 'publish' :
					return title

				case 'trash' :
					// translators: %s: module title
					return sprintf( __( '%s (Trash)', 'openlab-modules' ), title )

				case 'draft' :
					// translators: %s: module title
					return sprintf( __( '%s (Draft)', 'openlab-modules' ), title )
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
			label: __( '- Select Module -', 'openlab-modules' ),
			value: '',
		}
	)

	const selectedModuleObject = allModules ? allModules.find( ( module ) => module.id === selectedModuleId ) : null


	/*
	if ( attributionText ) {
		// Attribution text is stored as HTML. We must parse in order to make it safe for
		// display in a way that will not be escaped by React.
		const links = attributionText.match( /<a[^>]*>.*?<\/a>/g )
		const linkData = links ? links.map( ( link ) => {
			const href = link.match( /href="([^"]*)"/ )[ 1 ]
			const text = link.match( />(.*?)</ )[ 1 ]
			return { href, text }
		} ) : []

		// Identify the chunks of text that are not links.
		const textChunks = attributionText.split( /<a[^>]*>.*?<\/a>/g )

		// Build an element array that interleaves text and links.
		textChunks.forEach( ( chunk, index ) => {
			attributionElements.push(
				<span
					key={ 'chuck-' + index }
				>{chunk}</span>
			)
			if ( linkData[ index ] ) {
				attributionElements.push(
					<a
						href={ linkData[ index ].href }
						key={ index }
					>{ linkData[ index ].text }</a>
				)
			}
		} )
	}
	*/

	return (
		<>
			<InspectorControls>
				<Panel>
					<PanelBody title={ __( 'Navigation Settings', 'openlab-modules' ) }>
						<SelectControl
							help={ __( 'Select the Module whose attribution should be displayed.', 'openlab-modules' ) }
							label={ __( 'Module', 'openlab-modules' ) }
							onChange={ ( newModuleId ) => setAttributes( { moduleId: parseInt( newModuleId, 10 ) } ) }
							options={ moduleOptions }
							value={ selectedModuleId.toString() }
						/>
					</PanelBody>
				</Panel>
			</InspectorControls>

			<div { ...useBlockProps() }>
				<ServerSideRender
					block="openlab-modules/module-attribution"
					attributes={ {
						moduleId: selectedModuleId
					} }
				/>
			</div>
		</>
	)
}
