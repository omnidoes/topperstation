<?php

namespace Drupal\entity_browser\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\entity_browser\Entity\EntityBrowser;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint;
use Drupal\entity_browser\FieldWidgetDisplayManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Plugin implementation of the 'entity_reference' widget for entity browser.
 *
 * @FieldWidget(
 *   id = "entity_browser_entity_reference",
 *   label = @Translation("Entity browser"),
 *   description = @Translation("Uses entity browser to select entities."),
 *   multiple_values = TRUE,
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceBrowserWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Field widget display plugin manager.
   *
   * @var \Drupal\entity_browser\FieldWidgetDisplayManager
   */
  protected $fieldDisplayManager;

  /**
   * The depth of the delete button.
   *
   * This property exists so it can be changed if subclasses.
   *
   * @var int
   */
  protected static $deleteDepth = 4;

  /**
   * The module handler interface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * If triggering element was hidden target_id element.
   *
   * @var bool
   */
  protected $entityBrowserValueUpdated;

  /**
   * Constructs widget plugin.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\entity_browser\FieldWidgetDisplayManager $field_display_manager
   *   Field widget display plugin manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, FieldWidgetDisplayManager $field_display_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, MessengerInterface $messenger) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldDisplayManager = $field_display_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
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
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'entity_browser' => NULL,
      'open' => FALSE,
      'field_widget_display' => 'label',
      'field_widget_edit' => TRUE,
      'field_widget_remove' => TRUE,
      'field_widget_replace' => FALSE,
      'field_widget_display_settings' => [],
      'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $browsers = [];
    /** @var \Drupal\entity_browser\EntityBrowserInterface $browser */
    foreach ($this->entityTypeManager->getStorage('entity_browser')->loadMultiple() as $browser) {
      $browsers[$browser->id()] = $browser->label();
    }

    $element['entity_browser'] = [
      '#title' => $this->t('Entity browser'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('entity_browser'),
      '#options' => $browsers,
    ];

    $target_type = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type');
    $entity_type = $this->entityTypeManager->getStorage($target_type)->getEntityType();

    $displays = [];
    foreach ($this->fieldDisplayManager->getDefinitions() as $id => $definition) {
      if ($this->fieldDisplayManager->createInstance($id)->isApplicable($entity_type)) {
        $displays[$id] = $definition['label'];
      }
    }

    $id = Html::getId($this->fieldDefinition->getName()) . '-field-widget-display-settings-ajax-wrapper-' . md5($this->fieldDefinition->getUniqueIdentifier());
    $element['field_widget_display'] = [
      '#title' => $this->t('Entity display plugin'),
      '#type' => 'radios',
      '#default_value' => $this->getSetting('field_widget_display'),
      '#options' => $displays,
      '#ajax' => [
        'callback' => [get_class($this), 'updateFieldWidgetDisplaySettings'],
        'wrapper' => $id,
      ],
      '#limit_validation_errors' => [],
    ];

    if ($this->getSetting('field_widget_display')) {
      $element['field_widget_display_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Entity display plugin configuration'),
        '#open' => TRUE,
        '#prefix' => '<div id="' . $id . '">',
        '#suffix' => '</div>',
        '#tree' => TRUE,
      ];

      $element['field_widget_display_settings'] += $this->fieldDisplayManager
        ->createInstance(
          $form_state->getValue(
            [
              'fields',
              $this->fieldDefinition->getName(),
              'settings_edit_form',
              'settings',
              'field_widget_display',
            ],
            $this->getSetting('field_widget_display')
          ),
          $form_state->getValue(
            [
              'fields',
              $this->fieldDefinition->getName(),
              'settings_edit_form',
              'settings',
              'field_widget_display_settings',
            ],
            $this->getSetting('field_widget_display_settings')
          ) + [
            'entity_type' => $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type'),
          ]
        )
        ->settingsForm($form, $form_state);
    }

    $edit_button_access = TRUE;
    if ($entity_type->id() == 'file') {
      // For entities of type "file", it only makes sense to have the edit
      // button if the module "file_entity" is present.
      $edit_button_access = $this->moduleHandler->moduleExists('file_entity');
    }
    $element['field_widget_edit'] = [
      '#title' => $this->t('Display Edit button'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('field_widget_edit'),
      '#access' => $edit_button_access,
    ];

    $element['field_widget_remove'] = [
      '#title' => $this->t('Display Remove button'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('field_widget_remove'),
    ];

    $element['field_widget_replace'] = [
      '#title' => $this->t('Display Replace button'),
      '#description' => $this->t('This button will only be displayed if there is a single entity in the current selection.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('field_widget_replace'),
    ];

    $element['open'] = [
      '#title' => $this->t('Show widget details as open by default'),
      '#description' => $this->t('If marked, the fieldset container that wraps the browser on the entity form will be loaded initially expanded.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('open'),
    ];

    $element['selection_mode'] = [
      '#title' => $this->t('Selection mode'),
      '#description' => $this->t('Determines how selection in entity browser will be handled. Will selection be appended/prepended or it will be replaced in case of editing.'),
      '#type' => 'select',
      '#options' => EntityBrowserElement::getSelectionModeOptions(),
      '#default_value' => $this->getSetting('selection_mode'),
    ];

    $element['#element_validate'] = [[get_class($this), 'validateSettingsForm']];

    return $element;
  }

  /**
   * Validate the settings form.
   */
  public static function validateSettingsForm($element, FormStateInterface $form_state, $form) {

    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    if ($values['selection_mode'] == 'selection_edit') {
      /** @var \Drupal\entity_browser\Entity\EntityBrowser $entity_browser */
      $entity_browser = EntityBrowser::load($values['entity_browser']);
      if ($entity_browser->getSelectionDisplay()->supportsPreselection() === FALSE) {
        $tparams = [
          '%selection_mode' => EntityBrowserElement::getSelectionModeOptions()[EntityBrowserElement::SELECTION_MODE_EDIT],
          '@browser_link' => $entity_browser->toLink($entity_browser->label(), 'edit-form')->toString(),
        ];
        $form_state->setError($element['entity_browser']);
        $form_state->setError($element['selection_mode'], t('The selection mode %selection_mode requires an entity browser with a selection display plugin that supports preselection.  Either change the selection mode or update the @browser_link entity browser to use a selection display plugin that supports preselection.', $tparams));
      }
    }
  }

  /**
   * Ajax callback that updates field widget display settings fieldset.
   */
  public static function updateFieldWidgetDisplaySettings(array $form, FormStateInterface $form_state) {
    $array_parents = $form_state->getTriggeringElement()['#array_parents'];
    $up_two_levels = array_slice($array_parents, 0, count($array_parents) - 2);
    $settings_path = array_merge($up_two_levels, ['field_widget_display_settings']);
    $settingsElement = NestedArray::getValue($form, $settings_path);
    return $settingsElement;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = $this->summaryBase();
    $field_widget_display = $this->getSetting('field_widget_display');

    if (!empty($field_widget_display)) {
      $pluginDefinition = $this->fieldDisplayManager->getDefinition($field_widget_display);
      $field_widget_display_settings = $this->getSetting('field_widget_display_settings');
      $field_widget_display_settings += [
        'entity_type' => $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type'),
      ];
      $plugin = $this->fieldDisplayManager->createInstance($field_widget_display, $field_widget_display_settings);
      $summary[] = $this->t('Entity display: @name', ['@name' => $pluginDefinition['label']]);
      if ($field_widget_display == 'rendered_entity') {
        $view_mode_label = $plugin->getViewModeLabel();
        $summary[] = $this->t('View Mode: @view_mode', ['@view_mode' => $view_mode_label]);
      }
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function flagErrors(FieldItemListInterface $items, ConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    if ($violations->count() > 0) {
      /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
      foreach ($violations as $offset => $violation) {
        // The value of the required field is checked through the "not null"
        // constraint, whose message is not very useful. We override it here for
        // better UX.
        if ($violation->getConstraint() instanceof NotNullConstraint) {
          $violations->set($offset, new ConstraintViolation(
            $this->t('@name field is required.', ['@name' => $items->getFieldDefinition()->getLabel()]),
            '',
            [],
            $violation->getRoot(),
            $violation->getPropertyPath(),
            $violation->getInvalidValue(),
            $violation->getPlural(),
            $violation->getCode(),
            $violation->getConstraint(),
            $violation->getCause()
          ));
        }
      }
    }

    parent::flagErrors($items, $violations, $form, $form_state);
  }

  /**
   * Returns a key used to store the previously loaded entity.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   *
   * @return string
   *   A key for form state storage.
   */
  protected function getFormStateKey(FieldItemListInterface $items) {
    return $items->getEntity()->uuid() . ':' . $items->getFieldDefinition()->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $entities = $this->formElementEntities($items, $element, $form_state);
    $items->setValue($entities);

    $hidden_id = Html::getUniqueId('edit-' . $this->fieldDefinition->getName() . '-target-id');
    $details_id = Html::getUniqueId('edit-' . $this->fieldDefinition->getName());

    // Get configuration required to check entity browser availability.
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $selection_mode = $this->getSetting('selection_mode');

    $display_entity_browser = EntityBrowserElement::isEntityBrowserAvailable($selection_mode, $cardinality, count($entities));

    $element += [
      '#id' => $details_id,
      '#type' => 'details',
      '#open' => !empty($entities) || $this->getSetting('open') || $this->entityBrowserValueUpdated,
      '#required' => $this->fieldDefinition->isRequired(),
      // We are not using Entity browser's hidden element since we maintain
      // selected entities in it during entire process.
      'target_id' => [
        '#type' => 'hidden',
        '#id' => $hidden_id,
        // We need to repeat ID here as it is otherwise skipped when rendering.
        '#attributes' => [
          'id' => $hidden_id,
          'data-cardinality' => $cardinality,
          'data-entity-browser-visible' => $display_entity_browser,
        ],
        '#default_value' => implode(' ', array_map(
            function (EntityInterface $item) {
              return $item->getEntityTypeId() . ':' . $item->id();
            },
            $entities
        )),
        // #ajax is officially not supported for hidden elements but if we
        // specify event manually it works.
        '#ajax' => [
          'callback' => [get_class($this), 'updateWidgetCallback'],
          'wrapper' => $details_id,
          'event' => 'entity_browser_value_updated',
        ],
        '#submit' => [[get_class($this), 'entityBrowserValueUpdated']],
        '#limit_validation_errors' => [array_merge($element['#field_parents'], [$this->fieldDefinition->getName()])],
        '#executes_submit_callback' => TRUE,
      ],
    ];

    // Enable entity browser if requirements for that are fulfilled.
    if ($display_entity_browser) {
      $persistentData = $this->getPersistentData();

      $element['entity_browser'] = [
        '#type' => 'entity_browser',
        '#entity_browser' => $this->getSetting('entity_browser'),
        '#cardinality' => $cardinality,
        '#selection_mode' => $selection_mode,
        '#default_value' => $entities,
        '#entity_browser_validators' => $persistentData['validators'],
        '#widget_context' => $persistentData['widget_context'],
        '#custom_hidden_id' => $hidden_id,
        '#process' => [
          ['\Drupal\entity_browser\Element\EntityBrowserElement', 'processEntityBrowser'],
          [get_called_class(), 'processEntityBrowser'],
        ],
      ];

      $element['target_id']['#attributes']['data-entity-browser-available'] = 1;
    }
    else {
      // Allow non-ajax remove button to trigger ajax refresh when
      // cardinality.
      $element['target_id']['#attributes']['data-entity-browser-visible'] = 0;
    }

    $element['#attached']['library'][] = 'entity_browser/entity_reference';

    $field_parents = $element['#field_parents'];

    $element['current'] = $this->displayCurrentSelection($details_id, $field_parents, $entities);

    return $element;
  }

  /**
   * Render API callback: Processes the entity browser element.
   */
  public static function processEntityBrowser(&$element, FormStateInterface $form_state, &$complete_form) {
    $uuid = key($element['#attached']['drupalSettings']['entity_browser']);
    $element['#attached']['drupalSettings']['entity_browser'][$uuid]['selector'] = '#' . $element['#custom_hidden_id'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $entities = empty($values['target_id']) ? [] : explode(' ', trim($values['target_id']));
    $return = [];
    foreach ($entities as $entity) {
      $return[]['target_id'] = explode(':', $entity)[1];
    }

    return $return;
  }

  /**
   * AJAX form callback.
   */
  public static function updateWidgetCallback(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $reopen_browser = FALSE;
    // AJAX requests can be triggered by hidden "target_id" element when
    // entities are added or by one of the "Remove" buttons. Depending on that
    // we need to figure out where root of the widget is in the form structure
    // and use this information to return correct part of the form.
    $parents = [];
    if (!empty($trigger['#ajax']['event']) && $trigger['#ajax']['event'] == 'entity_browser_value_updated') {
      $parents = array_slice($trigger['#array_parents'], 0, -1);
    }
    elseif ($trigger['#type'] == 'submit' && strpos($trigger['#name'], '_replace_')) {
      $parents = array_slice($trigger['#array_parents'], 0, -static::$deleteDepth);
      // We need to re-open the browser. Instead of just passing "TRUE", send
      // to the JS the unique part of the button's name that needs to be clicked
      // on to relaunch the browser.
      $reopen_browser = implode("-", array_slice($trigger['#parents'], 0, -static::$deleteDepth));
    }

    $parents = NestedArray::getValue($form, $parents);
    $parents['#attached']['drupalSettings']['entity_browser_reopen_browser'] = $reopen_browser;
    return $parents;
  }

  /**
   * Submit callback for remove buttons.
   *
   * @deprecated Handled in javascript.  See entity_browser.entity_reference.js.
   */
  public static function removeItemSubmit(&$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element['#attributes']['data-entity-id']) && isset($triggering_element['#attributes']['data-row-id'])) {
      $id = $triggering_element['#attributes']['data-entity-id'];
      $row_id = $triggering_element['#attributes']['data-row-id'];
      $parents = array_slice($triggering_element['#parents'], 0, -static::$deleteDepth);
      $array_parents = array_slice($triggering_element['#array_parents'], 0, -static::$deleteDepth);

      // Find and remove correct entity.
      $values = explode(' ', $form_state->getValue(array_merge($parents, ['target_id'])));
      foreach ($values as $index => $item) {
        // @todo add weight field.
        if ($item == $id) {
          array_splice($values, $index, 1);
          break;
        }
      }
      $target_id_value = implode(' ', $values);

      // Set new value for this widget.
      $target_id_element = &NestedArray::getValue($form, array_merge($array_parents, ['target_id']));
      $form_state->setValueForElement($target_id_element, $target_id_value);
      NestedArray::setValue($form_state->getUserInput(), $target_id_element['#parents'], $target_id_value);

      // Rebuild form.
      $form_state->setRebuild();
    }
  }

  /**
   * Submit callback.
   */
  public static function entityBrowserValueUpdated(&$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#parents'], 0, -1);
    $array_parents = array_slice($triggering_element['#array_parents'], 0, -1);

    $values = $form_state->getValue($parents);

    $entities = empty($values['target_id']) ? [] : explode(' ', trim($values['target_id']));
    $values = [];
    foreach ($entities as $entity) {
      $values[]['target_id'] = explode(':', $entity)[1];
    }

    // Set new value for this widget in the form_state.
    $element = &NestedArray::getValue($form, $array_parents);
    $form_state->setValueForElement($element, $values);

    // Rebuild form.
    $form_state->setRebuild();
  }

  /**
   * Builds the render array for displaying the current results.
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

    $target_entity_type = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type');

    $field_widget_display = $this->fieldDisplayManager->createInstance(
      $this->getSetting('field_widget_display'),
      $this->getSetting('field_widget_display_settings') + ['entity_type' => $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type')]
    );

    $classes = [
      'entities-list',
      Html::cleanCssIdentifier("entity-type--$target_entity_type"),
    ];
    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() != 1) {
      $classes[] = 'sortable';
    }

    // The "Replace" button will only be shown if this setting is enabled in the
    // widget, and there is only one entity in the current selection.
    $replace_button_access = $this->getSetting('field_widget_replace') && (count($entities) === 1);

    return [
      '#theme_wrappers' => ['container'],
      '#attributes' => ['class' => $classes],
      '#prefix' => '<p>' . $this->getCardinalityMessage($entities) . '</p>',
      'items' => array_map(
        function (ContentEntityInterface $entity, $row_id) use ($field_widget_display, $details_id, $field_parents, $replace_button_access) {
          $display = $field_widget_display->view($entity);
          $edit_button_access = $this->getSetting('field_widget_edit') && $entity->access('update', $this->currentUser);
          if ($entity->getEntityTypeId() == 'file') {
            // On file entities, the "edit" button shouldn't be visible unless
            // the module "file_entity" is present, which will allow them to be
            // edited on their own form.
            $edit_button_access &= $this->moduleHandler->moduleExists('file_entity');
          }
          if (is_string($display)) {
            $display = ['#markup' => $display];
          }
          return [
            '#theme_wrappers' => ['container'],
            '#attributes' => [
              'class' => ['item-container', Html::getClass($field_widget_display->getPluginId())],
              'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
              'data-row-id' => $row_id,
            ],
            'display' => $display,
            'remove_button' => [
              '#type' => 'submit',
              '#value' => $this->t('Remove'),
              '#name' => $this->fieldDefinition->getName() . '_remove_' . $entity->id() . '_' . $row_id . '_' . md5(json_encode($field_parents)),
              '#attributes' => [
                'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
                'data-row-id' => $row_id,
                'class' => ['remove-button'],
              ],
              '#access' => (bool) $this->getSetting('field_widget_remove'),
            ],
            'replace_button' => [
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
              '#access' => $replace_button_access,
            ],
            'edit_button' => [
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
              '#access' => $edit_button_access,
            ],
          ];
        },
        $entities,
        empty($entities) ? [] : range(0, count($entities) - 1)
      ),
    ];
  }

  /**
   * Generates a message informing the user how many more items they can choose.
   *
   * @param array|int $selected
   *   The current selections, or how many items are selected.
   *
   * @return string
   *   A message informing the user who many more items they can select.
   */
  protected function getCardinalityMessage($selected) {
    $message = NULL;

    $storage = $this->fieldDefinition->getFieldStorageDefinition();
    $cardinality = $storage->getCardinality();
    $target_type = $storage->getSetting('target_type');
    $target_type = $this->entityTypeManager->getDefinition($target_type);

    if (is_array($selected)) {
      $selected = count($selected);
    }

    if ($cardinality === 1 && $selected === 0) {
      $message = $this->t('You can select one @entity_type.', [
        '@entity_type' => $target_type->getSingularLabel(),
      ]);
    }
    elseif ($cardinality >= $selected) {
      $message = $this->t('You can select up to @maximum @entity_type (@remaining left).', [
        '@maximum' => $cardinality,
        '@entity_type' => $target_type->getPluralLabel(),
        '@remaining' => $cardinality - $selected,
      ]);
    }
    return (string) $message;
  }

  /**
   * Gets data that should persist across Entity Browser renders.
   *
   * @return array
   *   Data that should persist after the Entity Browser is rendered.
   */
  protected function getPersistentData() {
    $settings = $this->fieldDefinition->getSettings();
    $handler = $settings['handler_settings'];
    return [
      'validators' => [
        'entity_type' => ['type' => $settings['target_type']],
      ],
      'widget_context' => [
        'target_bundles' => !empty($handler['target_bundles']) ? $handler['target_bundles'] : [],
        'target_entity_type' => $settings['target_type'],
        'cardinality' => $this->fieldDefinition->getFieldStorageDefinition()->getCardinality(),
      ],
    ];
  }

  /**
   * Gets options that define where newly added entities are inserted.
   *
   * @return array
   *   Mode labels indexed by key.
   */
  protected function selectionModeOptions() {
    return ['append' => $this->t('Append'), 'prepend' => $this->t('Prepend')];
  }

  /**
   * Provides base for settings summary shared by all EB widgets.
   *
   * @return array
   *   A short summary of the widget settings.
   */
  protected function summaryBase() {
    $summary = [];

    $entity_browser_id = $this->getSetting('entity_browser');
    if (empty($entity_browser_id)) {
      return [$this->t('No entity browser selected.')];
    }
    else {
      if ($browser = $this->entityTypeManager->getStorage('entity_browser')->load($entity_browser_id)) {
        $summary[] = $this->t('Entity browser: @browser', ['@browser' => $browser->label()]);
      }
      else {
        $this->messenger->addError($this->t('Missing entity browser!'));
        return [$this->t('Missing entity browser!')];
      }
    }

    $selection_mode = $this->getSetting('selection_mode');
    $selection_mode_options = EntityBrowserElement::getSelectionModeOptions();
    if (isset($selection_mode_options[$selection_mode])) {
      $summary[] = $this->t('Selection mode: @selection_mode', ['@selection_mode' => $selection_mode_options[$selection_mode]]);
    }
    else {
      $summary[] = $this->t('Undefined selection mode.');
    }

    return $summary;
  }

  /**
   * Determines the entities used for the form element.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field item to extract the entities from.
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The list of entities for the form element.
   */
  protected function formElementEntities(FieldItemListInterface $items, array $element, FormStateInterface $form_state) {
    $entities = [];

    // Find IDs from target_id element (it stores selected entities in form).
    // This was added to help solve a really edge casey bug in IEF.
    if (empty($form_state->getValues()) && $form_state->isMethodType('POST')) {
      $element_path = array_merge($element['#field_parents'], [$this->fieldDefinition->getName()]);
      $key_exists = NestedArray::keyExists($form_state->getUserInput(), $element_path);

      if ($key_exists) {
        $input_value = NestedArray::getValue($form_state->getUserInput(), $element_path);
        if (!empty($input_value) && isset($input_value['target_id'])) {
          $data = empty($input_value['target_id']) ? [] : explode(' ', trim($input_value['target_id']));
          $values = [];
          foreach ($data as $data_item) {
            $values[]['target_id'] = explode(':', $data_item)[1];
          }
          $items->setValue($values);
          return $items->referencedEntities();
        }
      }
    }

    // Determine if we're submitting and if submit came from this widget.
    $is_relevant_submit = FALSE;
    if (($trigger = $form_state->getTriggeringElement())) {
      // Can be triggered by hidden target_id element or "Remove" button.
      $last_parent = end($trigger['#parents']);
      if (in_array($last_parent, ['target_id', 'remove_button', 'replace_button'])) {
        $is_relevant_submit = TRUE;

        // In case there are more instances of this widget on the same page we
        // need to check if submit came from this instance.
        $field_name_key = end($trigger['#parents']) === 'target_id' ? 2 : static::$deleteDepth + 1;
        $field_name_key = count($trigger['#parents']) - $field_name_key;
        $is_relevant_submit &= ($trigger['#parents'][$field_name_key] === $this->fieldDefinition->getName()) &&
          (array_slice($trigger['#parents'], 0, count($element['#field_parents'])) == $element['#field_parents']);
      }
    }

    if ($is_relevant_submit) {
      // Submit was triggered by hidden "target_id" element when entities were
      // added via entity browser.
      if (!empty($trigger['#ajax']['event']) && $trigger['#ajax']['event'] == 'entity_browser_value_updated') {
        $parents = array_slice($trigger['#parents'], 0, -1);
        $this->entityBrowserValueUpdated = TRUE;
      }

      if (isset($parents) && $value = $form_state->getValue($parents)) {
        $items->setValue($value);
        $entities = $items->referencedEntities();
      }
      return $entities;
    }

    // We are loading for for the first time so we need to load any existing
    // values that might already exist on the entity. Also, remove any leftover
    // data from removed entity references.
    return $items->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    // If an entity browser is being used in this widget, add it as a config
    // dependency.
    if ($browser_name = $this->getSetting('entity_browser')) {
      $dependencies['config'][] = 'entity_browser.browser.' . $browser_name;
    }

    return $dependencies;
  }

}
