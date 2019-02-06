<?php

namespace Drupal\acm_sku;

/**
 * Provides an interface for product synchronization.
 *
 * @ingroup acm_sku
 */
interface ProductManagerInterface {

  /**
   * SynchronizeProducts.
   *
   * Syncs an array of product data.
   *
   * @param array $products
   *   Product / SKU Data.
   * @param string $storeId
   *   Store ID from header.
   *
   * @return array
   *   Array of results.
   */
  public function synchronizeProducts(array $products = [], $storeId = '');

}
