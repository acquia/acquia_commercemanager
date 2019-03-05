<?php

namespace Drupal\acm_customer\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an ACMCustomerPages item annotation object.
 *
 * @see \Drupal\acm_customer\CustomerPagesManager
 * @see plugin_api
 *
 * @Annotation
 */
class ACMCustomerPages extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
