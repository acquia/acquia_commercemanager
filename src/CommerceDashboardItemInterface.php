<?php

namespace Drupal\acm;

/**
 * Provides an interface defining a ACM Commerce Requirement plugin.
 *
 * @package acm
 */
interface CommerceDashboardItemInterface {

  /**
   * Returns the name of the requirement.
   *
   * @return string|null
   *   The name of the requirement.
   */
  public function title();

  /**
   * Returns the current value.
   *
   * @return array
   *   The current value.
   */
  public function value();

  /**
   * Returns render array.
   *
   * @return array
   *   Render array with title and value.
   */
  public function render();

}
