import { useBlockProps } from '@wordpress/block-editor';
import { RawHTML } from '@wordpress/element';

/**
 * Edit function.
 *
 * @param {Object} props
 * @param {Object} props.attributes
 */
export default function edit( { attributes } ) {
	const { textContent } = attributes

	const blockProps = () => {
		const classNames = []

		return useBlockProps( {
			className: classNames
		} )
	}

	return (
		<div { ...blockProps() }>
			<RawHTML>{ textContent }</RawHTML>
		</div>
	)
}
