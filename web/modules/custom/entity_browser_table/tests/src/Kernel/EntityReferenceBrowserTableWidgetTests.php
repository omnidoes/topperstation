<?php
/**
 * Created by PhpStorm.
 * User: jbyng
 * Date: 26/09/2019
 * Time: 12:29
 */

namespace Drupal\entity_browser_table\Kernel;


use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\entity_browser_table\Plugin\Field\FieldWidget\EntityReferenceBrowserTableWidget;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

class EntityReferenceBrowserTableWidgetTests extends KernelTestBase {

  protected $entities;

  protected $fieldParents;

  protected $detailsId;

  protected $rowId;

  protected $entityWithSingleBrand;

  protected $entityWithMultiBrand;

  /* @var $widget EntityReferenceBrowserTableWidget */
  protected $widget;

  protected static $modules = [
    'media',
    'image',
    'user',
    'file',
    'system',
    'language',
    'taxonomy',
    'text',
    'content_translation',
    'entity_browser',
    'entity_browser_table',
  ];

  public function setUp() {
    parent::setUp(); // TODO: Change the autogenerated stub

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig('media');

    // Install the United Kingdowm Language.
    $language = ConfigurableLanguage::createFromLangcode('en-gb');
    $language->save();

    $fieldStorage = $this->createMock(FieldStorageConfig::class);
    $fieldStorage->method('getSetting')
      ->willReturn('media');
    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->method('getFieldStorageDefinition')
      ->willReturn($fieldStorage);
    $configuration = [
      'field_definition' => $fieldDefinition,
      'settings' => [],
      'third_party_settings' => [],
    ];
    $this->widget = EntityReferenceBrowserTableWidget::create($this->container, $configuration, $this->randomString(), []);

    $this->entities = [
      Media::create([
        'bundle' => 'image',
        'name' => $this->randomString(),
        'field_brands' => [['target_id' => 1]],
      ]),
      Media::create([
        'bundle' => 'image',
        'name' => $this->randomString(),
        'field_brands' => 2,
      ]),
      Media::create([
        'bundle' => 'file',
        'name' => $this->randomString(),
        'field_brands' => 3,
      ]),
    ];
    $this->detailsId = $this->randomString();
    $this->fieldParents = [$this->randomString(), $this->randomString()];
    $this->rowId = $this->randomString();
  }

  public function testBuildTableRows__GivenThreeEntities__ReturnArrayOfThree() {
    $testBuildTableRows = $this->widget->buildTableRows($this->entities,$this->detailsId, $this->fieldParents);
    $this->assertInternalType('array', $testBuildTableRows);
    $this->assertCount(3, $testBuildTableRows);
  }

  public function testBuildTableRows__GivenFourEntitiesWithOneGB__ReturnArrayOfThree() {
    $ukMedia = Media::create([
      'bundle' => 'file',
      'name' => $this->randomString(),
      'langcode' => 'en-gb',
    ]);
    array_push($this->entities, $ukMedia);
    $this->assertCount(4, $this->entities);
    $testBuildTableRows = $this->widget->buildTableRows($this->entities,$this->detailsId, $this->fieldParents);
    $this->assertInternalType('array', $testBuildTableRows);
    $this->assertCount(3, $testBuildTableRows);
  }

  public function testBuildTableRows__GivenArrayOfFourStings__ReturnEmptyArray() {
    $arrayOfStrings = [
      $this->randomString(),
      $this->randomString(),
      $this->randomString(),
    ];
    $testBuildTableRows = $this->widget->buildTableRows($arrayOfStrings,$this->detailsId, $this->fieldParents);
    $this->assertInternalType('array', $testBuildTableRows);
    $this->assertEmpty($testBuildTableRows);
  }

  public function testBuildEditButton() {
    $testBuildEditButton = $this->widget->buildEditButton($this->entities[0]);
    $this->assertInternalType('array', $testBuildEditButton);
    $this->assertCount(8, $testBuildEditButton);
  }

  public function testBuildRemoveButton() {
    $testBuildEditButton = $this->widget->buildRemoveButton($this->entities[0], $this->detailsId, $this->rowId, $this->fieldParents);
    $this->assertInternalType('array', $testBuildEditButton);
    $this->assertCount(8, $testBuildEditButton);
  }

  public function testBuildReplaceButton() {
    $testBuildEditButton = $this->widget->buildReplaceButton($this->entities[0], $this->entities, $this->detailsId, $this->rowId, $this->fieldParents);
    $this->assertInternalType('array', $testBuildEditButton);
    $this->assertCount(8, $testBuildEditButton);
  }

  public function testGetEntityTypeLabel() {
    // TODO: Figure out how to mock the entityTypeManager response.
    //    $this->assertEquals('Image', $this->widget->getEntityTypeLable($this->entities[0]));
  }

  public function testGetEditButtonAccess__ReturnsTrue() {
    $entity = $this->createMock(Media::class);
    $entity->method('access')->withAnyParameters()->willReturn(TRUE);
    $this->widget->setSetting('field_widget_edit', TRUE);
    $this->assertTrue($this->widget->getEditButtonAccess($entity));
  }

  public function testGetEditButtonAccess__ReturnsFalse() {
    $entity = $this->createMock(Media::class);
    $entity->method('access')->withAnyParameters()->willReturn(FALSE);
    $this->widget->setSetting('field_widget_edit', TRUE);
    $this->assertFalse($this->widget->getEditButtonAccess($entity));
  }

  public function testGetReplaceButtonAccess__GivenOneEntityAndSettingTrue__ReturnTrue() {
    $entity = $this->createMock(Media::class);
    $this->widget->setSetting('field_widget_replace', TRUE);
    $this->assertTrue($this->widget->getReplaceButtonAccess($entity));
  }

  public function testGetReplaceButtonAccess__GivenOneEntityAndNoSetting__ReturnFalse() {
    $entity = $this->createMock(Media::class);
    $this->assertFalse($this->widget->getReplaceButtonAccess($entity));
  }

  public function testGetReplaceButtonAccess__GivenMultipleEntitiesAndSettingTrue__ReturnFalse() {
    $this->widget->setSetting('field_widget_edit', TRUE);
    $this->assertFalse($this->widget->getReplaceButtonAccess($this->entities));
  }

  public function testGetFirstColumn__GivenRenderedEntitySetting__ReturnMediaRenderArray() {
    $this->widget->setSetting('field_widget_display', 'rendered_entity');
    $this->widget->setSetting('field_widget_display_settings', [
      "view_mode" => "preview",
    ]);
    $testGetFirstColumn = $this->widget->getFirstColumn($this->entities[0]);
    $this->assertInternalType('array', $testGetFirstColumn);
    $this->assertArrayHasKey('#media', $testGetFirstColumn);
    $this->assertInstanceOf(Media::class, $testGetFirstColumn['#media']);
    $this->assertArrayHasKey('#view_mode', $testGetFirstColumn);
    $this->assertEquals('preview', $testGetFirstColumn['#view_mode']);
  }

  public function testGetFirstColumn__GivenLabelSetting__ReturnMarkupRenderArray() {
    $this->widget->setSetting('field_widget_display', 'label');
    $this->widget->setSetting('field_widget_display_settings', []);
    $testGetFirstColumn = $this->widget->getFirstColumn($this->entities[0]);
    $this->assertInternalType('array', $testGetFirstColumn);
    $this->assertArrayHasKey('#markup', $testGetFirstColumn);
    $this->assertEquals($this->entities[0]->getName(), $testGetFirstColumn['#markup']);
  }

  public function testGetFirstColumnHeader__GivenRenderedEntitySetting__ReturnThumbnailString() {
    $this->widget->setSetting('field_widget_display', 'rendered_entity');
    $this->assertEquals('Thumbnail', $this->widget->getFirstColumnHeader());
  }

  public function testGetFirstColumnHeader__GivenLabelSetting__ReturnTitleString() {
    $this->widget->setSetting('field_widget_display', 'label');
    $this->assertEquals('Title', $this->widget->getFirstColumnHeader());
  }

  public function testGetActionColumnHeader__GivenDisplayEditButtonSetting__ReturnActionString() {
    $this->widget->setSetting('field_widget_edit', TRUE);
    $this->assertEquals('Action', $this->widget->getActionColumnHeader());
  }

  public function testGetActionColumnHeader__GivenDisplayRemoveButtonSetting__ReturnActionString() {
    $this->widget->setSetting('field_widget_remove', TRUE);
    $this->assertEquals('Action', $this->widget->getActionColumnHeader());
  }
  public function testGetActionColumnHeader__GivenDisplayReplaceButtonSetting__ReturnActionString() {
    $this->widget->setSetting('field_widget_replace', TRUE);
    $this->assertEquals('Action', $this->widget->getActionColumnHeader());
  }
  public function testGetActionColumnHeader__GivenAllDisplayButtonSettingsFalse__ReturnNull() {
    $this->widget->setSetting('field_widget_edit', FALSE);
    $this->widget->setSetting('field_widget_remove', FALSE);
    $this->widget->setSetting('field_widget_replace', FALSE);

    $this->assertNull($this->widget->getActionColumnHeader());
  }

  protected function createImageMediaBundle() {
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $media_bundle_storage = $entity_type_manager->getStorage('media_type');
    $media_bundle = $media_bundle_storage->create([
      'id' => 'image',
      'label' => 'Image',
      'description' => 'Description.',
      'type' => 'image',
      'type_configuration' => [
        'source_field' => 'field_media_image',
      ],
    ]);
    $media_bundle->save();
  }

}
