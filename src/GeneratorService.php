<?php

namespace Drupal\chep_menugen;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Menu\MenuLinkTree;
use Drupal\system\Entity\Menu;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;

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
  protected $entityTypeManager;

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
   * The factory for entity queries.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryFactory;

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * GeneratorService constructor.
   *
   * @param \Drupal\Core\Menu\MenuLinkTree $menu_link_tree
   *   Menu link tree instance.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_manager
   *   The entity manager instance.
   */
  public function __construct(MenuLinkTree $menu_link_tree, EntityTypeManager $entity_manager, QueryFactory $entity_query_factory, MenuLinkManagerInterface $menu_link_manager) {
    $this->menuLinkTree = $menu_link_tree;
    $this->entityTypeManager = $entity_manager;
    $this->menuStorage = $entity_manager->getStorage('menu');
    $this->menuLinkContentStorage = $entity_manager->getStorage('menu_link_content');

    $this->entityQueryFactory = $entity_query_factory;
    $this->menuLinkManager = $menu_link_manager;
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

    $file_name = 'gen_menu.yml';
    $default_theme = 'chep_menugen';

    $theme_path = drupal_get_path('module', $default_theme);
    $file_path = "$theme_path/$file_name";

    return $file_path;
  }

  /**
   * Create a new entity.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param null|string $bundle_key
   *   The bunndle key.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity instance.
   */
  public function getNewEntity($entity_type_id, $bundle_key = NULL) {
    $values = [];

    if ($bundle_key) {
      $values[$bundle_key] = $bundle_key;
    }

    $entity = $this->entityTypeManager->getStorage($entity_type_id)
      ->create($values);

    return $entity;
  }

  /**
   * Create a menu config object.
   *
   * @param string $id
   *   The menu id.
   * @param array $properties
   *   The menu properties e.g label.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityBase
   *   The menu config object.
   */
  public function createMenu($id, array $properties) {
    if (empty($properties['label']) || empty($id)) {
      throw new \InvalidArgumentException('You must provide a label for the menu.');
    }

    $langcode = !empty($properties['lang']) ? $properties['lang'] : 'en';
    $id = $this->transliterateMenuId($id, $langcode);

    $values = array(
      'label' => $properties['label'],
      'id' => $id,
      'description' => !empty($properties['summary']) ? $properties['summary'] : '',
      'langcode' => $langcode,
    );

    if ($this->menuNameExists($id)) {
      return $this->entityTypeManager->getStorage('menu')->load($id);
    }

    $entity = $this->getNewEntity('menu');

    foreach ($values as $key => $value) {
      $entity->set($key, $value);
    }

    $entity->save();
    return $entity;
  }

  /**
   * Returns whether a menu name already exists.
   *
   * @param string $value
   *   The name of the menu.
   *
   * @return bool
   *   Returns TRUE if the menu already exists, FALSE otherwise.
   */
  public function menuNameExists($value) {
    // Check first to see if a menu with this ID exists.
    if ($this->entityQueryFactory->get('menu')
      ->condition('id', $value)
      ->range(0, 1)
      ->count()
      ->execute()
    ) {
      return TRUE;
    }

    // Check for a link assigned to this menu.
    return $this->menuLinkManager->menuNameInUse($value);
  }

  /**
   * {@inheritdoc}
   */
  public function generateMenuStructure() {
    $items = $this->getSystemMenu();

    foreach ($items as $menu_id => $item_value) {
      $menu_entity = $this->createMenu($menu_id, $item_value);
      $this->generateMenuLinks($menu_entity, $item_value['links']);
    }

  }

  /**
   * Generate menu links.
   *
   * @param \Drupal\system\Entity\Menu $menu
   *   The menu config entity instance.
   * @param array $links
   *   A list of links to add to the menu.
   * @param null|\Drupal\menu_link_content\Entity\MenuLinkContent $parent
   *   The parent menu link.
   */
  protected function generateMenuLinks(Menu $menu, array $links, $parent = NULL) {

    foreach ($links as $link => $properties) {
      // Save the link.
      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link */
      $menu_link = $this->createLink($menu, $link, $properties['path'], $properties, $parent);

      if (!empty($properties['links'])) {
        // Process the children.
        $this->generateMenuLinks($menu, $properties['links'], $menu_link);
      }
    }
  }

  /**
   * Create a link.
   *
   * @param \Drupal\system\Entity\Menu $menu
   *   The menu config entity instance.
   * @param string $title
   *   The title of the menu.
   * @param string $link_path
   *   The link path e.g internal:/node/2, route:<nolink>, route:<front>,
   *   https://www.drupal.org.
   * @param array $properties
   *   A list of menu properties.
   * @param null|\Drupal\menu_link_content\Entity\MenuLinkContent $parent
   *   The parent menu link.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent
   *   The menu link entity.
   */
  public function createLink(Menu $menu, $title, $link_path, array $properties, $parent = NULL) {

    $values = [
      'title' => $title,
      'link' => ['uri' => $link_path],
      'menu_name' => $menu->id(),
      'weight' => $properties['weight'] ? $properties['weight'] : 0,
    ];

    if ($parent && $parent instanceof MenuLinkContent) {
      $values['parent'] = "menu_link_content:{$parent->uuid()}";
    }

    /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
    $entity = MenuLinkContent::create($values);

    if (!empty($properties['attributes'])) {
      $this->addMenuLinkAttributes($entity, ['attributes' => $properties['attributes']]);
    }

    $entity->save();
    return $entity;
  }

  /**
   * Add menu link attributes.
   *
   * @param \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link
   *   The menu link instance.
   * @param array $attributes
   *   A list of link attributes.
   */
  public function addMenuLinkAttributes(MenuLinkContent $menu_link, array $attributes) {
    if (\Drupal::moduleHandler()->moduleExists('menu_link_attributes')) {
      // @TODO Check if the attributes were configured and validate the
      // @TODO attribute values.
      $menu_link_options = $menu_link->link->first()->options;
      $menu_link->link->first()->options = array_merge($menu_link_options, $attributes);
    }
  }

  /**
   * Transliterate the menu id.
   *
   * @param string $menu_id
   *   The menu id.
   * @param string $langcode
   *   The language code.
   *
   * @return string
   *   The transliterated string.
   */
  public function transliterateMenuId($menu_id, $langcode = 'en') {
    /** @var \Drupal\Component\Transliteration\TransliterationInterface $transliteration */
    $transliteration = \Drupal::service('transliteration');

    $replace_pattern = '[^a-z0-9-]+';
    $replace = '-';

    $transliterated = $transliteration->transliterate($menu_id, $langcode, '_');
    $transliterated = Unicode::strtolower($transliterated);

    // Quote the pattern delimiter and remove null characters to avoid the e
    // or other modifiers being injected.
    $transliterated = preg_replace('@' . strtr($replace_pattern, [
        '@' => '\@',
        chr(0) => '',
      ]) . '@', $replace, $transliterated);

    return $transliterated;
  }

}
