<?php

namespace Drupal\acm_cart\Event;

use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * Wraps a cart address event for event listeners.
 */
class CartAddressEvent extends SymfonyEvent {

  /**
   * The address.
   *
   * @var array
   */
  protected $address;

  /**
   * Constructs a cart address event object.
   *
   * @param array|object $address
   *   The address.
   */
  public function __construct($address) {
    $this->address = (object) $address;
  }

  /**
   * Sets the address.
   *
   * @param array|object $address
   *   The address.
   */
  public function setAddress($address) {
    $this->address = (object) $address;
  }

  /**
   * Gets the address.
   *
   * @return array
   *   The address.
   */
  public function getAddress() {
    return $this->address;
  }

}
