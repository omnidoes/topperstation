<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;

/**
 * Tests the Table Field Widget.
 *
 * @group entity_browser
 */
class TableFieldWidgetTest extends EntityBrowserWebDriverTestBase {

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
      'create article content',
      'access content',
    ]);
  }

  /**
   * Tests Table Field Widget.
   */
  public function testTableWidget() {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Create an entity_reference field to test the widget.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_mad_scientist',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_mad_scientist',
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

    $form_display->setComponent('field_mad_scientist', [
      'type' => 'entity_browser_table',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'table_settings' => [
          'status_column' => TRUE,
          'bundle_column' => TRUE,
          'label_column' => FALSE,
        ],
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
      'title' => 'Victor Frankenstein',
      'type' => 'article',
    ]);
    $target_node->save();

    $this->drupalGet('/node/add/article');
    $page->fillField('title[0][value]', 'Doctor Moreau');
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $page->pressButton('Save');

    $assert_session->pageTextContains('Article Doctor Moreau has been created.');
    $nid = $this->container->get('entity.query')->get('node')->condition('title', 'Doctor Moreau')->execute();
    $nid = reset($nid);

    $this->drupalGet('node/' . $nid . '/edit');

    $tr = $assert_session
      ->elementExists('xpath', "//tr[contains(., 'Victor Frankenstein')]");
    // Make sure both "Edit" and "Remove" buttons are visible.
    $this->assertTrue($tr->hasButton('Remove'));
    $this->assertTrue($tr->hasButton('Edit'));
    $tr_text = $tr->getText();
    $this->assertContains('Published', $tr_text);
    $this->assertContains('Article', $tr_text);

    // Assert table headers are present.
    $assert_session->elementExists('xpath', "//th[contains(., 'Published')]");
    $assert_session->elementExists('xpath', "//th[contains(., 'Content type')]");
    $assert_session->elementExists('xpath', "//th[contains(., 'Operations')]");

    // Test whether changing these definitions on the browser config effectively
    // change the visibility of the buttons.
    $form_display->setComponent('field_mad_scientist', [
      'type' => 'entity_browser_table',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'table_settings' => [
          'status_column' => FALSE,
          'bundle_column' => FALSE,
          'label_column' => FALSE,
        ],
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

    $tr = $assert_session
      ->elementExists('xpath', "//tr[contains(., 'Victor Frankenstein')]");
    // Make sure both "Edit" and "Remove" buttons are visible.
    $this->assertNotTrue($tr->hasButton('Remove'));
    $this->assertNotTrue($tr->hasButton('Edit'));
    $tr_text = $tr->getText();
    $this->assertNotContains('Published', $tr_text);
    $this->assertNotContains('Article', $tr_text);

    // Assert table headers are present.
    $assert_session->elementNotExists('xpath', "//th[contains(., 'Published')]");
    $assert_session->elementNotExists('xpath', "//th[contains(., 'Content type')]");

    // Set buttons to visible again.
    $form_display->setComponent('field_mad_scientist', [
      'type' => 'entity_browser_table',
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

    $tr = $assert_session
      ->elementExists('xpath', "//tr[contains(., 'Victor Frankenstein')]");
    // Make sure both "Edit" and "Remove" buttons are visible.
    $this->assertTrue($tr->hasButton('Remove'));
    $this->assertTrue($tr->hasButton('Edit'));
    // Make sure the "Replace" button is not there.
    $this->assertFalse($tr->hasButton('Replace'));

    // Test the "Remove" button on the widget works.
    $page->pressButton('Remove');
    $this->waitForAjaxToFinish();
    $assert_session->pageTextNotContains('Victor Frankenstein');

    // Test the "Replace" button functionality.
    $form_display->setComponent('field_mad_scientist', [
      'type' => 'entity_browser_table',
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
      'title' => 'Henry Jekyll',
      'type' => 'article',
    ]);
    $target_node2->save();
    $this->drupalGet('node/' . $nid . '/edit');
    // If there is only one entity in the current selection the button should
    // show up.
    $replace_button = $assert_session->buttonExists('Replace');
    $this->assertEquals('Replace', $replace_button->getValue());
    $this->assertTrue($replace_button->hasClass('replace-button'));
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node3');
    $page->pressButton('Select entities');
    $this->waitForAjaxToFinish();
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    // Even in the AJAX-built markup for the newly selected element, the replace
    // button should be there.
    $tr = $assert_session
      ->elementExists('xpath', "//tr[contains(., 'Henry Jekyll')]");
    // Make sure both "Edit" and "Remove" buttons are visible.
    $this->assertTrue($tr->hasButton('Remove'));
    $this->assertTrue($tr->hasButton('Replace'));
    $this->assertTrue($tr->hasButton('Edit'));

    // Adding a new node to the selection, however, should make it disappear.
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-mad-scientist-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $this->waitForAjaxToFinish();
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $assert_session->buttonNotExists('Replace');
    $page->pressButton('Save');
    $assert_session->pageTextContains('Article Doctor Moreau has been updated.');

    // Test the replace button again with different field cardinalities.
    FieldStorageConfig::load('node.field_mad_scientist')->setCardinality(1)->save();
    $this->drupalGet('/node/add/article');
    $page->fillField('title[0][value]', 'R. M. Renfield');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-mad-scientist-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $this->waitForAjaxToFinish();
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();

    $tr = $assert_session
      ->elementExists('xpath', "//tr[contains(., 'Victor Frankenstein')]");
    // All three buttons should be visible.
    $this->assertTrue($tr->hasButton('Remove'));
    $this->assertTrue($tr->hasButton('Edit'));
    $this->assertTrue($tr->hasButton('Replace'));

    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node2');
    $page->pressButton('Select entities');
    $this->waitForAjaxToFinish();
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $assert_session->elementExists('xpath', "//tr[contains(., 'Doctor Moreau')]");

    // Do the same as above but now with cardinality 2.
    FieldStorageConfig::load('node.field_mad_scientist')->setCardinality(2)->save();
    $this->drupalGet('/node/add/article');
    $page->fillField('title[0][value]', 'Referencing node 3');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-mad-scientist-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $this->waitForAjaxToFinish();
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();

    $tr = $assert_session
      ->elementExists('xpath', "//tr[contains(., 'Victor Frankenstein')]");
    // All three buttons should be visible.
    $this->assertTrue($tr->hasButton('Remove'));
    $this->assertTrue($tr->hasButton('Edit'));
    $this->assertTrue($tr->hasButton('Replace'));

    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $this->assertSession()->buttonExists('Replace')->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node2');
    $page->pressButton('Select entities');
    $this->waitForAjaxToFinish();
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $assert_session->elementExists('xpath', "//tr[contains(., 'Doctor Moreau')]");

    // Verify that if the user cannot edit the entity, the "Edit" button does
    // not show up, even if configured to.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $role->revokePermission('bypass node access')->trustData()->save();
    $this->drupalGet('node/add/article');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-mad-scientist-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();

    $tr = $assert_session
      ->elementExists('xpath', "//tr[contains(., 'Victor Frankenstein')]");

    // Edit field should not exist.
    $this->assertTrue($tr->hasButton('Remove'));
    $this->assertTrue($tr->hasButton('Replace'));
    $this->assertFalse($tr->hasButton('Edit'));

    FieldStorageConfig::load('node.field_mad_scientist')->setCardinality(-1)->save();
    $this->drupalGet('/node/add/article');

  }

  /**
   * Tests Table Field Widget form config.
   */
  public function testFieldWidgetSettings() {

    // Create an entity_reference field to test the widget.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_lycanthrope',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_lycanthrope',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Loup Garou',
      'settings' => [],
    ]);
    $field->save();

    $this->drupalGet('/admin/structure/types/manage/article/form-display');
    // Drag to enabled.
    $target = $this->assertSession()
      ->elementExists('css', '#title');
    $this->assertSession()
      ->elementExists('css', '#field-lycanthrope')
      ->find('css', '.handle')
      ->dragTo($target);
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Set to Entity Browser Widget.
    $this->assertSession()->selectExists('fields[field_lycanthrope][type]')->selectOption('entity_browser_table');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Open settings form.
    $this->assertSession()->waitforButton('field_lycanthrope_settings_edit')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $form_prefix = 'fields[field_lycanthrope][settings_edit_form][settings]';

    // Select entity browser with "no_selection" selection display.
    $this->assertSession()->selectExists($form_prefix . '[entity_browser]')->selectOption('test_entity_browser_iframe_node_view');

    $status_column = $this->assertSession()->fieldExists($form_prefix . '[table_settings][status_column]');
    $bundle_column = $this->assertSession()->fieldExists($form_prefix . '[table_settings][bundle_column]');
    $label_column = $this->assertSession()->fieldExists($form_prefix . '[table_settings][label_column]');

    $this->assertFalse($status_column->isChecked());
    $this->assertFalse($bundle_column->isChecked());
    $this->assertFalse($label_column->isChecked());

    $status_column->check();
    $bundle_column->check();
    $label_column->check();

    $this->assertSession()->buttonExists('field_lycanthrope_plugin_settings_update')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->pageTextContains('Status column enabled');
    $this->assertSession()->pageTextContains('Bundle column enabled');

    // This doesn't shows unless using rendered_entity field_widget_display.
    $this->assertSession()->pageTextNotContains('Label column enabled');

    // Open settings form.
    $this->assertSession()->waitforButton('field_lycanthrope_settings_edit')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $status_column = $this->assertSession()->fieldExists($form_prefix . '[table_settings][status_column]');
    $bundle_column = $this->assertSession()->fieldExists($form_prefix . '[table_settings][bundle_column]');
    $label_column = $this->assertSession()->fieldExists($form_prefix . '[table_settings][label_column]');

    $this->assertTrue($status_column->isChecked());
    $this->assertTrue($bundle_column->isChecked());
    $this->assertTrue($label_column->isChecked());

    // Test that changing field_widget_display to rendered entity
    // causes 'Label column enabled' to display on summary.
    $this->assertSession()->fieldExists($form_prefix . '[field_widget_display]')->setValue('rendered_entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->buttonExists('field_lycanthrope_plugin_settings_update')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Label column enabled');

  }

  /**
   * Tests that drag and drop functions properly.
   */
  public function testTableDrag() {

    $luke = $this->createNode(['type' => 'shark', 'title' => 'Luke']);
    $leia = $this->createNode(['type' => 'jet', 'title' => 'Leia']);
    $darth = $this->createNode(['type' => 'article', 'title' => 'Darth']);

    // Create an entity_reference field to test the widget.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_force',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_force',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Force sensitive people',
      'settings' => [
        'handler_settings' => [
          'target_bundles' => [
            'shark' => 'shark',
            'jet' => 'jet',
            'article' => 'article',
          ],
        ],
      ],
    ]);
    $field->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    $form_display->removeComponent('field_reference');

    $form_display->setComponent('field_force', [
      'type' => 'entity_browser_table',
      'settings' => [
        'entity_browser' => 'widget_context_default_value',
        'table_settings' => [
          'status_column' => TRUE,
          'bundle_column' => TRUE,
          'label_column' => FALSE,
        ],
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();

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

    // Open the entity browser widget form.
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');

    $page = $this->getSession()->getPage();

    $page->checkField('edit-entity-browser-select-node' . $luke->id());
    $page->checkField('edit-entity-browser-select-node' . $leia->id());
    $page->checkField('edit-entity-browser-select-node' . $darth->id());
    $page->pressButton('Select entities');
    $this->waitForAjaxToFinish();
    $page->pressButton('Use selected');
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();

    $this->assertItemOrder([
      1 => 'Luke',
      2 => 'Leia',
      3 => 'Darth',
    ]);

    $first_handle = $this->assertSession()->elementExists('xpath', '(//td[contains(@class, "entity-browser--field-widget--tabledrag")])[1]');
    $second_row = $this->assertSession()->elementExists('xpath', '(//tr[contains(@class, "draggable")])[2]');
    $first_handle->dragTo($second_row);
    // Trigger "mouseup" event, as driver's dragTo method doesn't trigger it.
    $first_handle->click();

    $this->assertItemOrder([
      1 => 'Leia',
      2 => 'Luke',
      3 => 'Darth',
    ]);

    $this->assertSession()->fieldExists('title[0][value]')->setValue('The Skywalkers');

    $this->assertSession()->buttonExists('Save')->press();

    $this->drupalGet('node/4/edit');

    $this->assertItemOrder([
      1 => 'Leia',
      2 => 'Luke',
      3 => 'Darth',
    ]);

    $first_handle = $this->assertSession()->elementExists('xpath', '(//td[contains(@class, "entity-browser--field-widget--tabledrag")])[1]');
    $second_row = $this->assertSession()->elementExists('xpath', '(//tr[contains(@class, "draggable")])[2]');
    $first_handle->dragTo($second_row);
    // Trigger "mouseup" event, as driver's dragTo method doesn't trigger it.
    $first_handle->click();

    $this->assertItemOrder([
      1 => 'Luke',
      2 => 'Leia',
      3 => 'Darth',
    ]);

    // Test that order is preserved after removing item.
    $this->removeItemAtPosition(2);

    $this->assertItemOrder([
      1 => 'Luke',
      2 => 'Darth',
    ]);

    $first_handle = $this->assertSession()->elementExists('xpath', '(//td[contains(@class, "entity-browser--field-widget--tabledrag")])[1]');
    $second_row = $this->assertSession()->elementExists('xpath', '(//tr[contains(@class, "draggable")])[2]');
    $first_handle->dragTo($second_row);
    // Trigger "mouseup" event, as driver's dragTo method doesn't trigger it.
    $first_handle->click();

    $this->assertItemOrder([
      1 => 'Darth',
      2 => 'Luke',
    ]);

    $this->assertSession()->buttonExists('Save')->press();

    $node = Node::load(4);

    $expected = [
      0 => ['target_id' => $darth->id()],
      1 => ['target_id' => $luke->id()],
    ];

    $this->assertEquals($expected, $node->field_force->getValue());

  }

}
