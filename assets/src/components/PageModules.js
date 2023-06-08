import {
	Button,
	PanelRow,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalDivider as Divider
} from '@wordpress/components'

import { __, _n } from '@wordpress/i18n'
import { useDispatch, useSelect } from '@wordpress/data'
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'

import SortableMultiSelect from './SortableMultiSelect'

import { select } from '@wordpress/data'

export default function PageModules( {
	isSelected
} ) {
	const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType() )
	const { editPost } = useDispatch( 'core/editor' )

	const {
		isNew,
		moduleToLink,
		pageModules,
		postId
	} = useSelect( ( select ) => {
		const postId = select( 'core/editor' ).getCurrentPostId()

		const urlParams = new URLSearchParams( window.location.search )
		const linkToModule = urlParams.get( 'link-to-module' )

		const pageModules = select( 'openlab-modules' ).getPageModules( postId )
		const moduleToLink = linkToModule ? select( 'core' ).getEntityRecord( 'postType', 'openlab_module', linkToModule ) : null

		return {
			isNew: select( 'core/editor' ).isEditedPostNew(),
			moduleToLink: moduleToLink ?? null,
			pageModules: pageModules ?? [],
			postId
		}
	}, [] )

	const isExistingPostWithModules = ! isNew && pageModules.length
	const isNewPostWithLinkToModule = isNew && moduleToLink

	React.useEffect(() => {
		// We send as postmeta and handle on the server, to work around restrictive caps.
		if ( isNewPostWithLinkToModule ) {
			editPost( {
				meta: { 'link_to_module': moduleToLink.id }
			} )
		}
	}, [isNewPostWithLinkToModule, moduleToLink]);

	// @todo Should this be dynamic?
	if ( 'page' !== postType ) {
		return null;
	}

	/*
	 * We show this panel only if
	 *  (a) it's an existing post with modules, or
	 *  (b) it's the New Post interface and there's a link-to-module URL param
	 */
	if ( ! isExistingPostWithModules && ! isNewPostWithLinkToModule ) {
		return null
	}

	return (
		<PluginDocumentSettingPanel
			name="openlab-modules-page-modules"
			title={ __( 'Modules', 'openlab-modules' ) }
			>

			{ isExistingPostWithModules && (
				<>
					<PanelRow>
						{ _n( 'This item is linked to the following module:', 'This item is linked to the following modules:', pageModules.length, 'openlab-modules' ) }
					</PanelRow>

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
				</>
			) }

			{ isNewPostWithLinkToModule && (
				<>
					<PanelRow>
						{ __( 'This item will be linked to the following module:', 'openlab-modules' ) }
					</PanelRow>

					<PanelRow>
						<div>
							{moduleToLink.title.rendered}
						</div>

						<div className="page-module-actions">
							<a href={ `${ window.location.origin }/wp-admin/post.php?post=${ moduleToLink.id }&action=edit` }
								>{ __( 'Edit', 'openlab-modules' ) }</a>
							&nbsp;|&nbsp;
							<a href={ moduleToLink.link }
								>{ __( 'View', 'openlab-modules' ) }</a>
						</div>
					</PanelRow>
				</>
			) }
		</PluginDocumentSettingPanel>
	);
}
