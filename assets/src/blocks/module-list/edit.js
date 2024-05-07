import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

import { Spinner } from '@wordpress/components'

import { useSelect } from '@wordpress/data'

import './editor.scss'

/**
 * Edit function.
 *
 * @param {Object} props            Props.
 * @param {Object} props.attributes Block attributes.
 */
export default function Edit( {
	attributes
} ) {
	const { allModules } = useSelect( ( select ) => {
		const rawModules = select( 'core' ).getEntityRecords(
			'postType',
			'openlab_module',
			{
				order: 'asc',
				orderby: 'title',
				per_page: 100,
				status: 'any'
			}
		)

		const filteredModules = rawModules ? rawModules.filter( ( module ) => module.title.rendered.length > 0 ) : null

		return {
			allModules: filteredModules
		}
	} )

	return (
		<div { ...useBlockProps() }>
			{ ( null !== allModules && allModules.length > 0 ) && (
				<ul className="openlab-modules-module-list">
					{ allModules.map( ( module ) => (
						<li key={ 'module-' + module.id }>
							<h2><a href={ module.link }>{ module.title.rendered }</a></h2>

							<p className="module-description">
								{ module.meta.module_description }
							</p>
						</li>
					) ) }
				</ul>
			) }

			{ ( null !== allModules && allModules.length === 0 ) && (
				<p>{ __( 'This site has no modules to display.', 'openlab-modules' ) }</p>
			) }

			{ ( null === allModules ) && (
				<Spinner />
			) }
		</div>
	)
}
