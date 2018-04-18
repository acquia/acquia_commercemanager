<?php

namespace Drupal\acm_diagnostic\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Commerce Requirement annotation object.
 *
 * @Annotation
 */
class CommerceRequirement extends Plugin {

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

}
