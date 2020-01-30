<?php

namespace Drupal\menu_block\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Entity\Menu;
use Drupal\system\Plugin\Block\SystemMenuBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Provides an extended Menu block.
 *
 * @Block(
 *   id = "menu_block",
 *   admin_label = @Translation("Menu block"),
 *   category = @Translation("Menus"),
 *   deriver = "Drupal\menu_block\Plugin\Derivative\MenuBlock"
 * )
 */
class MenuBlock extends SystemMenuBlock {

  /**
   * Constant definition options for block label type.
   */
  const LABEL_BLOCK = 'block';
  const LABEL_MENU = 'menu';
  const LABEL_ACTIVE_ITEM = 'active_item';
  const LABEL_PARENT = 'parent';
  const LABEL_ROOT = 'root';
  const LABEL_FIXED_PARENT = 'fixed_parent';

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $active_trail, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $menu_tree);
    $this->menuActiveTrail = $active_trail;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $label = $this->getBlockLabel() ?: $this->label();
    $this->setConfigurationValue('label', $label);
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;
    $defaults = $this->defaultConfiguration();

    $form = parent::blockForm($form, $form_state);

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced options'),
      '#open' => FALSE,
      '#process' => [[get_class(), 'processMenuBlockFieldSets']],
    ];

    $form['advanced']['expand'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Expand all menu links</strong>'),
      '#default_value' => $config['expand'],
      '#description' => $this->t('All menu links that have children will "Show as expanded".'),
    ];

    $menu_name = $this->getDerivativeId();
    $menus = Menu::loadMultiple([$menu_name]);
    $menus[$menu_name] = $menus[$menu_name]->label();

    /** @var \Drupal\Core\Menu\MenuParentFormSelectorInterface $menu_parent_selector */
    $menu_parent_selector = \Drupal::service('menu.parent_form_selector');
    $form['advanced']['parent'] = $menu_parent_selector->parentSelectElement($config['parent'], '', $menus);

    $form['advanced']['parent'] += [
      '#title' => $this->t('Fixed parent item'),
      '#description' => $this->t('Alter the options in “Menu levels” to be relative to the fixed parent item. The block will only contain children of the selected menu link.'),
    ];

    $form['advanced']['label_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Use as title'),
      '#description' => $this->t('Replace the block title with an item from the menu.'),
      '#options' => [
        self::LABEL_BLOCK => $this->t('Block title'),
        self::LABEL_MENU => $this->t('Menu title'),
        self::LABEL_FIXED_PARENT => $this->t("Fixed parent item's title"),
        self::LABEL_ACTIVE_ITEM => $this->t("Active item's title"),
        self::LABEL_PARENT => $this->t("Active trail's parent title"),
        self::LABEL_ROOT => $this->t("Active trail's root title"),
      ],
      '#default_value' => $config['label_type'],
      '#states' => [
        'visible' => [
          ':input[name="settings[label_display]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['style'] = [
      '#type' => 'details',
      '#title' => $this->t('HTML and style options'),
      '#open' => FALSE,
      '#process' => [[get_class(), 'processMenuBlockFieldSets']],
    ];

    $form['style']['suggestion'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Theme hook suggestion'),
      '#default_value' => $config['suggestion'],
      '#field_prefix' => '<code>menu__</code>',
      '#description' => $this->t('A theme hook suggestion can be used to override the default HTML and CSS classes for menus found in <code>menu.html.twig</code>.'),
      '#machine_name' => [
        'error' => $this->t('The theme hook suggestion must contain only lowercase letters, numbers, and underscores.'),
        'exists' => [$this, 'suggestionExists'],
      ],
    ];

    // Open the details field sets if their config is not set to defaults.
    foreach (['menu_levels', 'advanced', 'style'] as $fieldSet) {
      foreach (array_keys($form[$fieldSet]) as $field) {
        if (isset($defaults[$field]) && $defaults[$field] !== $config[$field]) {
          $form[$fieldSet]['#open'] = TRUE;
        }
      }
    }

    return $form;
  }

  /**
   * Form API callback: Processes the elements in field sets.
   *
   * Adjusts the #parents of field sets to save its children at the top level.
   */
  public static function processMenuBlockFieldSets(&$element, FormStateInterface $form_state, &$complete_form) {
    array_pop($element['#parents']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['level'] = $form_state->getValue('level');
    $this->configuration['depth'] = $form_state->getValue('depth');
    $this->configuration['expand'] = $form_state->getValue('expand');
    $this->configuration['parent'] = $form_state->getValue('parent');
    $this->configuration['suggestion'] = $form_state->getValue('suggestion');
    $this->configuration['label_type'] = $form_state->getValue('label_type');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $menu_name = $this->getDerivativeId();
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);

    // Adjust the menu tree parameters based on the block's configuration.
    $level = $this->configuration['level'];
    $depth = $this->configuration['depth'];
    $expand = $this->configuration['expand'];
    $parent = $this->configuration['parent'];
    $suggestion = $this->configuration['suggestion'];

    $parameters->setMinDepth($level);
    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(min($level + $depth - 1, $this->menuTree->maxDepth()));
    }

    // For menu blocks with start level greater than 1, only show menu items
    // from the current active trail. Adjust the root according to the current
    // position in the menu in order to determine if we can show the subtree.
    // If we're using a fixed parent item, we'll skip this step.
    $fixed_parent_menu_link_id = str_replace($menu_name . ':', '', $parent);
    if ($level > 1 && !$fixed_parent_menu_link_id) {
      if (count($parameters->activeTrail) >= $level) {
        // Active trail array is child-first. Reverse it, and pull the new menu
        // root based on the parent of the configured start level.
        $menu_trail_ids = array_reverse(array_values($parameters->activeTrail));
        $menu_root = $menu_trail_ids[$level - 1];
        $parameters->setRoot($menu_root)->setMinDepth(1);
        if ($depth > 0) {
          $max_depth = min($level - 1 + $depth - 1, $this->menuTree->maxDepth());
          $parameters->setMaxDepth($max_depth);
        }
      }
      else {
        return [];
      }
    }

    // If expandedParents is empty, the whole menu tree is built.
    if ($expand) {
      $parameters->expandedParents = [];
    }
    // When a fixed parent item is set, root the menu tree at the given ID.
    if ($fixed_parent_menu_link_id) {
      $parameters->setRoot($fixed_parent_menu_link_id);

      // If the starting level is 1, we always want the child links to appear,
      // but the requested tree may be empty if the tree does not contain the
      // active trail.
      if ($level === 1 || $level === '1') {
        // Check if the tree contains links.
        $tree = $this->menuTree->load($menu_name, $parameters);
        if (empty($tree)) {
          // Change the request to expand all children and limit the depth to
          // the immediate children of the root.
          $parameters->expandedParents = [];
          $parameters->setMinDepth(1);
          $parameters->setMaxDepth(1);
          // Re-load the tree.
          $tree = $this->menuTree->load($menu_name, $parameters);
        }
      }
    }

    // Load the tree if we haven't already.
    if (!isset($tree)) {
      $tree = $this->menuTree->load($menu_name, $parameters);
    }
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build = $this->menuTree->build($tree);

    if (!empty($build['#theme'])) {
      // Add the configuration for use in menu_block_theme_suggestions_menu().
      $build['#menu_block_configuration'] = $this->configuration;
      // Remove the menu name-based suggestion so we can control its precedence
      // better in menu_block_theme_suggestions_menu().
      $build['#theme'] = 'menu';
    }

    $build['#contextual_links']['menu'] = [
      'route_parameters' => ['menu' => $menu_name],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'level' => 1,
      'depth' => 0,
      'expand' => 0,
      'parent' => $this->getDerivativeId() . ':',
      'suggestion' => strtr($this->getDerivativeId(), '-', '_'),
      'label_type' => self::LABEL_BLOCK,
    ];
  }

  /**
   * Checks for an existing theme hook suggestion.
   *
   * @return bool
   *   Returns FALSE because there is no need of validation by unique value.
   */
  public function suggestionExists() {
    return FALSE;
  }

  /**
   * Get the configured block label.
   *
   * @return string
   *   The configured label.
   */
  public function getBlockLabel() {
    switch ($this->configuration['label_type']) {
      case self::LABEL_MENU:
        return $this->getMenuTitle();

      case self::LABEL_ACTIVE_ITEM:
        return $this->getActiveItemTitle();

      case self::LABEL_PARENT:
        return $this->getActiveTrailParentTitle();

      case self::LABEL_ROOT:
        return $this->getActiveTrailRootTitle();

      case self::LABEL_FIXED_PARENT:
        return $this->getFixedParentItemTitle();

      default:
        return $this->label();
    }
  }

  /**
   * Get the label of the configured menu.
   *
   * @return string|null
   *   Menu label or null if no menu exists.
   */
  protected function getMenuTitle() {
    try {
      $menu = $this->entityTypeManager->getStorage('menu')
        ->load($this->getDerivativeId());
    }
    catch (\Exception $e) {
      return NULL;
    }

    return $menu ? $menu->label() : NULL;
  }

  /**
   * @return string
   */
  protected function getFixedParentItemTitle() {
    $parent = $this->configuration['parent'];

    if ($parent) {
      $fixed_parent_menu_link_id = str_replace($this->getDerivativeId() . ':', '', $parent);
      return $this->getLinkTitleFromLink($fixed_parent_menu_link_id);
    }
  }

  /**
   * Get the active menu item's title.
   *
   * @return string
   *   Current menu item title.
   */
  protected function getActiveItemTitle() {
    $active_trail_ids = $this->getDerivativeActiveTrailIds();
    if ($active_trail_ids) {
      return $this->getLinkTitleFromLink(reset($active_trail_ids));
    }
  }

  /**
   * Get the current menu item's parent menu title.
   *
   * @return string
   *   The menu item title.
   */
  protected function getActiveTrailParentTitle() {
    $active_trail_ids = $this->getDerivativeActiveTrailIds();

    if ($active_trail_ids) {
      if (count($active_trail_ids) === 1) {
        return $this->getActiveItemTitle();
      }
      return $this->getLinkTitleFromLink(next($active_trail_ids));
    }
  }

  /**
   * Get the current menu item's root menu item title.
   *
   * @return string
   *   The menu item title.
   */
  protected function getActiveTrailRootTitle() {
    $active_trail_ids = $this->getDerivativeActiveTrailIds();

    if ($active_trail_ids) {
      return $this->getLinkTitleFromLink(end($active_trail_ids));
    }
  }

  /**
   * Get an array of the active trail menu link items.
   *
   * @return array
   *   The active trail.
   */
  protected function getDerivativeActiveTrailIds() {
    $menu_id = $this->getDerivativeId();
    return array_filter($this->menuActiveTrail->getActiveTrailIds($menu_id));
  }

  /**
   * Given a menu item ID, get that item's title.
   *
   * @param string $link_id
   *   Menu Item ID.
   *
   * @return string
   *   The menu item title.
   */
  protected function getLinkTitleFromLink($link_id) {
    $parameters = new MenuTreeParameters();
    $menu = $this->menuTree->load($this->getDerivativeId(), $parameters);
    if ($link = $this->findLinkInTree($menu, $link_id)) {
      return $link->link->getTitle();
    }
  }

  /**
   * Find and return the menu link item from the menu tree.
   *
   * @param array $menu_tree
   *   Associative array containing the menu link tree data.
   * @param string $link_id
   *   Menu link id to find.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement
   *   The link element from the given menu tree.
   */
  protected function findLinkInTree(array $menu_tree, $link_id) {
    if (isset($menu_tree[$link_id])) {
      return $menu_tree[$link_id];
    }
    /** @var \Drupal\Core\Menu\MenuLinkTreeElement $link */
    foreach ($menu_tree as $link) {
      if ($link = $this->findLinkInTree($link->subtree, $link_id)) {
        return $link;
      }
    }
  }

}
