<?php

namespace Drupal\Tests\acm\Unit\Connector;

use Drupal\acm\Connector\TestAPIWrapper;

/**
 * Mock CartStorage.
 *
 * @TODO: Have this actually hit the API eventually. Just returns mock data
 * right now.
 */
class MockAPIWrapper extends TestAPIWrapper {

  /**
   * Storage for the last used cart id.
   *
   * @var int
   */
  protected $lastCartId = 0;

  /**
   * Constructor override.
   */
  public function __construct() {}

  /**
   * {@inheritdoc}
   */
  public function updateStoreContext($store_id){}

  /**
   * Generates a new cart id.
   *
   * @return int
   *   The generated cart id.
   */
  protected function generateCartId() {
    $this->lastCartId += 1;
    return $this->lastCartId;
  }

  /**
   * {@inheritdoc}
   */
  public function createCart($customer_id = NULL) {
    return [
      'shippable' => TRUE,
      'cart_id' => $this->generateCartId(),
      'store_id' => 987,
      'customer_id' => $customer_id,
      'customer_email' => 'test@test.com',
      'totals' => 999.99,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCart($cart_id, $customer_id = NULL) {
    return $this->createCart();
  }

  /**
   * {@inheritdoc}
   */
  public function updateCart($cart_id, $update) {
    return $this->createCart();
  }

  /**
   * {@inheritdoc}
   */
  public function associateCart($cart_id, $customer_id) {
    return TRUE;
  }

}
