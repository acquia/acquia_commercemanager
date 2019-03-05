<?php

namespace Drupal\acm_sku;

/**
 * Provides an interface for category tree to taxonomy synchronization.
 *
 * @ingroup acm_sku
 */
interface CategoryManagerInterface {

  /**
   * SynchronizeTree.
   *
   * Synchronize a taxonomy vocabulary based on a commerce backend
   * category tree.
   *
   * @param string $vocabulary
   *   Vocabulary name for category tree.
   * @param int $remoteRoot
   *   Remote root category ID (optional)
   *
   * @return array
   *   Array of results.
   */
  public function synchronizeTree($vocabulary, $remoteRoot = NULL);

  /**
   * SynchronizeCategory.
   *
   * Synchronize a taxonomy vocabulary based on updated commerce
   * backend partial category tree (when a category is moved or
   * updated).
   *
   * @param string $vocabulary
   *   Vocabulary name for category tree.
   * @param array $categories
   *   Connector category tree data.
   * @param string $storeId
   *   Store ID.
   *
   * @return array
   *   Array of results.
   */
  public function synchronizeCategory($vocabulary, array $categories, $storeId = '');

  /**
   * Identify the categories which are not in commerce backend anymore.
   *
   * We will delete them.
   *
   * @param array $sync_categories
   *   Sync categories.
   *
   * @return array
   *   Orphan categories.
   */
  public function getOrphanCategories(array $sync_categories);

}
