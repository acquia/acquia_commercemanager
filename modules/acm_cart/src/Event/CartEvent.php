<?php

namespace Drupal\acm_cart\Event;

use Drupal\acm_cart\CartInterface;
use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * Wraps a cart event for event listeners.
 */
class CartEvent extends SymfonyEvent {

  /**
   * The cart.
   *
   * @var \Drupal\acm_cart\CartInterface
   */
  protected $cart;

  /**
   * Constructs a cart event object.
   *
   * @param \Drupal\acm_cart\CartInterface $cart
   *   The current cart.
   */
  public function __construct(CartInterface $cart) {
    $this->cart = $cart;
  }

  /**
   * Sets the cart object.
   *
   * @param \Drupal\acm_cart\CartInterface $cart
   *   The current cart.
   */
  public function setCart(CartInterface $cart) {
    $this->cart = $cart;
  }

  /**
   * Gets the cart object.
   *
   * @return \Drupal\acm_cart\CartInterface
   *   The cart object.
   */
  public function getCart() {
    return $this->cart;
  }

}
