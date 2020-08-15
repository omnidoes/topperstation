<?php

namespace Drupal\entity_browser\Plugin\Field\FieldWidget;

use Drupal\entity_browser\Plugin\Field\FieldWidget\ListBuilder\TableListBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\FieldWidgetDisplayManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Plugin implementation of the 'entity_reference' widget for entity browser.
 *
 * @FieldWidget(
 *   id = "entity_browser_table",
 *   label = @Translation("Entity browser with table layout"),
 *   description = @Translation("Uses entity browser to select entities."),
 *   multiple_values = TRUE,
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class TableWidget extends EntityReferenceBrowserWidget {

  /**
   * The target entity type for this entity reference field.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $targetEntityType;

  /**
   * The current field item list.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected $currentItems;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, FieldWidgetDisplayManager $field_display_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, MessengerInterface $messenger) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $entity_type_manager, $field_display_manager, $module_handler, $current_user, $messenger);
    $target_entity_type_id = $field_definition
      ->getFieldStorageDefinition()
      ->getSetting('target_type');
    $this->targetEntityType = $this->entityTypeManager->getDefinition($target_entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'table_settings' => [
        'status_column' => FALSE,
        'bundle_column' => FALSE,
        'label_column' => FALSE,
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['table_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Table column configuration'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $table_settings = $this->getSetting('table_settings');

    if ($this->targetEntityType->hasKey('published')) {
      $element['table_settings']['status_column'] = [
        '#title' => $this->t('Show Publication status'),
        '#description' => $this->t('Display publication status in separate column.'),
        '#type' => 'checkbox',
        '#default_value' => !empty($table_settings['status_column']) ? $table_settings['status_column'] : 0,
      ];
    }

    if ($this->targetEntityType->get('bundle_entity_type')) {
      $element['table_settings']['bundle_column'] = [
        '#title' => $this->t('Show @label', ['@label' => $this->targetEntityType->getBundleLabel()]),
        '#description' => $this->t('Display @label in separate column.', ['@label' => strtolower($this->targetEntityType->getBundleLabel())]),
        '#type' => 'checkbox',
        '#default_value' => !empty($table_settings['bundle_column']) ? $table_settings['bundle_column'] : 0,
      ];
    }

    $element['table_settings']['label_column'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Label'),
      '#description' => $this->t('Display label in separate column if not using label display.'),
      '#default_value' => !empty($table_settings['label_column']) ? $table_settings['label_column'] : 0,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $table_settings = $this->getSetting('table_settings');

    if (!empty($table_settings['status_column'])) {
      $summary[] = $this->t('Status column enabled');
    }

    if (!empty($table_settings['bundle_column'])) {
      $summary[] = $this->t('Bundle column enabled');
    }

    if (!empty($table_settings['label_column']) && $this->getSetting('field_widget_display') == 'rendered_entity') {
      $summary[] = $this->t('Label column enabled');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $this->currentItems = $items;
    return parent::formElement($items, $delta, $element, $form, $form_state);
  }

  /**
   * Builds the render array for displaying the current results.
   *
   * @param string $details_id
   *   The ID for the details element.
   * @param string[] $field_parents
   *   Field parents.
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   An array of referenced entities to be displayed.
   *
   * @return array
   *   The render array for the current selection.
   */
  protected function displayCurrentSelection($details_id, array $field_parents, array $entities) {
    $entity_type_id = $this->fieldDefinition->getSettings()['target_type'];
    $settings['entity_type'] = $entity_type_id;
    $field_widget_display = $this->fieldDisplayManager
      ->createInstance($this->getSetting('field_widget_display'), $this->getSetting('field_widget_display_settings'));

    $params = [
      $this->entityTypeManager->getDefinition($entity_type_id),
      $this->entityTypeManager->getStorage($entity_type_id),
      $this->currentItems,
      $entities,
      $field_widget_display,
      $details_id,
      $this->getSettings(),
      $field_parents,
      $this->fieldDefinition,
    ];

    $listBuilder = new TableListBuilder(...$params);

    return $listBuilder->render();
  }

  /**
   * Check if entity type has an edit form.
   *
   * @return bool
   *   Returns TRUE if entity type has an edit form.
   */
  protected function targetEntityTypeHasEditForm() {
    if ($this->targetEntityType->getFormClass('edit') || $this->targetEntityType->getFormClass('default')) {
      return TRUE;
    }
    return FALSE;
  }

}
