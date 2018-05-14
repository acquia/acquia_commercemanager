<?php

namespace Drupal\acm_diagnostic;

/**
 * Provides an interface for mapping verification.
 *
 * @ingroup acm_diagnostic
 */
interface VerifyMappingInterface {

  /**
   * verifyMapping.
   *
   * Verifies the commerce connector mapping by checking the configuration
   *
   * @return array Array of results.
   *   Array of results.
   *
   * @internal param string $acmUuid
   *   Optional ACM UUID of the mapping to be checked, otherwise uses the
   *   X-ACM-UUID header
   */
  public function verify($acmUuId = '');

}