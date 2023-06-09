import React, { useState } from 'react'
import { useSortable } from '@dnd-kit/sortable'
import { __ } from '@wordpress/i18n'
import classNames from 'classnames'

import Item from './Item'

import './styles.scss'

const SortableItem = (props) => {
	const { id, label, url, editUrl, postStatus, handleRemoveClick } = props

	const [ hovered, setHovered ] = useState( false )

  const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition
	} = useSortable( { id } );

  const style = transform ? {
    transform: `translate3d(${transform.x}px, ${transform.y}px, 0px)`,
  } : undefined

	const itemClassnames = classNames({
		'sortable-multi-select-item': true,
		'sortable-multi-select-item-hover': hovered
	})

	const getStatusEl = ( postStatus ) => {
		switch ( postStatus ) {
			case 'trash' :
				return (
					<span className="module-page-inline-status module-page-non-publish-inline-status">{ __( '(Trash)', 'openlab-modules' ) }</span>
				)

			case 'draft' :
				return (
					<span className="module-page-inline-status module-page-non-publish-inline-status">{ __( '(Draft)', 'openlab-modules' ) }</span>
				)

			case 'publish' :
			default :
				return ( <></> )
		}
	}

	// The REST API returns escaped characters.
	const editUrlClean = editUrl.replace( '&amp;', '&' )

  return (
    <Item
			ref={setNodeRef}
			style={style}
			label={label}
			{...attributes}
			className={itemClassnames}
		>
			<div
				className='sortable-multi-select-item-handle'
				onMouseEnter={() => setHovered(true)}
				onMouseLeave={() => setHovered(false)}
				{...listeners}
			>{label}{getStatusEl(postStatus)}</div>

			<div>
				<a href={editUrlClean}>{ __( 'Edit', 'openlab-modules' ) }</a>
				&nbsp;|&nbsp;
				<a href={url}>{ __( 'View', 'openlab-modules' ) }</a>
				&nbsp;|&nbsp;
				<a
					href={editUrl}
					onClick={(e) => {
						e.preventDefault()
						handleRemoveClick( id )
					}}
				>{ __( 'Remove', 'openlab-modules' ) }</a>
			</div>
    </Item>
  );
}

export default SortableItem
