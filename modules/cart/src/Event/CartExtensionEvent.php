<?php

namespace Drupal\acm_cart\Event;

use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * Wraps a cart extension event for event listeners.
 */
class CartExtensionEvent extends SymfonyEvent {

  /**
   * The extension key.
   *
   * @var string
   */
  protected $key;

  /**
   * The extension value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * Constructs a cart extension event object.
   *
   * @param string $key
   *   The extension key.
   * @param mixed $value
   *   The extension value.
   */
  public function __construct($key, $value) {
    $this->key = $key;
    $this->value = $value;
  }

  /**
   * Sets the extension key.
   *
   * @param string $key
   *   The extension key.
   */
  public function setKey($key) {
    $this->key = $key;
  }

  /**
   * Sets the extension value.
   *
   * @param mixed $value
   *   The extension value.
   */
  public function setValue($value) {
    $this->value = $value;
  }

  /**
   * Gets the extension key.
   *
   * @return string
   *   The extension key.
   */
  public function getKey() {
    return $this->key;
  }

  /**
   * Gets the extension value.
   *
   * @return mixed
   *   The extension value.
   */
  public function getValue() {
    return $this->value;
  }

}
