<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;

/**
 * Tests the Entity Reference Widget.
 *
 * @group entity_browser
 */
class EntityReferenceWidgetTest extends EntityBrowserWebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, [
      'access test_entity_browser_iframe_node_view entity browser pages',
      'bypass node access',
      'administer node form display',
    ]);

  }

  /**
   * Tests Entity Reference widget.
   */
  public function testEntityReferenceWidget() {

    // Create an entity_reference field to test the widget.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_entity_reference1',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_entity_reference1',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Referenced articles',
      'settings' => [],
    ]);
    $field->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();

    // Create a dummy node that will be used as target.
    $target_node = Node::create([
      'title' => 'Walrus',
      'type' => 'article',
    ]);
    $target_node->save();

    $this->drupalGet('/node/add/article');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Referencing node 1');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    $this->assertSession()->buttonExists('Save')->press();

    $this->assertSession()->pageTextContains('Article Referencing node 1 has been created.');
    $nid = $this->container->get('entity.query')->get('node')->condition('title', 'Referencing node 1')->execute();
    $nid = reset($nid);

    $this->drupalGet('node/' . $nid . '/edit');
    $this->assertSession()->pageTextContains('Walrus');
    // Make sure both "Edit" and "Remove" buttons are visible.
    $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-remove-button');
    $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-edit-button')->press();

    // Test edit dialog by changing title of referenced entity.
    $edit_dialog = $this->assertSession()->waitForElement('xpath', '//div[contains(@id, "node-' . $target_node->id() . '-edit-dialog")]');
    $title_field = $edit_dialog->findField('title[0][value]');
    $title = $title_field->getValue();
    $this->assertEquals('Walrus', $title);
    $title_field->setValue('Alpaca');
    $this->getSession()->switchToIFrame();
    $this->assertSession()
      ->elementExists('css', '.ui-dialog-buttonset.form-actions .form-submit')
      ->press();
    $this->waitForAjaxToFinish();
    // Check that new title is displayed.
    $this->assertSession()->pageTextNotContains('Walrus');
    $this->assertSession()->pageTextContains('Alpaca');

    // Test whether changing these definitions on the browser config effectively
    // change the visibility of the buttons.
    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => FALSE,
        'field_widget_remove' => FALSE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();
    $this->drupalGet('node/' . $nid . '/edit');
    $this->assertSession()->buttonNotExists('edit-field-entity-reference1-current-items-0-remove-button');
    $this->assertSession()->buttonNotExists('edit-field-entity-reference1-current-items-0-edit-button');

    // Set them to visible again.
    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();
    $this->drupalGet('node/' . $nid . '/edit');
    $remove_button = $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-remove-button');
    $this->assertEquals('Remove', $remove_button->getValue());
    $this->assertTrue($remove_button->hasClass('remove-button'));
    $edit_button = $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-edit-button');
    $this->assertEquals('Edit', $edit_button->getValue());
    $this->assertTrue($edit_button->hasClass('edit-button'));
    // Make sure the "Replace" button is not there.
    $this->assertSession()->buttonNotExists('edit-field-entity-reference1-current-items-0-replace-button');

    // Test the "Remove" button on the widget works.
    $this->assertSession()->buttonExists('Remove')->press();
    $this->waitForAjaxToFinish();
    $this->assertSession()->pageTextNotContains('Alpaca');

    // Test the "Replace" button functionality.
    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => TRUE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();
    // In order to ensure the replace button opens the browser, it needs to be
    // closed.
    /** @var \Drupal\entity_browser\EntityBrowserInterface $browser */
    $browser = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser')
      ->load('test_entity_browser_iframe_node_view');
    $browser->getDisplay()
      ->setConfiguration([
        'width' => 650,
        'height' => 500,
        'link_text' => 'Select entities',
        'auto_open' => FALSE,
      ]);
    $browser->save();

    // We'll need a third node to be able to make a new selection.
    $target_node2 = Node::create([
      'title' => 'Target example node 2',
      'type' => 'article',
    ]);
    $target_node2->save();
    $this->drupalGet('node/' . $nid . '/edit');
    // If there is only one entity in the current selection the button should
    // show up.
    $replace_button = $this->assertSession()->buttonExists('edit-field-entity-reference1-current-items-0-replace-button');
    $this->assertEquals('Replace', $replace_button->getValue());
    $this->assertTrue($replace_button->hasClass('replace-button'));
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldExists('entity_browser_select[node:3]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->getSession()->wait(1000);
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    // Even in the AJAX-built markup for the newly selected element, the replace
    // button should be there.
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    // Adding a new node to the selection, however, should make it disappear.
    $open_iframe_link = $this->assertSession()->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->getSession()->wait(1000);
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementNotExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    $this->assertSession()->buttonExists('Save')->press();
    $this->assertSession()->pageTextContains('Article Referencing node 1 has been updated.');

    // Test the replace button again with different field cardinalities.
    FieldStorageConfig::load('node.field_entity_reference1')->setCardinality(1)->save();
    $this->drupalGet('/node/add/article');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Referencing node 2');
    $open_iframe_link = $this->assertSession()->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->getSession()->wait(1000);
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Alpaca');
    // All three buttons should be visible.
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-remove-button"]');
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-edit-button"]');
    $replace_button = $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldExists('entity_browser_select[node:2]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->getSession()->wait(1000);
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Referencing node 1');

    // Do the same as above but now with cardinality 2.
    FieldStorageConfig::load('node.field_entity_reference1')
      ->setCardinality(2)
      ->save();
    $this->drupalGet('/node/add/article');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Referencing node 3');
    $open_iframe_link = $this->assertSession()->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->getSession()->wait(1000);
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Alpaca');
    // All three buttons should be visible.
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-remove-button"]');
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-edit-button"]');
    $replace_button = $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldExists('entity_browser_select[node:2]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->getSession()->wait(1000);
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Referencing node 1');

    // Verify that if the user cannot edit the entity, the "Edit" button does
    // not show up, even if configured to.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $role->revokePermission('bypass node access')->trustData()->save();
    $this->drupalGet('node/add/article');
    $open_iframe_link = $this->assertSession()->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldExists('entity_browser_select[node:1]')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    $this->assertSession()->buttonNotExists('edit-field-entity-reference1-current-items-0-edit-button');
  }

  /**
   * Tests that drag and drop functions properly.
   */
  public function testDragAndDrop() {

    $gatsby = $this->createNode(['type' => 'shark', 'title' => 'Gatsby']);
    $daisy = $this->createNode(['type' => 'jet', 'title' => 'Daisy']);
    $nick = $this->createNode(['type' => 'article', 'title' => 'Nick']);

    $santa = $this->createNode(['type' => 'shark', 'title' => 'Santa Claus']);
    $easter_bunny = $this->createNode(['type' => 'jet', 'title' => 'Easter Bunny']);
    $pumpkin_king = $this->createNode(['type' => 'article', 'title' => 'Pumpkin King']);

    $field1_storage_config = [
      'field_name' => 'field_east_egg',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ];

    $field2_storage_config = [
      'field_name' => 'field_east_egg2',
    ] + $field1_storage_config;

    $field_storage = FieldStorageConfig::create($field1_storage_config);
    $field_storage->save();

    $field_storage2 = FieldStorageConfig::create($field2_storage_config);
    $field_storage2->save();

    $field1_config = [
      'field_name' => 'field_east_egg',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'East Eggers',
      'settings' => [
        'handler_settings' => [
          'target_bundles' => [
            'shark' => 'shark',
            'jet' => 'jet',
            'article' => 'article',
          ],
        ],
      ],
    ];

    $field2_config = [
      'field_name' => 'field_east_egg2',
      'label' => 'Easter Eggs',
    ] + $field1_config;

    $field = FieldConfig::create($field1_config);
    $field->save();

    $field2 = FieldConfig::create($field2_config);
    $field2->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    $form_display->removeComponent('field_reference');

    $field_widget_config = [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'widget_context_default_value',
        'table_settings' => [
          'status_column' => TRUE,
          'bundle_column' => TRUE,
          'label_column' => FALSE,
        ],
        'open' => FALSE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ];

    $form_display->setComponent('field_east_egg', $field_widget_config)->save();
    $form_display->setComponent('field_east_egg2', $field_widget_config)->save();

    // Set auto open to false on the entity browser.
    $entity_browser = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser')
      ->load('widget_context_default_value');

    $display_configuration = $entity_browser->get('display_configuration');
    $display_configuration['auto_open'] = FALSE;
    $entity_browser->set('display_configuration', $display_configuration);
    $entity_browser->save();

    $account = $this->drupalCreateUser([
      'access widget_context_default_value entity browser pages',
      'create article content',
      'access content',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('node/add/article');

    $this->assertSession()->elementExists('xpath', '(//summary)[1]')->click();

    // Open the entity browser widget form.
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $gatsby->id() . ']')->check();
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $daisy->id() . ']')->check();
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $nick->id() . ']')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->assertSession()->buttonExists('Use selected')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();

    $correct_order = [
      1 => 'Gatsby',
      2 => 'Daisy',
      3 => 'Nick',
    ];
    foreach ($correct_order as $key => $value) {
      $this->assertSession()
        ->elementContains('xpath', "(//div[contains(@class, 'item-container')])[" . $key . "]", $value);
    }

    // Close details 1.
    $this->assertSession()->elementExists('xpath', '(//summary)[1]')->click();
    // Open details 2.
    $this->assertSession()->elementExists('xpath', '(//summary)[2]')->click();

    // Open the entity browser widget form.
    $this->assertSession()->elementExists('xpath', "(//a[contains(text(), 'Select entities')])[2]")->click();
    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');

    $this->assertSession()->fieldExists('entity_browser_select[node:' . $santa->id() . ']')->check();
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $easter_bunny->id() . ']')->check();
    $this->assertSession()->fieldExists('entity_browser_select[node:' . $pumpkin_king->id() . ']')->check();
    $this->assertSession()->buttonExists('Select entities')->press();
    $this->waitForAjaxToFinish();
    $this->assertSession()->buttonExists('Use selected')->press();
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();

    // Close details 2.
    $this->assertSession()->elementExists('xpath', '(//summary)[2]')->click();
    // Open details 1.
    $this->assertSession()->elementExists('xpath', '(//summary)[1]')->click();

    $first_item = $this->assertSession()->elementExists('xpath', "(//div[contains(@class, 'item-container')])[1]");
    $this->dragDropElement($first_item, 160, 0);
    $this->waitForAjaxToFinish();

    $this->assertSession()->fieldExists('title[0][value]')->setValue('Hello World');

    $this->assertSession()->buttonExists('Save')->press();

    $this->drupalGet('node/7/edit');

    $this->assertItemOrder([
      1 => 'Daisy',
      2 => 'Gatsby',
      3 => 'Nick',
      4 => 'Santa Claus',
      5 => 'Easter Bunny',
      6 => 'Pumpkin King',
    ]);

    $fourth = $this->assertSession()->elementExists('xpath', "(//div[contains(@class, 'item-container')])[4]");
    $this->dragDropElement($fourth, 160, 0);

    $this->assertItemOrder([
      4 => 'Easter Bunny',
      5 => 'Santa Claus',
      6 => 'Pumpkin King',
    ]);

    // Test that order is preserved after removing item.
    $this->removeItemAtPosition(5);

    $this->waitForAjaxToFinish();

    $this->assertItemOrder([
      4 => 'Easter Bunny',
      5 => 'Pumpkin King',
    ]);

  }

  /**
   * Tests that reorder plus remove functions properly.
   */
  public function testDragAndDropAndRemove() {

    // Test reorder plus remove.
    $current_user = \Drupal::currentUser();

    $file_system = \Drupal::service('file_system');

    $files = [
      1 => 'file1',
      2 => 'file2',
      3 => 'file3',
      4 => 'file4',
      5 => 'file5',
      6 => 'file6',
      7 => 'file7',
      8 => 'file8',
    ];
    $values = [];
    foreach ($files as $key => $filename) {
      $file_system->copy(\Drupal::root() . '/core/misc/druplicon.png', 'public://' . $filename . '.jpg');
      /** @var \Drupal\file\FileInterface $file */
      $file = File::create([
        'uri' => 'public://' . $filename . '.jpg',
        'uid' => $current_user->id(),
      ]);
      $file->save();
      $values[] = ['target_id' => $file->id()];
    }

    $node = Node::create(
      [
        'title' => 'Testing file sort and remove',
        'type' => 'article',
        'field_reference' => $values,
      ]
    );

    $node->save();
    $edit_link = $node->toUrl('edit-form')->toString();
    $this->drupalGet($edit_link);

    $this->assertItemOrder([
      1 => 'file1.jpg',
      2 => 'file2.jpg',
      3 => 'file3.jpg',
      4 => 'file4.jpg',
      5 => 'file5.jpg',
      6 => 'file6.jpg',
      7 => 'file7.jpg',
      8 => 'file8.jpg',
    ]);

    $file1 = $this->assertSession()->elementExists('xpath', "(//div[contains(@class, 'item-container')])[1]");
    $this->dragDropElement($file1, 160, 0);

    $this->assertItemOrder([
      1 => 'file2.jpg',
      2 => 'file1.jpg',
    ]);

    // Test that order is preserved after removing item.
    $this->removeItemAtPosition(2);

    $this->assertItemOrder([
      1 => 'file2.jpg',
      2 => 'file3.jpg',
    ]);

    $file3 = $this->assertSession()->elementExists('xpath', "(//div[contains(@class, 'item-container')])[2]");
    $this->dragDropElement($file3, 160, 0);

    $this->assertItemOrder([
      2 => 'file4.jpg',
      3 => 'file3.jpg',
    ]);

    // Test that order is preserved after removing item.
    $this->removeItemAtPosition(3);

    $this->assertItemOrder([
      2 => 'file4.jpg',
      3 => 'file5.jpg',
    ]);

    $file5 = $this->assertSession()->elementExists('xpath', "(//div[contains(@class, 'item-container')])[3]");
    $this->dragDropElement($file5, 160, 0);

    $this->assertItemOrder([
      3 => 'file6.jpg',
      4 => 'file5.jpg',
    ]);

    // Test that order is preserved after removing item.
    $this->removeItemAtPosition(4);

    $this->assertItemOrder([
      3 => 'file6.jpg',
      4 => 'file7.jpg',
    ]);

    $file2 = $this->assertSession()->elementExists('xpath', "(//div[contains(@class, 'item-container')])[1]");
    $this->dragDropElement($file2, 320, 0);

    $this->assertItemOrder([
      1 => 'file4.jpg',
      2 => 'file6.jpg',
      3 => 'file7.jpg',
      4 => 'file2.jpg',
      5 => 'file8.jpg',
    ]);

    // Test that order is preserved after removing two items.
    $this->removeItemAtPosition(3);
    $this->removeItemAtPosition(3);

    $this->assertItemOrder([
      1 => 'file4.jpg',
      2 => 'file6.jpg',
      3 => 'file8.jpg',
    ]);

    // Test that remove with duplicate items removes the one at the correct
    // delta.  If you remove file 1 at position 3, it should remove that one,
    // not the same entity at position 1.
    $values = [
      ['target_id' => 1],
      ['target_id' => 2],
      ['target_id' => 1],
      ['target_id' => 3],
      ['target_id' => 4],
      ['target_id' => 5],
    ];
    $node->field_reference->setValue($values);
    $node->save();

    $edit_link = $node->toUrl('edit-form')->toString();
    $this->drupalGet($edit_link);

    $this->assertItemOrder([
      1 => 'file1.jpg',
      2 => 'file2.jpg',
      3 => 'file1.jpg',
      4 => 'file3.jpg',
      5 => 'file4.jpg',
      6 => 'file5.jpg',
    ]);

    $this->removeItemAtPosition(3);

    $this->assertItemOrder([
      1 => 'file1.jpg',
      2 => 'file2.jpg',
      3 => 'file3.jpg',
      4 => 'file4.jpg',
      5 => 'file5.jpg',
    ]);

    $this->drupalGet($edit_link);

    $this->assertItemOrder([
      1 => 'file1.jpg',
      2 => 'file2.jpg',
      3 => 'file1.jpg',
      4 => 'file3.jpg',
      5 => 'file4.jpg',
      6 => 'file5.jpg',
    ]);

    $this->removeItemAtPosition(1);

    $this->assertItemOrder([
      1 => 'file2.jpg',
      2 => 'file1.jpg',
      3 => 'file3.jpg',
      4 => 'file4.jpg',
      5 => 'file5.jpg',
    ]);

    // Test that removing item that reduces selection count to less than
    // cardinality number restores entity browser element.
    FieldStorageConfig::load('node.field_reference')
      ->setCardinality(1)
      ->save();

    $values = [
      ['target_id' => 1],
    ];
    $node->field_reference->setValue($values);
    $node->save();

    $this->assertSession()->buttonExists('Save')->press();

    // Reopen the form.
    $edit_link = $node->toUrl('edit-form')->toString();
    $this->drupalGet($edit_link);

    $this->assertItemOrder([
      1 => 'file1.jpg',
    ]);
    // The entity browser element should not be visible with cardinality 1 and
    // 1 currently selected item.
    $this->assertSession()->linkNotExists('Select entities');
    $this->assertSession()->buttonExists('Remove')->press();
    $this->waitForAjaxToFinish();
    // There should be no current selection.
    $this->assertSession()
      ->elementNotExists('xpath', "//*[contains(@class, 'item-container')]");
    // The entity browser element should be visible with cardinality 1 and
    // no current selection.
    $this->assertSession()->linkExists('Select entities');

    FieldStorageConfig::load('node.field_reference')
      ->setCardinality(2)
      ->save();

    $values = [
      ['target_id' => 1],
      ['target_id' => 2],
    ];
    $node->field_reference->setValue($values);
    $node->save();

    // Reopen the form.
    $edit_link = $node->toUrl('edit-form')->toString();
    $this->drupalGet($edit_link);

    $this->assertItemOrder([
      1 => 'file1.jpg',
      2 => 'file2.jpg',
    ]);
    // The entity browser element should not be visible with
    // cardinality 2 and 2 selections.
    $this->assertSession()->linkNotExists('Select entities');
    $this->assertSession()->elementExists('xpath', "(//*[contains(@class, 'item-container')])[2]")
      ->findButton('Remove')
      ->press();
    $this->waitForAjaxToFinish();

    $this->assertItemOrder([
      1 => 'file1.jpg',
    ]);

    // The entity browser element should be visible with cardinality 2 and
    // and a selection count of 1.
    $this->assertSession()->linkExists('Select entities');

  }

}
