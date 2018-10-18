<?php

namespace Drupal\acm_cart\Event;

use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * Wraps a cart push event for event listeners.
 */
class CartPushEvent extends SymfonyEvent {

  /**
   * The raw cart.
   *
   * @var object
   */
  protected $rawCart;

  /**
   * Constructs a cart event object.
   *
   * @param object $raw_cart
   *   The raw cart object about to be sent to the connector.
   */
  public function __construct($raw_cart) {
    $this->rawCart = $raw_cart;
  }

  /**
   * Sets the raw cart object.
   *
   * @param object $raw_cart
   *   The raw cart object about to be sent to the connector.
   */
  public function setRawCart($raw_cart) {
    $this->rawCart = $raw_cart;
  }

  /**
   * Gets the raw cart object.
   *
   * @return \Drupal\acm_cart\CartInterface
   *   The cart object.
   */
  public function getRawCart() {
    return $this->rawCart;
  }

}
