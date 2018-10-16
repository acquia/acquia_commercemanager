<?php

namespace Drupal\acm_sku;

/**
 * Provides an interface for category tree to taxonomy synchronization.
 *
 * @ingroup acm_sku
 */
interface CategoryRepositoryInterface {

  /**
   * LoadCategoryTerm.
   *
   * Load a Taxonomy term representing a category by commerce ID.
   *
   * @param int $commerce_id
   *   Commerce Backend ID.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   Return found term or null if not found.
   *
   * @throws \RuntimeException
   */
  public function loadCategoryTerm($commerce_id);

  /**
   * SetVocabulary.
   *
   * Set the vocabulary name of the taxonomy used for category sync.
   *
   * @param string $vocabulary
   *   Taxonomy vocabulary.
   *
   * @return self
   *   Return self.
   */
  public function setVocabulary($vocabulary);

}
