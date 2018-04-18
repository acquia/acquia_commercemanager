<?php

namespace Drupal\acm_checkout\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ACM Checkout Pane item annotation object.
 *
 * @see \Drupal\acm_checkout\CheckoutFlowManager
 * @see plugin_api
 *
 * @Annotation
 */
class ACMCheckoutFlow extends Plugin {

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
