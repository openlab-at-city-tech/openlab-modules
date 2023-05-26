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

export default function PageModules( {
	isSelected
} ) {
	const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType() )

	// @todo Should this be dynamic?
	if ( 'page' !== postType ) {
		return null;
	}

	const {
		pageModules,
		postId
	} = useSelect( ( select ) => {
		const postId = select( 'core/editor' ).getCurrentPostId()

		const pageModules = select( 'openlab-modules' ).getPageModules( postId )

		return {
			pageModules: pageModules ?? [],
			postId
		}
	}, [] )

	return (
		<PluginDocumentSettingPanel
			name="openlab-modules-page-modules"
			title={ __( 'Modules', 'openlab-modules' ) }
			>

			{ pageModules.map( (pageModule) => (
				<PanelRow key={'page-module-' + pageModule.id}>
					<div>
						{pageModule.title}
					</div>

					<div className="page-module-actions">
						<a href={ pageModule.editUrl }
							>{ __( 'Edit', 'openlab-modules' ) }</a>
						&nbsp;|&nbsp;
						<a href={ pageModule.url }
							>{ __( 'View', 'openlab-modules' ) }</a>
					</div>
				</PanelRow>
			) ) }
		</PluginDocumentSettingPanel>
	);
}
