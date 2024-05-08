import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Button, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import './editor.scss';

const SortableItem = ({ id, title, link, description }) => {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
    setActivatorNodeRef,
  } = useSortable({ id });

  const style = {
		display: 'flex',
		marginLeft: '0',
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <li ref={setNodeRef} style={style}>
      <Button
        className="drag-handle"
        ref={setActivatorNodeRef} // Bind the drag handle
        { ...listeners } // Apply the necessary event listeners
        icon="move" // Using a WordPress icon for the handle, ensure you have this or use a custom icon
        label={__('Drag', 'text-domain')} // Accessibility label for the drag handle
      >
        <span className="screen-reader-text">Drag</span>
      </Button>

      <div className="item-content">
        <h2><a href={link}>{title}</a></h2>
        <p className="module-description">{description}</p>
      </div>
    </li>
  );
};

/**
 * Edit function.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @param {boolean}  props.isSelected    Whether the block is currently selected.
 */
export default function Edit({ attributes, isSelected, setAttributes }) {
  const { orderedIds } = attributes;

  const { allModules } = useSelect((select) => {
    const rawModules = select('core').getEntityRecords(
      'postType',
      'openlab_module',
      {
        order: 'asc',
        orderby: 'title',
        per_page: 100,
        status: 'any'
      }
    );

    return {
      allModules: rawModules ? rawModules.filter(module => module.title.rendered.length > 0) : []
    };
  }, []);

	const orderModulesAccordingToId = () => {
		if ( ! orderedIds ) {
			return allModules;
		}

		const orderedModules = []
		orderedIds.forEach(id => {
			const module = allModules.find(foundModule => foundModule.id === id);
			if (module) {
				orderedModules.push(module);
			}
		});

		// Append any modules that were not in the ordered list.
		allModules.forEach(module => {
			if ( ! orderedIds.includes(module.id) ) {
				orderedModules.push(module);
			}
		});

		return orderedModules;
	}

  const orderedModules = orderModulesAccordingToId();

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor)
  );

  const handleDragEnd = (event) => {
    const { active, over } = event;

    if (active.id !== over.id) {
      const oldIndex = orderedModules.findIndex(module => module.id === active.id);
      const newIndex = orderedModules.findIndex(module => module.id === over.id);

			const orderedModulesIds = orderedModules.map(module => module.id);

      const newOrderedIds = arrayMove( orderedModulesIds, oldIndex, newIndex )

      setAttributes({ orderedIds: newOrderedIds });
    }
  };

  return (
    <div { ...useBlockProps() }>
      { allModules && allModules.length > 0 && (
        <ul className="openlab-modules-module-list">
					{ isSelected && (
						<DndContext
							sensors={sensors}
							collisionDetection={closestCenter}
							onDragEnd={handleDragEnd}
						>
							<SortableContext
								items={orderedModules.map(module => module.id)}
								strategy={verticalListSortingStrategy}
							>
								{ orderedModules.map((module) => (
									<SortableItem
										key={'module-' + module.id}
										id={module.id}
										title={module.title.rendered}
										link={module.link}
										description={module.meta.module_description}
									/>
								)) }
							</SortableContext>
						</DndContext>
					) }

					{ ! isSelected && (
						<>
							{ orderedModules.map((module) => (
								<li key={'module-' + module.id}>
									<h2><a href={module.link}>{module.title.rendered}</a></h2>
									<p className="module-description">{module.meta.module_description}</p>
								</li>
							)) }
						</>
					) }
        </ul>
      ) }

			{ allModules && allModules.length === 0 && (
        <p>{ __( 'This site has no modules to display.', 'openlab-modules' ) }</p>
      ) }

			{ ! allModules && (
        <Spinner />
      ) }
    </div>
  );
}
