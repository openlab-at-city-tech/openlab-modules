import { __ } from '@wordpress/i18n';

import {
	PanelColorSettings,
	InspectorControls,
	useBlockProps
} from '@wordpress/block-editor';

import {
	Button,
	CheckboxControl,
	ColorPicker,
	Panel,
	PanelBody,
	PanelRow,
	Spinner
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import './editor.scss';

const SortableItem = ({ id, title, link, authorName, description, image }) => {
  const {
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
    setActivatorNodeRef,
  } = useSortable({ id });

  const style = {
		alignItems: 'center',
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

			<div className="draggable-content">
				<ModuleListItem
					id={id}
					title={title}
					link={link}
					authorName={authorName}
					description={description}
					image={image}
				/>
			</div>
    </li>
  );
};


const CARD_COLOR_CLASS_PREFIX = 'has-';
const CARD_COLOR_CLASS_SUFFIX = '-card-background-color';

const getCardBackgroundClass = (slug) =>
	slug ? `has-card-background-color ${CARD_COLOR_CLASS_PREFIX}${slug}${CARD_COLOR_CLASS_SUFFIX}` : '';

const ModuleListItem = ({ id, title, link, authorName, description, image }) => {
	const showAuthor      = null !== authorName;
	const showDescription = null !== description;
	const showImage       = null !== image;

	return (
		<div key={'module-' + id} className="module-list-item">
			{ showImage && (
				<div className="module-list-item-image">
					<div className="image-ratio-box">
						{image && (
							<img src={image} alt={title} />
						)}

						{!image && (
							<span>&nbsp;</span>
						)}
					</div>
				</div>
			)}

			<div className="module-list-item-info">
				<h2><a href={link}>{title}</a></h2>

				{showAuthor && (
					<p className="module-list-item-author">{authorName}</p>
				)}

				{showDescription && (
					<p className="module-list-item-description">{description}</p>
				)}
			</div>
		</div>
	)
}

/**
 * Edit function.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @param {boolean}  props.isSelected    Whether the block is currently selected.
 */
export default function Edit({ attributes, isSelected, setAttributes }) {
  const {
		cardBackgroundColor,
		orderedIds,
		showModuleAuthor,
		showModuleDescription,
		showModuleImage
	} = attributes;

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
      allModules: rawModules ? rawModules.filter(module => module.title.rendered.length > 0) : null
    };
  }, []);

	const orderModulesAccordingToId = () => {
		if ( null === allModules ) {
			return []
		}

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

	const blockProps = useBlockProps({
		className: getCardBackgroundClass(cardBackgroundColor),
		style: {
			'--card-background-color': cardBackgroundColor || 'transparent',
		}
	})

  return (
		<>
			<InspectorControls>
				<PanelColorSettings
					title="Image List/Grid Color"
					colorSettings={[
						{
							value: attributes.cardBackgroundColor,
							onChange: (color) => setAttributes({ cardBackgroundColor: color }),
							label: 'Background Color',
						},
					]}
				/>
			</InspectorControls>

			<InspectorControls>

				<Panel>
					<PanelBody title={ __( 'Layout Settings', 'openlab-modules' ) }>
						<PanelRow>
							<CheckboxControl
								label={ __( 'Module Description', 'openlab-modules' ) }
								help={ __( 'Include each Module\'s Description in the list. This can be edited in the Module Settings', 'openlab-modules' ) }
								checked={ showModuleDescription }
								onChange={ (value) => setAttributes({ showModuleDescription: value }) }
							/>
						</PanelRow>

						<PanelRow>
							<CheckboxControl
								label={ __( 'Module Author', 'openlab-modules' ) }
								help={ __( 'Include each Module\'s Author in the list.', 'openlab-modules' ) }
								checked={ showModuleAuthor }
								onChange={ (value) => setAttributes({ showModuleAuthor: value }) }
							/>
						</PanelRow>

						<PanelRow>
							<CheckboxControl
								label={ __( 'Module Featured Image', 'openlab-modules' ) }
								help={ __( 'Include each Module\'s featured image in the list. This can be edited in the Module Settings', 'openlab-modules' ) }
								checked={ showModuleImage }
								onChange={ (value) => setAttributes({ showModuleImage: value }) }
							/>
						</PanelRow>
					</PanelBody>
				</Panel>
			</InspectorControls>

			<div { ...blockProps }>
				{ null !== allModules && allModules.length > 0 && (
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
									{ orderedModules.map((module) => {
										return (
											<SortableItem
												key={'module-' + module.id}
												id={module.id}
												title={module.title.rendered}
												link={module.link}
												authorName={showModuleAuthor ? module.authorName : null}
												description={showModuleDescription ? module.meta.module_description : null}
												image={showModuleImage ? module.featuredImage : null}
											/>
										)
									}) }
								</SortableContext>
							</DndContext>
						) }

						{ ! isSelected && (
							<>
								{ orderedModules.map((module) => (
									<ModuleListItem
										key={'module-' + module.id}
										id={module.id}
										title={module.title.rendered}
										link={module.link}
										authorName={showModuleAuthor ? module.authorName : null}
										description={showModuleDescription ? module.meta.module_description : null}
										image={showModuleImage ? module.featuredImage : null}
									/>
								)) }
							</>
						) }
					</ul>
				) }

				{ null !== allModules && allModules.length === 0 && (
					<p>{ __( 'This site has no modules to display.', 'openlab-modules' ) }</p>
				) }

				{ null === allModules && (
					<Spinner />
				) }
			</div>
		</>
  );
}
