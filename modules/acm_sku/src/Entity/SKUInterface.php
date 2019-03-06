<?php

namespace Drupal\acm_sku\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a SKU entity.
 *
 * @ingroup acm
 */
interface SKUInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Get plugin instance for current object.
   *
   * @return null|object
   *   Returns a plugin instance if one exists.
   */
  public function getPluginInstance();

  /**
   * Returns the locale-aware display formatted price like this '$1,234.56'.
   *
   * Calls the number price formatting function of the SKU Type.
   *
   * @param bool $returnOriginalPrice
   *   Indicates if the original price should be returned, normally the final
   *   price is returned.
   *
   * @return string
   *   Formatted price string.
   */
  public function getAdminGridDisplayFormattedPrice(bool $returnOriginalPrice = FALSE);

  /**
   * Returns the locale-aware display formatted price like this '$1,234.56'.
   *
   * Calls the number price formatting function of the SKU Type.
   *
   * @param bool $returnOriginalPrice
   *   Indicates if the original price should be returned, normally the final
   *   price is returned.
   *
   * @return string|array
   *   Get locale-aware formatted prices with currency, if there are any
   *   related products, min and max price is returned in an array.
   */
  public function getNumberFormattedPrice(bool $returnOriginalPrice = FALSE);

  /**
   * Refresh stock for the sku using stock api.
   */
  public function refreshStock();

}
