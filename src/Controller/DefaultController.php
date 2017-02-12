<?php

namespace Drupal\chep_menugen\Controller;

use Drupal\chep_menugen\GeneratorService;
use Drupal\Core\Controller\ControllerBase;

/**
 * Class DefaultController.
 *
 * @package Drupal\chep_menugen\Controller
 */
class DefaultController extends ControllerBase {

  /**
   * Hello.
   *
   * @return string
   *   Return Hello string.
   */
  public function hello() {

    /** @var GeneratorService $generator */
    $generator = \Drupal::service('chep_menugen.generator');
    $items = $generator->getSystemMenu();
    $gg = 0;
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: hello')
    ];
  }

}
