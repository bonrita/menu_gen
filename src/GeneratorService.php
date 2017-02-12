<?php

namespace Drupal\chep_menugen;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Menu\MenuLinkTree;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GeneratorService.
 *
 * @package Drupal\chep_menugen
 */
class GeneratorService implements GeneratorServiceInterface {

  /**
   * Drupal\Core\Menu\MenuLinkTree definition.
   *
   * @var \Drupal\Core\Menu\MenuLinkTree
   */
  protected $menuLinkTree;

  /**
   * Drupal\Core\Entity\EntityManager definition.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * The menu storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuStorage;

  /**
   * The menu link storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuLinkContentStorage;

  /**
   * GeneratorService constructor.
   *
   * @param \Drupal\Core\Menu\MenuLinkTree $menu_link_tree
   *   Menu link tree instance.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_manager
   *   The entity manager instance.
   */
  public function __construct(MenuLinkTree $menu_link_tree, EntityTypeManager $entity_manager) {
    $this->menuLinkTree = $menu_link_tree;
    $this->entityManager = $entity_manager;
    $this->menuStorage = $entity_manager->getStorage('menu');
    $this->menuLinkContentStorage = $entity_manager->getStorage('menu_link_content');
  }

  /**
   * {@inheritdoc}
   */
  public function getSystemMenu() {
    $items = [];
    $file_path = $this->getConfiguredMenuFilePath();

    if (file_exists($file_path)) {
      $file_content = file_get_contents($file_path);
      $items = Yaml::parse($file_content);
    }

    return $items;
  }

  /**
   * Get the html form field path from the default theme.
   *
   * @return array
   *   The file path.
   */
  protected function getConfiguredMenuFilePath() {

//    $config = $this->configFactory->get('system.theme');
//    $default_theme = $config->get('default');
//    $file_name = "$default_theme.eloqua_forms.yml";

    $file_name = 'gen_menu.yml';
    $default_theme = 'chep_menugen';

    $theme_path = drupal_get_path('module', $default_theme);
    $file_path = "$theme_path/$file_name";

    return $file_path;
  }

}
