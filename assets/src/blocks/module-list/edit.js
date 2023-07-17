import { __, sprintf } from '@wordpress/i18n';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

const BLOCKS_TEMPLATE = [
	[
		'core/query',
		{
			queryId: 'module-list-query',
			query: {
				perPage: 10,
				pages: 0,
				offset: 0,
				postType: 'openlab_module',
				order: 'asc',
				orderBy: 'title',
				author: '',
				search: '',
				exclude: [],
				sticky: '',
				inherit: false,
				parents: []
			}
		},
		[
			[
				'core/post-template',
				{},
				[
					[ 'core/post-title', { isLink: true } ]
				]
			],
			[
				'core/query-pagination',
				{},
				[
					[ 'core/query-pagination-previous' ],
					[ 'core/query-pagination-numbers' ],
					[ 'core/query-pagination-next' ]
				]
			],
			[
				'core/paragraph',
				{
					content: '<p>' + __( 'There are no modules on this site.', 'openlab-modules' ) + '</p>',
					placeholder: __( 'Add text or blocks that will display when your site has no modules.', 'openlab-modules' )
				}
			]
		]
	]
]


/**
 * Edit function.
 *
 * @return {WPElement} Element to render.
 */
export default function edit( {
	attributes
} ) {
	const blockProps = () => {
		let classNames = []

		return useBlockProps( {
			className: classNames
		} )
	}

	return (
		<div { ...blockProps() }>
			<InnerBlocks
				template={ BLOCKS_TEMPLATE }
			/>
		</div>
	)
}
