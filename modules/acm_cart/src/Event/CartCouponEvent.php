<?php

namespace Drupal\acm_cart\Event;

use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * Wraps a cart coupon event for event listeners.
 */
class CartCouponEvent extends SymfonyEvent {

  /**
   * The coupon.
   *
   * @var mixed
   */
  protected $coupon;

  /**
   * Constructs a cart coupon event object.
   *
   * @param mixed $coupon
   *   The coupon.
   */
  public function __construct($coupon) {
    $this->coupon = $coupon;
  }

  /**
   * Sets the coupon.
   *
   * @param mixed $coupon
   *   The coupon.
   */
  public function setCoupon($coupon) {
    $this->coupon = $coupon;
  }

  /**
   * Gets the coupon.
   *
   * @return array
   *   The coupon.
   */
  public function getCoupon() {
    return $this->coupon;
  }

}
