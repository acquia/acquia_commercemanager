<?php

namespace Drupal\acm_checkout\Plugin\CheckoutFlow;

/**
 * Defines the interface for checkout flows which have panes.
 */
interface CheckoutFlowWithPanesInterface extends CheckoutFlowInterface {

  /**
   * Gets the checkout flow's panes.
   *
   * @param string $step_id
   *   (Optional) The step ID to filter on.
   *
   * @return \Drupal\acm_checkout\Plugin\CheckoutPane\CheckoutPaneInterface[]
   *   The panes, keyed by pane id, ordered by weight.
   */
  public function getPanes($step_id = NULL);

}
