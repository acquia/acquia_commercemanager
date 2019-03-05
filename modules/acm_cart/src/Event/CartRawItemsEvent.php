<?php

namespace Drupal\acm_cart\Event;

use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * Wraps a cart raw items event for event listeners.
 */
class CartRawItemsEvent extends SymfonyEvent {

  /**
   * The items.
   *
   * @var array
   */
  protected $items;

  /**
   * Constructs a cart raw items event object.
   *
   * @param array $items
   *   The items.
   */
  public function __construct(array $items = []) {
    $this->items = $items;
  }

  /**
   * Sets the items.
   *
   * @param array $items
   *   The items.
   */
  public function setItems(array $items = []) {
    $this->items = $items;
  }

  /**
   * Gets the items.
   *
   * @return array
   *   The items.
   */
  public function getItems() {
    return $this->items;
  }

}
