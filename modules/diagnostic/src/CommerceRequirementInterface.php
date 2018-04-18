<?php

namespace Drupal\acm_diagnostic;

/**
 * Provides an interface defining a ACM Commerce Requirement plugin.
 *
 * @package acm_diagnostic
 */
interface CommerceRequirementInterface {

  /**
   * Returns the name of the requirement.
   *
   * @return string
   *   The name of the requirement.
   */
  public function title();

  /**
   * Returns the current value.
   *
   * @return string
   *   The current value.
   */
  public function value();

  /**
   * Returns the description of the requirement/status.
   *
   * @return string
   *   The description of the requirement/status.
   */
  public function description();

  /**
   * Returns the severity of the requirement/status.
   *
   * @return int
   *   The requirement status code:
   *     - REQUIREMENT_INFO: For info only.
   *     - REQUIREMENT_OK: The requirement is satisfied.
   *     - REQUIREMENT_WARNING: The requirement failed with a warning.
   *     - REQUIREMENT_ERROR: The requirement failed with an error.
   */
  public function severity();

}
