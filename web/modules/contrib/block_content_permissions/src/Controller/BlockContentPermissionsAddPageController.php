<?php

namespace Drupal\block_content_permissions\Controller;

use Drupal\block_content\Controller\BlockContentController;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the block content add page.
 *
 * Extends normal controller to remove types based on create permission.
 */
class BlockContentPermissionsAddPageController extends BlockContentController {

  /**
   * {@inheritdoc}
   */
  public function add(Request $request) {
    $types = $this->blockContentTypeStorage->loadMultiple();

    // Remove block content types based on create permissions.
    $account = \Drupal::getContainer()->get('current_user');
    foreach ($types as $bundle_type => $bundle_obj) {
      if (!$account->hasPermission("create $bundle_type block content")) {
        unset($types[$bundle_type]);
      }
    }

    if ($types && count($types) == 1) {
      $type = reset($types);
      return $this->addForm($type, $request);
    }
    if (count($types) === 0) {
      return [
        '#markup' => $this->t('You have not created any block types yet. Go to the <a href=":url">block type creation page</a> to add a new block type.', [
          ':url' => Url::fromRoute('block_content.type_add')->toString(),
        ]),
      ];
    }

    return ['#theme' => 'block_content_add_list', '#content' => $types];
  }

}
