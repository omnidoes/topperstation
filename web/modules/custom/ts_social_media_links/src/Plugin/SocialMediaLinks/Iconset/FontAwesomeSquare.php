<?php
/**
 * @file
 * Contains \Drupal\custom_iconset\Plugin\SocialMediaLinks\Iconset\FontAwesomeSquare.
 */

namespace Drupal\ts_social_media_links\Plugin\SocialMediaLinks\Iconset;

use Drupal\social_media_links\IconsetBase;
use Drupal\social_media_links\IconsetInterface;

/**
 * Provides 'fontawesome_square' iconset.
 *
 * @Iconset(
 *   id = "fontawesome_square",
 *   publisher = "Font Awesome Square",
 *   publisherUrl = "http://fontawesome.github.io/",
 *   downloadUrl = "http://fortawesome.github.io/Font-Awesome/",
 *   name = "Font Awesome Square",
 * )
 */
class FontAwesomeSquare extends IconsetBase implements IconsetInterface {

  /**
   * {@inheritdoc}
   */
  public function setPath($iconset_id) {
    $this->path = $this->finder->getPath($iconset_id) ? $this->finder->getPath($iconset_id) : 'library';
  }

  /**
   * {@inheritdoc}
   */
  public function getStyle() {
    return [
      '2x' => 'fa-2x',
      '3x' => 'fa-3x',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIconElement($platform, $style) {
    $icon_name = $platform->getIconName();
    $icon_shape = '-square';

    switch ($icon_name) {

      case 'drupal':
      case 'flickr':
      case 'instagram':
      case 'slideshare':
      case 'vk':
        $icon_shape = '';
        break;

      case 'googleplus':
        $icon_name = 'google-plus';
        break;

      case 'email':
        $icon_name = 'envelope';
        break;
    }

    $icon = [
      '#type' => 'markup',
      '#markup' => "<span class='fa fa-$icon_name".$icon_shape." fa-$style'></span>",
    ];

    return $icon;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibrary() {
    return [
      'social_media_links/fontawesome.component',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIconPath($icon_name, $style) {
    return NULL;
  }

}
