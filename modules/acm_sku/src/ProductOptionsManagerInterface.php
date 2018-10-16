<?php

namespace Drupal\acm_sku;

/**
 * Provides a service for product options data to taxonomy synchronization.
 *
 * @ingroup acm_sku
 */
interface ProductOptionsManagerInterface {

  /**
   * Load existing term (if available).
   *
   * @param string $attribute_code
   *   Attribute code - Magento value.
   * @param int $option_id
   *   Option id - Magento value.
   * @param string $langcode
   *   The language to focus on.
   * @param bool $log_error
   *   Flag to stop logging term not found errors during sync.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   Loaded taxonomy term object if found.
   */
  public function loadProductOptionByOptionId($attribute_code, $option_id, $langcode, $log_error = TRUE);

  /**
   * Synchronize all product options.
   */
  public function synchronizeProductOptions();

}
