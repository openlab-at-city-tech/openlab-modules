import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import './block.scss';

/**
 * Edit function.
 *
 * @param {Object} props
 * @param {Object} props.attributes
 */
export default function Edit( { attributes } ) {
	const {} = attributes

	return (
		<div { ...useBlockProps() }>
			<button
				className="clone-module-button clone-module-button-reset"
			>{ __( 'Clone this Module', 'openlab-modules' ) }</button>
		</div>
	)
}
