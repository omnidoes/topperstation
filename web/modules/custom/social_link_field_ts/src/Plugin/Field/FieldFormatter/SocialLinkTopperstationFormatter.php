<?php

namespace Drupal\social_link_field_ts\Plugin\Field\FieldFormatter;

use Drupal\social_link_field\Plugin\Field\FieldFormatter\SocialLinkBaseFormatter;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'social_link_field' formatter.
 *
 * @FieldFormatter(
 *   id = "topperstation",
 *   label = @Translation("TopperStation icons"),
 *   field_types = {
 *     "social_links"
 *   }
 * )
 */
class SocialLinkTopperstationFormatter extends SocialLinkBaseFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = parent::viewElements($items, $langcode);

    foreach ($items as $delta => $item) {
      $values = $item->getValue();
      $social = $values['social'];
      $link = $values['link'];
      $icon = str_replace('fa-', '', $this->platforms[$social]['icon']);

      $element['#links'][$delta] = [
        'class' => '',
        'url' => $this->platforms[$social]['urlPrefix'] . $link,
        'title' => $this->platforms[$social]['name'],
        'icon' => $icon,
      ];
    }

    return $element;
  }

}
