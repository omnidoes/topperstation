<?php

namespace Drupal\page_manager\Routing;

use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\page_manager\PageVariantInterface;
use Drupal\page_manager\Entity\PageVariant;
use Drupal\page_manager\Plugin\DisplayVariant\PageBlockDisplayVariant;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a class for title callbacks.
 */
class TitleCallback implements ContainerInjectionInterface {

  /**
   * Controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * Constructs a new TitleCallback object.
   *
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controllerResolver
   *   Controller resolver.
   */
  public function __construct(ControllerResolverInterface $controllerResolver) {
    $this->controllerResolver = $controllerResolver;
  }

  /**
   * Title callback for page manager pages.
   *
   * @param \Drupal\page_manager\PageVariantInterface|string $page_manager_page_variant
   *   Page manager variant.
   * @param string $base_title_callback
   *   Base title callback.
   *
   * @return string
   *   The title.
   */
  public function title($page_manager_page_variant, $base_title_callback = NULL, Request $request) {
    if (!$page_manager_page_variant instanceof PageVariantInterface) {
      $page_manager_page_variant = PageVariant::load($page_manager_page_variant);
    } else {
      return $page_manager_page_variant->generateTitle();
    }
    $variant = $page_manager_page_variant->getVariantPlugin();
    if ($variant instanceof PageBlockDisplayVariant) {
      return $variant->generateTitle();
    }
    return $page_manager_page_variant->label();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('controller_resolver'));
  }

}
