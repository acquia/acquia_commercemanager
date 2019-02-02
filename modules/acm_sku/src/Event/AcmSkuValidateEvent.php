<?php

namespace Drupal\acm_sku\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Wraps a acm sku validator event for event listeners.
 */
class AcmSkuValidateEvent extends Event {

  const ACM_SKU_VALIDATE = 'acm_sku.validate';

  /**
   * Product data being imported.
   *
   * @var array
   */
  protected $product;

  /**
   * Constructs an acm sku validator event.
   *
   * @param array $product
   *   Product that needs to be passed for processing.
   */
  public function __construct(array $product) {
    $this->product = $product;
  }

  /**
   * Get the inserted entity.
   *
   * @return array
   *   Product array.
   */
  public function getProduct() {
    return $this->product;
  }

  /**
   * Set the product to event object.
   *
   * @param array $product
   *   Array of product attributes.
   */
  public function setProduct(array $product) {
    $this->product = $product;
  }

}
