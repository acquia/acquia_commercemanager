<?php

namespace Drupal\acm_sku\AcquiaCommerce\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an SKU type annotation object.
 *
 * Plugin Namespace: Plugin\AcquiaCommerce/SKUType.
 *
 * @see plugin_api
 * @see hook_sku_type_info_alter()
 *
 * @Annotation
 */
class SKUType extends Plugin {

  /**
   * The SKU type plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the SKU Type plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description of the SKU Type plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
