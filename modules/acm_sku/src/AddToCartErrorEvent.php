<?php

namespace Drupal\acm_sku;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class AddToCartErrorEvent.
 *
 * @package Drupal\acm_sku
 */
class AddToCartErrorEvent extends Event {

  const SUBMIT = 'addToCart.submit.error';

  /**
   * The PHP exception we throw from SKU add to cart forms.
   *
   * @var \Exception
   */
  protected $exception;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Exception $exception) {
    $this->exception = $exception;
  }

  /**
   * Get The exception.
   *
   * @return \Exception
   *   Exception object which contains code and message.
   */
  public function getEventException() {
    return $this->exception;
  }

  /**
   * A Event description method for info.
   */
  public function myEventDescription() {
    return "This event occurs when there is a exception in add to cart.";
  }

}
