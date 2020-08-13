<?php

namespace Drupal\entity_browser_table\Plugin\Field\FieldWidget;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\FieldWidgetDisplayInterface;
use Drupal\entity_browser\FieldWidgetDisplayManager;
use Drupal\entity_browser\Plugin\Field\FieldWidget\EntityReferenceBrowserWidget;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity_reference_browser_table_widget' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_browser_table_widget",
 *   label = @Translation("Entity Browser - Table"),
 *   multiple_values = TRUE,
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceBrowserTableWidget extends EntityReferenceBrowserWidget {

  protected $targetId;

  protected $currentLanguage;

  protected $entityBundleInfo;

  protected $entities;

  protected static $deleteDepth = 4;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.entity_browser.field_widget_display'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('language_manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  public function __construct(
    $plugin_id,
    array $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
    FieldWidgetDisplayManager $field_display_manager,
    ModuleHandlerInterface $module_handler,
    AccountInterface $current_user,
    MessengerInterface $messenger,
    LanguageManagerInterface $languageManager,
    EntityTypeBundleInfo $bundleInfo) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings,
      $entity_type_manager,
      $field_display_manager,
      $module_handler,
      $current_user,
      $messenger
    );
    $this->currentLanguage = $languageManager->getCurrentLanguage()->getId();
    $this->entityBundleInfo = $bundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    return $form;
  }

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#attributes']['class'] = [
      'field--widget-entity_reference_browser_table_widget',
    ];
    $element['#attached']['library'][] = 'entity_browser_table/entity_browser_table';

    return $element;
  }

  /**
   * Builds the render array for displaying the current results as a table.
   *
   * @param string $details_id
   *   The ID for the details element.
   * @param string[] $field_parents
   *   Field parents.
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   Array of referenced entities.
   *
   * @return array
   *   The render array for the current selection.
   */
  protected function displayCurrentSelection($details_id, array $field_parents, array $entities) {
    try {
      $table = [
        '#type' => 'table',
        '#header' => $this->buildTableHeaders(),
        '#attributes' => ['class' => ['table--widget-' . $this->getPluginId()]],
        '#empty' => $this->t('Use the buttons above to add content to this area.'),
      ];
      return array_merge($table, $this->buildTableRows($entities, $details_id, $field_parents));
    } catch (PluginException $exception) {
      \Drupal::logger('Entity Browser - Table Display')
        ->error(t(
          'Could not get the field widget display: @message',
          ['@message' => $exception->getMessage()]
        ));

      return $table = [
        '#type' => 'table',
        '#header' => [''],
        '#rows' => [
          [
            $this->t('The field widget could not be found. See the logs for details'),
          ],
        ],
      ];
    }

  }

  public function buildTableHeaders() {
    return array_filter(
      [
        '',
        $this->getFirstColumnHeader(),
        $this->getActionColumnHeader(),
      ],
      function ($header) {
        return $header !== NULL;
      }
    );
  }

  public function buildTableRows(array $entities, $details_id, $field_parents) {

    $field_widget_display = $this->fieldDisplayManager->createInstance(
      $this->getSetting('field_widget_display'),
      $this->getSetting('field_widget_display_settings') + [
        'entity_type' => $this->fieldDefinition->getFieldStorageDefinition()
          ->getSetting('target_type'),
      ]
    );


    // The "Replace" button will only be shown if this setting is enabled in
    // the widget, and there is only one entity in the current selection.
    $replace_button_access = $this->getSetting('field_widget_replace') && (count($entities) === 1);

    $entities = array_filter($entities, function ($entity) {
      return $entity instanceof EntityInterface;
    });
    $rowData = [];
    foreach ($entities as $row_id => $entity) {
      if ($entity->hasTranslation($this->currentLanguage) == TRUE) {
        $entity = $entity->getTranslation($this->currentLanguage);
      }

      $rowData[] = array_filter([
        'handle' => $this->buildSortableHandle(),
        'title-preview' => $this->getFirstColumn($entity),
        'actions' => [
          'edit_button' => $this->buildEditButton($entity, $details_id, $row_id, $field_parents),
          'replace_button' => $this->buildReplaceButton($entity, $entities, $details_id, $row_id, $field_parents),
          'remove_button' => $this->buildRemoveButton($entity, $details_id, $row_id, $field_parents),
        ],
        '#attributes' => [
          'class' => [
            'item-container',
            Html::getClass($field_widget_display->getPluginId()),
          ],
          'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
          'data-row-id' => $row_id,
        ],

      ]);
    }

    return $rowData;
  }

  public function buildSortableHandle() {
    return [
      '#type' => 'markup',
      '#markup' => '<span class="handle">'
    ];
  }

  public function buildEditButton(EntityInterface $entity, $details_id, $row_id, $field_parents) {

    return [
      '#type' => 'submit',
      '#value' => $this->t('Edit'),
      '#name' => $this->fieldDefinition->getName() . '_edit_button_' . $entity->id() . '_' . $row_id . '_' . md5(json_encode($field_parents)),
      '#ajax' => [
        'url' => Url::fromRoute(
          'entity_browser.edit_form', [
            'entity_type' => $entity->getEntityTypeId(),
            'entity' => $entity->id(),
          ]
        ),
        'options' => [
          'query' => [
            'details_id' => $details_id,
          ],
        ],
      ],
      '#attributes' => [
        'class' => ['edit-button'],
      ],
      '#access' => $this->getEditButtonAccess($entity),
    ];
  }

  public function buildRemoveButton(EntityInterface $entity, $details_id, $row_id, array $field_parents) {
    return [
      '#type' => 'submit',
      '#value' => $this->t('Remove'),
      '#ajax' => [
        'callback' => [get_class($this), 'updateWidgetCallback'],
        'wrapper' => $details_id,
      ],
      '#submit' => [[get_class($this), 'removeItemSubmit']],
      '#name' => $this->fieldDefinition->getName() . '_remove_' . $entity->id() . '_' . $row_id . '_' . md5(json_encode($field_parents)),
      '#limit_validation_errors' => [array_merge($field_parents, [$this->fieldDefinition->getName()])],
      '#attributes' => [
        'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
        'data-row-id' => $row_id,
        'class' => ['remove-button'],
      ],
      '#access' => (bool) $this->getSetting('field_widget_remove'),
    ];
  }

  public function buildReplaceButton(EntityInterface $entity, array $entities, $details_id, $row_id, array $field_parents) {
    return [
      '#type' => 'submit',
      '#value' => $this->t('Replace'),
      '#ajax' => [
        'callback' => [get_class($this), 'updateWidgetCallback'],
        'wrapper' => $details_id,
      ],
      '#submit' => [[get_class($this), 'removeItemSubmit']],
      '#name' => $this->fieldDefinition->getName() . '_replace_' . $entity->id() . '_' . $row_id . '_' . md5(json_encode($field_parents)),
      '#limit_validation_errors' => [array_merge($field_parents, [$this->fieldDefinition->getName()])],
      '#attributes' => [
        'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
        'data-row-id' => $row_id,
        'class' => ['replace-button'],
      ],
      '#access' => $this->getReplaceButtonAccess($entities),
    ];
  }

  public function getEditButtonAccess(EntityInterface $entity) {
    $edit_button_access = $this->getSetting('field_widget_edit') && $entity->access('update', $this->currentUser);
    if ($entity->getEntityTypeId() == 'file') {
      // On file entities, the "edit" button shouldn't be visible unless
      // the module "file_entity" is present, which will allow them to be
      // edited on their own form.
      $edit_button_access &= $this->moduleHandler->moduleExists('file_entity');
    }
    return $edit_button_access;
  }

  public function getReplaceButtonAccess($entities) {
    return $this->getSetting('field_widget_replace') && (count($entities) === 1);
  }

  /**
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getFirstColumn(EntityInterface $entity): array {
    $value = $this->getFieldWidgetDisplay()->view($entity);
    if (is_string($value)) {
      if ($value == ' ') {
        $value = $this->t('<i>No title set</i>');
      }
      $value = ['#markup' => $value];
    }
    return $value;
  }

  public function getFirstColumnHeader() {
    return $this->getSetting('field_widget_display') == 'rendered_entity'
      ? $this->t('Thumbnail')
      : $this->t('Title');
  }

  public function getActionColumnHeader() {
    return $this->getSetting('field_widget_edit') || $this->getSetting('field_widget_remove') || $this->getSetting('field_widget_replace')
      ? $this->t('Action')
      : NULL;
  }

  /**
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getFieldWidgetDisplay() {
    return $this->fieldDisplayManager->createInstance(
      $this->getSetting('field_widget_display'),
      $this->getSetting('field_widget_display_settings') + [
        'entity_type' => $this->fieldDefinition->getFieldStorageDefinition()
          ->getSetting('target_type'),
      ]
    );
  }

}
