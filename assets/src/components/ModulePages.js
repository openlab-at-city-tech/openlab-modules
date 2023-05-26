import {
	Button,
	PanelRow,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalDivider as Divider
} from '@wordpress/components'

import { __ } from '@wordpress/i18n'
import { useDispatch, useSelect } from '@wordpress/data'
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'

import SortableMultiSelect from './SortableMultiSelect'

import { select } from '@wordpress/data'

export default function EditModule( {
	isSelected
} ) {
	const {
		modulePageIds,
		modulePages,
		postId,
		postType
	} = useSelect( ( select ) => {
		const postId = select( 'core/editor' ).getCurrentPostId()

		const modulePages = select( 'openlab-modules' ).getModulePages( postId )

		const modulePageIdsRaw = select( 'core/editor' ).getEditedPostAttribute( 'meta' ).module_page_ids

		const modulePageIds = modulePageIdsRaw ? JSON.parse( modulePageIdsRaw ) : []

		return {
			modulePageIds,
			modulePages: modulePages ?? [],
			postId,
			postType: select( 'core/editor' ).getCurrentPostType()
		}
	}, [] )

	if ( 'openlab_module' !== postType ) {
		return null;
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
	}

	return (
		<PluginDocumentSettingPanel
			name="openlab-modules-edit-module"
			title={ __( 'Module Pages', 'openlab-modules' ) }
			>

			<PanelRow>
				<SortableMultiSelect
					options={sortedOptions}
					onChange={onSort}
				/>
			</PanelRow>
		</PluginDocumentSettingPanel>
	);
}
