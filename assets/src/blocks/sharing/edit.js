import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

import './editor.scss';

/**
 * Edit function.
 *
 * @param {Object} props
 * @param {Object} props.attributes
 */
export default function Edit( { attributes } ) {
	const {} = attributes

	const { isSharingEnabled } = useSelect( ( select ) => {
		const thisPostId = select( 'core/editor' ).getCurrentPostId()
		const postType = select( 'core/editor' ).getCurrentPostType()

		const thisPageModuleIdCb = () => {
			if ( 'openlab_module' === postType ) {
				return thisPostId
			}

			/*
			if ( thisPostModuleIds ) {
				return thisPostModuleIds[ 0 ]
			}
			*/

			return 0
		}

		const thisPageModuleId = thisPageModuleIdCb()

		/*
		 * If the page currently being edited is thisPageModuleId, then we should get the
		 * value of enableSharing directly from getEditedPostAttribute() so that it's
		 * properly reactive.
		 */
		const getEnableSharing = () => {
			if ( thisPageModuleId === thisPostId ) {
				return select( 'core/editor' ).getEditedPostAttribute( 'enableSharing' )
			}

			const thisModule = select( 'core' ).getEntityRecord( 'postType', 'openlab_module', thisPageModuleId )
			return thisModule?.enableSharing || false
		}

		return {
			isSharingEnabled: getEnableSharing(),
		};
	} );

	const useCustomBlockProps = () => {
		let className = 'wp-block-openlab-modules-sharing'
		if ( ! isSharingEnabled ) {
			className += ' sharing-is-disabled'
		}

		return useBlockProps( {
			className
		} );
	}

	return (
		<div { ...useCustomBlockProps() }>
			<button
				className="clone-module-button clone-module-button-reset"
			>{ __( 'Clone this Module', 'openlab-modules' ) }</button>

			{ ! isSharingEnabled && (
				<p className="sharing-notice">
					{ __( 'Sharing is disabled for this module, so the "Clone this Module" button will not show on the front end. Remove this block, or enable sharing under the "Edit Module" settings in the Module toolbar.', 'openlab-modules' ) }
				</p>
			) }
		</div>
	)
}
