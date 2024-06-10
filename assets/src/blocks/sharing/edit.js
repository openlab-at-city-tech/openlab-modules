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

		const thisModule = select( 'core' ).getEntityRecord( 'postType', 'openlab_module', thisPageModuleId )

		return {
			isSharingEnabled: thisModule?.enableSharing || false,
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

			<p className="sharing-notice">
				{ __( 'Sharing is disabled for this module, and the "Clone this Module" button will not show on the front end. Remove this block, or enable sharing under the "Edit Module" settings in the Module toolbar.', 'openlab-modules' ) }
			</p>
		</div>
	)
}
