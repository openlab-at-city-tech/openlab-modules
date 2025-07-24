import React, { useState } from 'react'

import he from 'he'

import { useCallback } from '@wordpress/element'

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

	const findSelectedOptionIndexById = (id) => {
		for ( const k in options ) {
			if ( id === options[k].id ) {
				return k
			}
		}

		return -1
	}

	const activeItem = activeId ? options[ findSelectedOptionIndexById( Number( activeId.substr( 9 ) ) ) ] : null

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
			const oldId = Number( active.id.substr( 9 ) )
			const newId = Number( over.id.substr( 9 ) )

			const oldIndex = findSelectedOptionIndexById( oldId )
			const newIndex = findSelectedOptionIndexById( newId )

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

	const handleRemoveClick = useCallback(
		(itemHandle) => {
			const realOptions = Array.from(options);
			const itemId = Number(itemHandle.substr(9));
			const itemIndex = realOptions.findIndex(opt => opt.id === itemId);

			if (itemIndex === -1) return;

			const before = realOptions.slice(0, itemIndex);
			const after = realOptions.slice(itemIndex + 1);

			const newSelectedOptions = [...before, ...after];
			onChange(newSelectedOptions);
		},
		[options, onChange]
	);

	const items = options.map( option => 'sortable-' + option.id )

	return (
		<div className="sortable-multi-select">
			<div className="sortable-multi-select-selected-items">
				<DndContext
					collisionDetection={closestCenter}
					onDragEnd={handleDragEnd}
					onDragStart={handleDragStart}
					sensors={sensors}
				>
					<SortableContext
						items={items}
						strategy={verticalListSortingStrategy}
					>
						{ options.map( ( { editUrl, id, title, url, status } ) => {
							return (
								<SortableItem
									id={'sortable-' + id}
									key={'sortable-' + id}
									value={id}
									label={he.decode(title)}
									url={url}
									postStatus={status}
									editUrl={editUrl}
									handleRemoveClick={handleRemoveClick}
								/>
							)
						} ) }
					</SortableContext>

					<DragOverlay>
						{activeItem ? (
							<SortableItem
								id={activeId}
								key={activeId}
								value={activeItem.id}
								label={activeItem.title}
								url={activeItem.url}
								editUrl={activeItem.editUrl}
							/>
						) : null}
					</DragOverlay>
				</DndContext>
			</div>
		</div>
	)
}

export default SortableMultiSelect
