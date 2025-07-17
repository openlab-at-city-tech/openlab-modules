import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useSelect, select } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';

const ALLOWED_BLOCKS = [ 'core/details' ];

const ackIcon = (
	<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
		<path fillRule="evenodd" clipRule="evenodd" d="M10 4.5C10 4.76522 9.89464 5.01957 9.70711 5.20711C9.51957 5.39464 9.26522 5.5 9 5.5C8.73478 5.5 8.48043 5.39464 8.29289 5.20711C8.10536 5.01957 8 4.76522 8 4.5C8 4.23478 8.10536 3.98043 8.29289 3.79289C8.48043 3.60536 8.73478 3.5 9 3.5C9.26522 3.5 9.51957 3.60536 9.70711 3.79289C9.89464 3.98043 10 4.23478 10 4.5ZM11.5 4.5C11.5 5.16304 11.2366 5.79893 10.7678 6.26777C10.2989 6.73661 9.66304 7 9 7C8.33696 7 7.70107 6.73661 7.23223 6.26777C6.76339 5.79893 6.5 5.16304 6.5 4.5C6.5 3.83696 6.76339 3.20107 7.23223 2.73223C7.70107 2.26339 8.33696 2 9 2C9.66304 2 10.2989 2.26339 10.7678 2.73223C11.2366 3.20107 11.5 3.83696 11.5 4.5ZM13.75 12V11C13.75 10.2707 13.4603 9.57118 12.9445 9.05546C12.4288 8.53973 11.7293 8.25 11 8.25H7C6.27065 8.25 5.57118 8.53973 5.05546 9.05546C4.53973 9.57118 4.25 10.2707 4.25 11V12H5.75V11C5.75 10.31 6.31 9.75 7 9.75H11C11.69 9.75 12.25 10.31 12.25 11V12H13.75ZM4 20H13V18.5H4V20ZM20 16H4V14.5H20V16Z" fill="#1E1E1E"/>
	</svg>
)

registerBlockType( 'openlab-modules/module-acknowledgements', {
	title: __( 'Module Acknowledgments', 'openlab-modules' ),
	icon: ackIcon,
	attributes: {
		hasContent: {
			type: 'boolean',
			default: true,
		},
	},
	category: 'layout',
	supports: {
		html: false,
	},
	edit( { attributes, setAttributes, clientId, isSelected } ) {
		const blockProps = useBlockProps();

		// 1. Static content from post meta (only used on initial insert)
		const initialParagraphContent = useMemo( () => {
			const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			const attributionData = select( 'core/editor' ).getEditedPostAttribute( 'attributionData' ) || {};
			const lines = [ meta.module_acknowledgements, attributionData.text ].filter( Boolean );
			return lines.join( '<br />' );
		}, [] );

		// 2. Generate template (only once on initial block insert)
		const TEMPLATE = useMemo( () => [
			[
				'core/details',
				{
					summary: __( 'Module Acknowledgments', 'openlab-modules' ),
				},
				[
					[
						'core/paragraph',
						{
							content: initialParagraphContent,
						}
					]
				]
			],
		], [ initialParagraphContent ] );

		// 3. Reactively check if inner content is empty
		const isEmpty = useSelect( ( select ) => {
			const children = select( 'core/block-editor' ).getBlocks( clientId );
			if ( ! children.length ) {
				return true;
			}

			const detailsBlock = children.find( ( block ) => block.name === 'core/details' );
			if ( ! detailsBlock ) {
				return true;
			}

			// Consider the details content empty if all children are empty/whitespace
			const innerBlocks = detailsBlock.innerBlocks || [];
			return innerBlocks.every( ( block ) => {
				if ( block.name === 'core/paragraph' ) {
					return ! block.attributes?.content?.trim();
				}
				// optionally treat all other blocks as non-empty
				return false;
			} );
		}, [ clientId ] );

		useEffect( () => {
			if ( attributes.hasContent !== !isEmpty ) {
				setAttributes( { hasContent: !isEmpty } );
			}
		}, [ isEmpty ] );

		return (
			<div { ...blockProps }>
				<InnerBlocks
					allowedBlocks={ ALLOWED_BLOCKS }
					template={ TEMPLATE }
					templateLock="all"
				/>
			</div>
		);
	},
	save( { attributes } ) {
		const { hasContent } = attributes;

		if ( ! hasContent ) {
			return null; // Don't output anything at all
		}

		return (
			<div className="openlab-module-acknowledgments">
				<InnerBlocks.Content />
			</div>
		);
	},
} );
