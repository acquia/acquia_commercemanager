<?php

namespace Drupal\acm_sku\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the SKU type entity.
 *
 * @ConfigEntityType(
 *   id = "acm_sku_type",
 *   label = @Translation("SKU type"),
 *   handlers = {
 *     "list_builder" = "Drupal\acm_sku\SKUTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\acm_sku\Form\SKUTypeForm",
 *       "edit" = "Drupal\acm_sku\Form\SKUTypeForm",
 *       "delete" = "Drupal\acm_sku\Form\SKUTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\acm_sku\SKUTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "type",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/config/sku/{acm_sku_type}",
 *     "add-form" = "/admin/commerce/config/sku/add",
 *     "edit-form" = "/admin/commerce/config/sku/{acm_sku_type}",
 *     "delete-form" = "/admin/commerce/config/sku/{acm_sku_type}/delete",
 *     "collection" = "/admin/commerce/config/sku"
 *   },
 *   bundle_of = "acm_sku"
 * )
 */
class SKUType extends ConfigEntityBundleBase implements SKUTypeInterface {

  /**
   * The Product type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Product type label.
   *
   * @var string
   */
  protected $label;

}
