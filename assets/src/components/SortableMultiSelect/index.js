import React, { useState } from 'react'

import {
	closestCenter,
	DndContext,
	DragOverlay,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors
} from '@dnd-kit/core'

import {
	SortableContext,
  sortableKeyboardCoordinates,
	verticalListSortingStrategy
} from '@dnd-kit/sortable';

import SortableItem from './SortableItem'

const SortableMultiSelect = (props) => {
	const {
		onChange,
		options
	} = props

	const [ activeId, setActiveId ] = useState( null )

	const sensors = useSensors(
		useSensor(PointerSensor),
		useSensor(KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates
		})
	)

	const handleDragStart = (event) => {
		const { active } = event
		setActiveId( active.id )
	}

	const handleDragEnd = (event) => {
		const { active, over } = event

		if ( active.id !== over.id ) {
			const oldValue = Number( active.id.substr( 9 ) )
			const newValue = Number( over.id.substr( 9 ) )

			const oldIndex = findSelectedOptionIndexByValue( oldValue )
			const newIndex = findSelectedOptionIndexByValue( newValue )

			const sorted = arrayMove( options, oldIndex, newIndex )

			onChange( sorted )

			setActiveId( null )

			return sorted
		}
	}

	const arrayMove = (arr, from, to) => {
		const clone = [...arr];
		Array.prototype.splice.call(clone, to, 0,
			Array.prototype.splice.call(clone, from, 1)[0]
		);
		return clone;
	};

	const findSelectedOptionIndexByValue = (value) => {
		for ( var k in options ) {
			if ( value === options[k].value ) {
				return k
			} else {
				continue
			}
		}

		return -1
	}

	const onSelect = (newSelectedOption) => {
		const newSelectedOptions = [...options, newSelectedOption ]
		onChange( newSelectedOptions )
	}

	const handleRemoveClick = (itemHandle) => {
		const itemId = Number( itemHandle.substr( 9 ) )
		const itemIndex = findSelectedOptionIndexByValue( itemId )

		const newSelectedOptions = [...options.slice(0, itemIndex), ...options.slice(itemIndex + 1 )]
		onChange( newSelectedOptions )
	}

	const items = options.map( option => 'sortable-' + option.id )

	return (
		<div className="sortable-multi-select">
			<div className="sortable-multi-select-selected-items">
				<DndContext
					collisionDetection={closestCenter}
					onDragEnd={handleDragEnd}
					sensors={sensors}
				>
					<SortableContext
						items={items}
						strategy={verticalListSortingStrategy}
					>
						{ options.map( ( { editUrl, id, order, title, url } ) => {
							return (
								<SortableItem
									id={'sortable-' + id}
									key={'sortable-' + id}
									value={id}
									label={title}
									url={url}
									editUrl={editUrl}
									handleRemoveClick={handleRemoveClick}
								/>
							)
						} ) }
					</SortableContext>

					<DragOverlay>
						{activeId ? <Item id={activeId} /> : null}
					</DragOverlay>
				</DndContext>
			</div>
		</div>
	)
}

export default SortableMultiSelect