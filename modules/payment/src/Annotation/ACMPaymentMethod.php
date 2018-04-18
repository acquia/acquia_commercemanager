<?php

namespace Drupal\acm_payment\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ACM Checkout Pane item annotation object.
 *
 * @see \Drupal\acm_payment\CheckoutPaneManager
 * @see plugin_api
 *
 * @Annotation
 */
class ACMPaymentMethod extends Plugin {

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
