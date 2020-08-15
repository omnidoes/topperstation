<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

/**
 * Tests the image field widget.
 *
 * @group entity_browser
 */
class ImageFieldTest extends EntityBrowserWebDriverTestBase {

  /**
   * Created file entity.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $image;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'type' => 'image',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Images',
      'settings' => [
        'file_extensions' => 'jpg',
        'file_directory' => 'entity-browser-test',
        'max_resolution' => '40x40',
        'title_field' => TRUE,
      ],
    ])->save();

    \Drupal::service('file_system')->copy(\Drupal::root() . '/core/modules/simpletest/files/image-test.jpg', 'public://example.jpg');
    $this->image = File::create([
      'uri' => 'public://example.jpg',
    ]);
    $this->image->save();
    // Register usage for this file to avoid validation erros when referencing
    // this file on node save.
    \Drupal::service('file.usage')->add($this->image, 'entity_browser', 'test', '1');

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    $form_display->setComponent('field_image', [
      'type' => 'entity_browser_file',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_view',
        'open' => TRUE,
        'field_widget_edit' => FALSE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => TRUE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'view_mode' => 'default',
        'preview_image_style' => 'thumbnail',
      ],
    ])->save();

    $display_config = [
      'width' => '650',
      'height' => '500',
      'link_text' => 'Select images',
    ];
    /** @var \Drupal\entity_browser\EntityBrowserInterface $browser */
    $browser = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser')
      ->load('test_entity_browser_iframe_view');
    $browser->setDisplay('iframe');
    $browser->getDisplay()->setConfiguration($display_config);
    $browser->addWidget([
      // These settings should get overridden by our field settings.
      'settings' => [
        'upload_location' => 'public://',
        'extensions' => 'png',
      ],
      'weight' => 1,
      'label' => 'Upload images',
      'id' => 'upload',
    ]);
    $browser->setWidgetSelector('tabs');
    $browser->save();

    $account = $this->drupalCreateUser([
      'access test_entity_browser_iframe_view entity browser pages',
      'create article content',
      'edit own article content',
      'access content',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Tests basic usage for an image field.
   */
  public function testImageFieldUsage() {
    $this->drupalGet('node/add/article');
    $this->assertSession()->linkExists('Select images');
    $this->getSession()->getPage()->clickLink('Select images');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_view');
    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $this->image->id() . ']');
    $this->getSession()->getPage()->pressButton('Select entities');
    $this->waitForAjaxToFinish();
    $button = $this->assertSession()->waitForButton('Use selected');
    $this->assertSession()->pageTextContains('example.jpg');
    $button->press();

    // Switch back to the main page.
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    // Check if the image thumbnail exists.
    $this->assertSession()
      ->waitForElementVisible('xpath', '//tr[@data-drupal-selector="edit-field-image-current-0"]');
    // Test if the image filename is present.
    $this->assertSession()->pageTextContains('example.jpg');
    // Test specifying Alt and Title texts and saving the node.
    $alt_text = 'Test alt text.';
    $title_text = 'Test title text.';
    $this->getSession()->getPage()->fillField('field_image[current][0][meta][alt]', $alt_text);
    $this->getSession()->getPage()->fillField('field_image[current][0][meta][title]', $title_text);
    $this->getSession()->getPage()->fillField('title[0][value]', 'Node 1');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Article Node 1 has been created.');
    $node = Node::load(1);
    $saved_alt = $node->get('field_image')[0]->alt;
    $this->assertEquals($saved_alt, $alt_text);
    $saved_title = $node->get('field_image')[0]->title;
    $this->assertEquals($saved_title, $title_text);
    // Test the Delete functionality.
    $this->drupalGet('node/1/edit');
    $this->assertSession()->buttonExists('Remove');
    $this->getSession()->getPage()->pressButton('Remove');
    $this->waitForAjaxToFinish();
    // Image filename should not be present.
    $this->assertSession()->pageTextNotContains('example.jpg');
    $this->assertSession()->linkExists('Select entities');

    // Test the Replace functionality.
    \Drupal::service('file_system')->copy(\Drupal::root() . '/core/modules/simpletest/files/image-test.jpg', 'public://example2.jpg');
    $image2 = File::create(['uri' => 'public://example2.jpg']);
    $image2->save();
    \Drupal::service('file.usage')->add($image2, 'entity_browser', 'test', '1');
    $this->drupalGet('node/1/edit');
    $this->assertSession()->buttonExists('Replace');
    $this->getSession()->getPage()->pressButton('Replace');
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_view');
    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $image2->id() . ']');
    $this->getSession()->getPage()->pressButton('Select entities');
    $this->getSession()->getPage()->pressButton('Use selected');
    $this->getSession()->wait(1000);
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    // Initial image should not be present, the new one should be there instead.
    $this->assertSession()->pageTextNotContains('example.jpg');
    $this->assertSession()->pageTextContains('example2.jpg');
  }

  /**
   * Tests that settings are passed from the image field to the upload widget.
   */
  public function testImageFieldSettings() {
    $root = \Drupal::root();
    $file_wrong_type = $root . '/core/misc/druplicon.png';
    $file_too_big = $root . '/core/modules/simpletest/files/image-2.jpg';
    $file_just_right = $root . '/core/modules/simpletest/files/image-test.jpg';
    $this->drupalGet('node/add/article');
    $this->assertSession()->linkExists('Select images');
    $this->getSession()->getPage()->clickLink('Select images');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_view');
    // Switch to the image tab.
    $this->clickLink('Upload images');
    // Attempt to upload an invalid image type. The upload widget is configured
    // to allow png but the field widget is configured to allow jpg, so we
    // expect the field to override the widget.
    $this->getSession()->getPage()->attachFileToField('files[upload][]', $file_wrong_type);
    $this->waitForAjaxToFinish();
    if (version_compare(\Drupal::VERSION, '8.7', '>=')) {
      $this->assertSession()->responseContains('Only files with the following extensions are allowed: <em class="placeholder">jpg</em>.');
      $this->assertSession()->responseContains('The selected file <em class="placeholder">druplicon.png</em> cannot be uploaded.');
    }
    else {
      $this->assertSession()->pageTextContains('Only files with the following extensions are allowed: jpg');
      $this->assertSession()->pageTextContains('The specified file druplicon.png could not be uploaded');
    }
    // Upload an image bigger than the field widget's configured max size.
    $this->getSession()->getPage()->attachFileToField('files[upload][]', $file_too_big);
    $this->waitForAjaxToFinish();
    $this->assertSession()->pageTextContains('The image was resized to fit within the maximum allowed dimensions of 40x40 pixels.');
    // Upload an image that passes validation and finish the upload.
    $this->getSession()->getPage()->attachFileToField('files[upload][]', $file_just_right);
    $this->waitForAjaxToFinish();
    $this->getSession()->getPage()->pressButton('Select files');
    $this->waitForAjaxToFinish();
    $button = $this->assertSession()->waitForButton('Use selected');
    $this->assertSession()->pageTextContains('image-test.jpg');
    $button->press();
    $this->waitForAjaxToFinish();
    // Check that the file has uploaded to the correct sub-directory.
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    $entity_id = $this->getSession()->evaluateScript('jQuery("#edit-field-image-wrapper [data-entity-id]").data("entity-id")');
    $this->assertStringStartsWith('file:', $entity_id);
    /** @var \Drupal\file\Entity\File $file */
    $fid = explode(':', $entity_id)[1];
    $file = File::load($fid);
    $this->assertContains('entity-browser-test', $file->getFileUri());
  }

  /**
   * Tests that reorder plus remove functions properly.
   */
  public function testDragAndDropAndRemove() {

    // Test reorder plus remove.
    $current_user = \Drupal::currentUser();

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
      file_unmanaged_copy(\Drupal::root() . '/core/misc/druplicon.png', 'public://' . $filename . '.jpg');
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
        'field_image' => $values,
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

    $file1_handle = $this->assertSession()->elementExists('xpath', "(//a[contains(@class, 'tabledrag-handle')])[1]");
    $file2 = $this->assertSession()->elementExists('xpath', "(//tr[contains(@class, 'item-container')])[2]");
    $file1_handle->dragTo($file2);

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

    $file3 = $this->assertSession()->elementExists('xpath', "(//tr[contains(@class, 'item-container')])[2]");
    $this->dragDropElement($file3, 160, 0);

    $file3_handle = $this->assertSession()->elementExists('xpath', "(//a[contains(@class, 'tabledrag-handle')])[2]");
    $file4 = $this->assertSession()->elementExists('xpath', "(//tr[contains(@class, 'item-container')])[3]");
    $file3_handle->dragTo($file4);

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

    $file5 = $this->assertSession()->elementExists('xpath', "(//tr[contains(@class, 'item-container')])[3]");
    $this->dragDropElement($file5, 160, 0);

    $file5_handle = $this->assertSession()->elementExists('xpath', "(//a[contains(@class, 'tabledrag-handle')])[3]");
    $file6 = $this->assertSession()->elementExists('xpath', "(//tr[contains(@class, 'item-container')])[4]");
    $file5_handle->dragTo($file6);

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

    $file2_handle = $this->assertSession()->elementExists('xpath', "(//a[contains(@class, 'tabledrag-handle')])[1]");
    $file8 = $this->assertSession()->elementExists('xpath', "(//tr[contains(@class, 'item-container')])[5]");
    $file2_handle->dragTo($file8);

    $this->assertItemOrder([
      1 => 'file4.jpg',
      2 => 'file6.jpg',
      3 => 'file7.jpg',
      4 => 'file8.jpg',
      5 => 'file2.jpg',
    ]);

    // Test that order is preserved after removing two items.
    $this->removeItemAtPosition(3);
    $this->removeItemAtPosition(4);

    $this->assertItemOrder([
      1 => 'file4.jpg',
      2 => 'file6.jpg',
      3 => 'file8.jpg',
    ]);

    // Test that remove with duplicate items removes the one at the
    // correct delta.  If you remove file 1 at position 3, it should
    // remove that one, not the same entity at position 1.
    $values = [
      ['target_id' => 2],
      ['target_id' => 3],
      ['target_id' => 2],
      ['target_id' => 4],
      ['target_id' => 5],
      ['target_id' => 6],
    ];
    $node->field_image->setValue($values);
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

  }

}
