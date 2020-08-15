<?php

namespace Drupal\entity_browser\Plugin\Field\FieldWidget\ListBuilder;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_browser\Plugin\Field\FieldWidget\EntityReferenceBrowserWidget;
use Drupal\Core\Url;
use Drupal\entity_browser\FieldWidgetDisplayInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a listing of selected entities.
 *
 * @ingroup entity_browser
 */
class TableListBuilder extends EntityListBuilder {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * Field item list of entities to display.
   *
   * Stores data such as alt and title text not available on the entity.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected $items;

  /**
   * An array of entities to display.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities;

  /**
   * The FieldWidgetDisplay plugin.
   *
   * @var \Drupal\entity_browser\FieldWidgetDisplayInterface
   */
  protected $display;

  /**
   * The current delta.
   *
   * @var int
   */
  protected $delta;

  /**
   * The ID of the current details DOM element.
   *
   * @var string
   */
  protected $ajaxWrapper;

  /**
   * The EntityReferenceBrowserWidget settings.
   *
   * @var array
   */
  protected $widgetSettings;

  /**
   * The field parents array.
   *
   * @var array
   */
  protected $fieldParents;

  /**
   * Field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * The current field machine name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The current field cardinality.
   *
   * @var int
   */
  protected $cardinality;

  /**
   * The field type.
   *
   * @var string
   */
  protected $fieldType;

  /**
   * Edit button access.
   *
   * @var bool
   */
  protected $editButtonAccess;

  /**
   * Replace button access.
   *
   * @var bool
   */
  protected $replaceButtonAccess;

  /**
   * The target entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The current field item list entity.
   * @param array $entities
   *   An array of entities to display.
   * @param \Drupal\entity_browser\FieldWidgetDisplayInterface $display
   *   A field Widget display plugin.
   * @param string $wrapper
   *   The ajax wrapper.
   * @param array $settings
   *   The EntityReferenceBrowserWidget settings.
   * @param array $parents
   *   The element's field parents array.
   * @param \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition interface.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, FieldItemListInterface $items, array $entities, FieldWidgetDisplayInterface $display = NULL, $wrapper, array $settings, array $parents, FieldDefinitionInterface $field_definition) {
    $this->storage = $storage;
    $this->entityType = $entity_type;
    $this->items = $items;
    $this->entities = $entities;
    $this->display = $display;
    $this->ajaxWrapper = $wrapper;
    $this->widgetSettings = $settings;
    $this->fieldParents = $parents;
    $this->fieldDefinition = $field_definition;
    $this->fieldName = $this->fieldDefinition->getName();
    $this->cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $this->fieldType = $this->fieldDefinition->getType();

    // The "Replace" button will only be shown if this setting is enabled in the
    // widget, and there is only one entity in the current selection.
    $this->replaceButtonAccess = $this->widgetSettings['field_widget_replace'] && (count($this->entities) === 1);

    $this->editButtonAccess = $this->widgetSettings['field_widget_edit'] && $this->entityTypeHasForm($entity_type);

  }

  /**
   * Returns True if Target Entity Type has an Edit Form class.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *
   * @return bool
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function entityTypeHasForm(EntityTypeInterface $entityType) {
    if ($entityType->getFormClass('edit') || $entityType->getFormClass('default')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];

    $header['tabledrag'] = [
      'class' => ['entity-browser--field-widget--tabledrag'],
    ];

    $header['display'] = $this->display
      ->getColumnHeader($this->entityType);

    if (!empty($this->widgetSettings['table_settings']['label_column']) && $this->display->getPluginId() != 'label') {
      // Load the label for the entity 'label' field.
      $label_key = $this->entityType->getKey('label');
      $label = $this->storage
        ->getFieldStorageDefinitions()[$label_key]
        ->getLabel();
      $header['label'] = $label;
    }
    elseif ($this->display->getPluginId() == 'label') {
      // Load the label for the entity 'label' field.
      $label_key = $this->entityType->getKey('label');
      $label = $this->storage
        ->getFieldStorageDefinitions()[$label_key]
        ->getLabel();
      $header['display'] = $label;
    }

    if (!empty($this->widgetSettings['table_settings']['status_column']) && $this->entityType->hasKey('published')) {
      $header['publication_status'] = $this->storage
        ->getFieldStorageDefinitions()[$this->entityType->getKey('published')]
        ->getLabel();
    }

    if (!empty($this->widgetSettings['table_settings']['bundle_column']) && $this->entityType->get('bundle_entity_type')) {
      $header['bundle'] = $this->entityType->getBundleLabel();
    }

    $header['_weight'] = [
      'data' => $this->t('Weight'),
      'class' => ['tabledrag-hide'],
    ];
    $header['operations'] = $this->t('Operations');
    return $header;
  }

  /**
   * Get entity's publication status as renderable markup.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The entity's publication status.
   */
  protected function getPublicationStatus(EntityInterface $entity) {

    $published = $entity->get($this->entityType->getKey('published'));

    $label = $this->storage
      ->getFieldStorageDefinitions()[$this->entityType->getKey('published')]
      ->getLabel();

    if ((string) $label === (string) $this->t('Published')) {
      $output = $published ? $this->t('Published') : $this->t('Unpublished');
    }
    else {
      $output = $published ? $this->t('True') : $this->t('False');
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {

    $hash = md5(json_encode($this->fieldParents));
    $data_entity_id = $entity->getEntityTypeId() . ':' . $entity->id();
    $limit_validation_errors = [array_merge($this->fieldParents, [$this->fieldName])];

    $row = [
      '#attributes' => [
        'class' => [
          'item-container',
          'draggable',
        ],
        'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
        'data-row-id' => $this->delta,
      ],
    ];

    $row['#weight'] = $this->delta;

    $row['tabledrag'] = [
      '#wrapper_attributes' => ['class' => ['entity-browser--field-widget--tabledrag']],
    ];

    $row['display'] = $this->display->view($entity);
    $row['display']['#wrapper_attributes'] = [
      'class' => ['entity-browser--field-widget--display'],
    ];

    if (!empty($this->widgetSettings['table_settings']['label_column']) && $this->display->getPluginId() != 'label') {
      $row['label'] = ['#markup' => $entity->label()];
    }

    if (!empty($this->widgetSettings['table_settings']['status_column']) && $this->entityType->hasKey('published')) {

      $row['publication_status'] = [
        '#markup' => $this->getPublicationStatus($entity),
        '#wrapper_attributes' => ['class' => ['entity-browser--field-widget--publication-status']],
      ];
    }

    if (!empty($this->widgetSettings['table_settings']['bundle_column']) && $this->entityType->get('bundle_entity_type')) {
      $bundle_label = \Drupal::entityTypeManager()
        ->getStorage($this->entityType->get('bundle_entity_type'))
        ->load($entity->bundle())
        ->label();

      $row['bundle'] = [
        '#markup' => $bundle_label,
        '#wrapper_attributes' => ['class' => ['entity-browser--field-widget--bundle']],
      ];
    }

    $row['_weight'] = [
      '#type' => 'weight',
      '#title' => t('Weight for @title', ['@title' => $entity->label()]),
      '#title_display' => 'invisible',
      '#default_value' => $this->delta,
      '#attributes' => ['class' => ['weight']],
      '#delta' => $this->delta,
      '#wrapper_attributes' => ['class' => ['entity-browser--field-widget--weight']],
    ];

    $row['operations'] = [
      '#wrapper_attributes' => ['class' => ['entity-browser--field-widget--operations']],
    ];

    $row['operations']['remove_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove'),
      '#name' => $this->fieldName . '_remove_' . $entity->id() . '_' . $this->delta . '_' . $hash,
      '#attributes' => [
        'data-entity-id' => $data_entity_id,
        'data-row-id' => $this->delta,
        'class' => ['remove-button'],
      ],
      '#access' => (bool) $this->widgetSettings['field_widget_remove'],
    ];

    $row['operations']['replace_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Replace'),
      '#ajax' => [
        'callback' => [EntityReferenceBrowserWidget::class, 'updateWidgetCallback'],
        'wrapper' => $this->ajaxWrapper,
      ],
      '#submit' => [[EntityReferenceBrowserWidget::class, 'removeItemSubmit']],
      '#name' => $this->fieldName . '_replace_' . $entity->id() . '_' . $this->delta . '_' . $hash,
      '#limit_validation_errors' => $limit_validation_errors,
      '#attributes' => [
        'data-entity-id' => $data_entity_id,
        'data-row-id' => $this->delta,
        'class' => ['replace-button'],
      ],
      '#access' => $this->replaceButtonAccess,
    ];

    $row['operations']['edit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit'),
      '#ajax' => [
        'url' => Url::fromRoute(
          'entity_browser.edit_form', [
            'entity_type' => $entity->getEntityTypeId(),
            'entity' => $entity->id(),
          ]
        ),
        'options' => [
          'query' => [
            'details_id' => $this->ajaxWrapper,
          ],
        ],
      ],
      '#attributes' => [
        'class' => ['edit-button'],
      ],
      '#access' => $this->editButtonAccess && $entity->access('update'),
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   *
   * Builds the entity listing as renderable array for table.html.twig.
   */
  public function render() {

    $build = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => [],
      '#attributes' => [
        'class' => ['entities-list'],
        'data-entity-browser-entities-list' => 1,
      ],
      '#empty' => $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
      '#attached' => ['library' => ['entity_browser/entity_list']],
    ];

    $this->delta = 0;
    foreach ($this->entities as $entity) {
      $row = $this->buildRow($entity);
      $build[$this->delta] = $row;
      $this->delta++;
    }

    return $build;
  }

}
