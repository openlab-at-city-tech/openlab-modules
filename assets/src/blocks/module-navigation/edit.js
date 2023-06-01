import { __ } from '@wordpress/i18n';

import {
	useBlockProps
} from '@wordpress/block-editor';

/**
 * Editor styles.
 */
//import './editor.scss';

/**
 * Edit function.
 *
 * @return {WPElement} Element to render.
 */
export default function edit( {
	attributes,
	setAttributes,
} ) {

	const blockProps = () => {
		let classNames = []

		return useBlockProps( {
			className: classNames
		} )
	}

	return (
        <div { ...blockProps() }>
            { __( 'Navigation will appear here', 'openlab-modules' ) }
        </div>
	)
}
