<?php

namespace Drupal\acm_customer\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an ACMCustomerForm item annotation object.
 *
 * @see \Drupal\acm_customer\CustomerFormManager
 * @see plugin_api
 *
 * @Annotation
 */
class ACMCustomerForm extends Plugin {

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
   * The ID of the default page this form is on.
   *
   * Optional. If missing, the form will be disabled by default.
   *
   * @var string
   */
  public $defaultPage;

  /**
   * The wrapper element to use when rendering the form.
   *
   * E.g: 'container', 'fieldset'. Defaults to 'container'.
   *
   * @var string
   */
  public $wrapperElement;

}
