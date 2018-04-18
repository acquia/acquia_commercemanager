<?php

namespace Drupal\acm_cart\Event;

use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * Wraps a cart item event for event listeners.
 */
class CartItemEvent extends SymfonyEvent {

  /**
   * The item sku.
   *
   * @var string
   */
  protected $sku;

  /**
   * The item quantity.
   *
   * @var int
   */
  protected $quantity;

  /**
   * The item extension.
   *
   * @var array
   */
  protected $extension;

  /**
   * Constructs a cart item event object.
   *
   * @param string $sku
   *   The item sku.
   * @param int $quantity
   *   The item quantity.
   * @param array $extension
   *   The item extension.
   */
  public function __construct($sku, $quantity = 0, array $extension = []) {
    $this->sku = $sku;
    $this->quantity = $quantity;
    $this->extension = $extension;
  }

  /**
   * Sets the item sku.
   *
   * @param string $sku
   *   The item sku.
   */
  public function setSku($sku) {
    $this->sku = $sku;
  }

  /**
   * Sets the item quantity.
   *
   * @param int $quantity
   *   The item quantity.
   */
  public function setQuantity($quantity = 0) {
    $this->quantity = $quantity;
  }

  /**
   * Sets the item extension.
   *
   * @param array $extension
   *   The item extension.
   */
  public function setExtension(array $extension = []) {
    $this->extension = $extension;
  }

  /**
   * Gets the item sku.
   *
   * @return string
   *   The item sku.
   */
  public function getSku() {
    return $this->sku;
  }

  /**
   * Gets the item quantity.
   *
   * @return int
   *   The item quantity.
   */
  public function getQuantity() {
    return $this->quantity;
  }

  /**
   * Gets the item extension.
   *
   * @return array
   *   The item extension.
   */
  public function getExtension() {
    return $this->extension;
  }

}
