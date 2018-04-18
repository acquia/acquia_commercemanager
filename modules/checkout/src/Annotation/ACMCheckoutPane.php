<?php

namespace Drupal\acm_checkout\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ACM Checkout Pane item annotation object.
 *
 * @see \Drupal\acm_checkout\CheckoutPaneManager
 * @see plugin_api
 *
 * @Annotation
 */
class ACMCheckoutPane extends Plugin {

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

  /**
   * The ID of the default step for this pane.
   *
   * Optional. If missing, the pane will be disabled by default.
   *
   * @var string
   */
  public $defaultStep;

  /**
   * The wrapper element to use when rendering the pane's form.
   *
   * E.g: 'container', 'fieldset'. Defaults to 'container'.
   *
   * @var string
   */
  public $wrapperElement;

  /**
   * Constructs a new CommerceCheckoutPane object.
   *
   * @param array $values
   *   The annotation values.
   */
  public function __construct(array $values) {
    if (empty($values['adminLabel'])) {
      $values['adminLabel'] = $values['label'];
    }
    parent::__construct($values);
  }

}
