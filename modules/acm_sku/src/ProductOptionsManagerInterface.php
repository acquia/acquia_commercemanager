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

  /**
   * Create product option if not available or update the name.
   *
   * @param string $langcode
   *   Lang code.
   * @param int $option_id
   *   Option id.
   * @param string $option_value
   *   Value (term name).
   * @param int $attribute_id
   *   Attribute id.
   * @param string $attribute_code
   *   Attribute code.
   * @param int $weight
   *   Taxonomy term weight == attribute option sort order.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   Term object or null.
   */
  public function createProductOptionWrapper($langcode, $option_id, $option_value, $attribute_id, $attribute_code, $weight);

  /**
   * Delete all the options that are no longer available.
   *
   * @param array $synced_options
   *   Multi-dimensional array containing attribute codes as key and option ids
   *   as value.
   */
  public function deleteUnavailableOptions(array $synced_options);

}
