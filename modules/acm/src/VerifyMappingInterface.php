<?php

namespace Drupal\acm;

/**
 * Provides an interface for mapping verification.
 *
 * @ingroup acm
 */
interface VerifyMappingInterface {

  /**
   * Verifies the commerce connector mapping by checking the configuration.
   *
   * @param string $acmUuId
   *   Optional ACM UUID of the mapping to be checked, otherwise uses the
   *   X-ACM-UUID header.
   *
   * @return array
   *   Array of results.
   */
  public function verify($acmUuId = '');

}
