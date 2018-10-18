<?php

namespace Drupal\acm\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Commerce Requirement annotation object.
 *
 * @Annotation
 */
class CommerceDashboardItem extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable title of the requirement.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * Weight of item.
   *
   * @var int
   */
  public $weight;

  /**
   * Group in which we want to display item.
   *
   * Currently supported - tile, line.
   *
   * @var string
   */
  public $group = 'line';

}
