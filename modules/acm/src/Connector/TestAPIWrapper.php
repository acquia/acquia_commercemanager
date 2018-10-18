<?php

namespace Drupal\acm\Connector;

/**
 * TestAPIWrapper class.
 */
class TestAPIWrapper extends APIWrapper {

  /**
   * Stores the last generated product id.
   *
   * @var int
   */
  private $lastProductId = 0;

  /**
   * Stores the last generated order id.
   *
   * @var int
   */
  private $lastOrderId = 0;

  /**
   * {@inheritdoc}
   */
  public function createCart($customer_id = NULL) {
    $cart = $this->generateOrder($customer_id, 'in progress');
    return $cart;
  }

  /**
   * {@inheritdoc}
   */
  public function getCart($cart_id, $customer_id = NULL) {
    $cart = $this->generateOrder($cart_id, 'in progress');
    return $cart;
  }

  /**
   * {@inheritdoc}
   */
  public function updateCart($cart_id, $cart) {
    $cart = $this->generateOrder($customer_id, 'in progress');
    return $cart;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomer($email, $throwRouteException = TRUE) {
    return [
      'customer_id' => 1,
      'store_id' => 1,
      'group_id' => 1,
      'email' => 'test1@example.com',
      'firstname' => 'Test',
      'lastname' => 'Testerson',
      'title' => 'Mr',
      'dob' => '1989-09-12',
      'created' => '2017-09-11 16:57:46',
      'updated' => '2017-09-12 03:38:21',
      'addresses' => [
        [
          'address_id' => 1,
          'firstname' => 'Test',
          'lastname' => 'Testerson',
          'street' => '100 Test Ave',
          'street2' => 'Apt 100',
          'city' => 'Cranston',
          'region' => 'Rhode Island',
          'region_id' => 32,
          'postcode' => '02920',
          'country_id' => 'US',
          'telephone' => '5555555555',
          'default_billing' => TRUE,
          'default_shipping' => FALSE,
          'customer_address_id' => 1,
          'customer_id' => 1,
          'extension' => [
            'test_custom_field' => 'test_val',
          ],
        ], [
          'address_id' => 2,
          'firstname' => 'Test',
          'lastname' => 'Testerson',
          'street' => '200 Test Boulevard',
          'street2' => '',
          'city' => 'Denver',
          'region' => 'Colorado',
          'region_id' => 32,
          'postcode' => '80014',
          'country_id' => 'US',
          'telephone' => '5555555555',
          'default_billing' => FALSE,
          'default_shipping' => TRUE,
          'customer_address_id' => 2,
          'customer_id' => 1,
          'extension' => [
            'test_custom_field' => 'test_val',
          ],
        ],
      ],
      'extension' => [
        'test_custom_field' => 'test_val',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerOrders($email, $order_id = NULL) {
    $orders = [];

    for ($i = 0; $i < rand(1, 6); $i++) {
      $orders[] = $this->generateOrder($email);
    }

    return $orders;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingEstimates($cart_id, $address, $customer_id = NULL) {
    return [
      [
        'carrier_code'  => 'testcarrier',
        'carrier_title' => 'Test Carrier',
        'method_code'   => 'testmethod',
        'method_title'  => 'Test Method Overnight',
        'estimated'     => TRUE,
        'amount'        => '$50.00',
        'extension'     => [],
      ], [
        'carrier_code'  => 'testcarrier',
        'carrier_title' => 'Test Carrier',
        'method_code'   => 'testmethod2',
        'method_title'  => 'Test Method Two-Day',
        'estimated'     => TRUE,
        'amount'        => '$30.00',
        'extension'     => [],
      ], [
        'carrier_code'  => 'testcarrier',
        'carrier_title' => 'Test Carrier',
        'method_code'   => 'testmethod3',
        'method_title'  => 'Test Method Ground',
        'estimated'     => TRUE,
        'amount'        => '$10.00',
        'extension'     => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethods($cart_id) {
    return [
      'braintree' => 'braintree',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentToken($method) {
    // This token is invalid, but will at least get the hosted iframe to show.
    return '112233445566778899';
  }

  /**
   * Generates an order.
   *
   * @param string $email
   *   The customer email address.
   * @param string $status
   *   The order status.
   * @param string $state
   *   The order state.
   *
   * @return array
   *   Generated order.
   */
  private function generateOrder($email = '', $status = 'shipped', $state = 'new') {
    $order = $this->getCustomer($email);
    $products = $this->generateTestProducts(2);
    $totals = $this->generateOrderTotals($products);

    $order['items'] = $products;
    $order['totals'] = $totals;

    $order['payment'] = [
      'method_code' => 'testcc',
      'method_title' => 'Test CC Method',
      'amount' => $totals['grand'],
      'extension' => [
        'cc_last4' => '1111',
        'cc_year' => '2020',
        'cc_month' => '12',
      ],
    ];

    $order['billing'] = $order['addresses'][0];
    $order['shipping'] = [
      'address' => $order['addresses'][1],
      'method' => [
        'carrier_code'  => 'testcarrier',
        'carrier_title' => 'Test Carrier',
        'method_code'   => 'testmethod',
        'method_title'  => 'Test Method Overnight',
        'estimated'     => TRUE,
        'amount'        => '100.00',
        'extension'     => [],
      ],
    ];
    $order['coupon'] = 'GREAT DEAL';
    $order['status'] = $status;
    $order['state'] = $state;
    $order['created_at'] = REQUEST_TIME;
    $order['order_id'] = $this->generateOrderId();

    return $order;
  }

  /**
   * Generates a new product ID.
   *
   * @return int
   *   The next product ID.
   */
  private function generateProductId() {
    $this->lastProductId++;
    return $this->lastProductId;
  }

  /**
   * Generates a new order ID.
   *
   * @return int
   *   The next order ID.
   */
  private function generateOrderId() {
    $this->lastOrderId++;
    return $this->lastOrderId;
  }

  /**
   * Generates a order totals.
   *
   * @param array $products
   *   The products to use to build the totals.
   *
   * @return array
   *   Generated order totals.
   */
  private function generateOrderTotals(array $products = []) {
    $sub = 0;

    foreach ($products as $product) {
      $sub += $product['price'] * $product['shipped'];
    }

    $tax = $sub * 0.06;
    $discount = rand(0, $sub / 3);
    $grand = $sub + $tax - $discount;

    return [
      'sub' => number_format($sub, 2),
      'tax' => number_format($tax, 2),
      'discount' => number_format($discount, 2),
      'grand' => number_format($grand, 2),
    ];
  }

  /**
   * Generates a random test product.
   *
   * @param string $type
   *   The product type.
   *
   * @return array
   *   Generated test product.
   */
  private function generateTestProduct($type = 'simple') {
    $id = $this->generateProductId();
    $sku = "TESTSKU{$id}";
    $name = "Test Product {$id}";
    $ordered = rand(1, 10);
    $shipped = rand(1, $ordered);
    $refunded = rand(0, $shipped);
    $price = number_format(10 + lcg_value() * (abs(500 - 10)), 2);

    return [
      'product_id' => $id,
      'sku' => $sku,
      'name' => $name,
      'type' => $type,
      'price' => $price,
      'qty' => $ordered,
      'ordered' => $ordered,
      'shipped' => $shipped,
      'refunded' => $refunded,
      'extension' => [],
    ];
  }

  /**
   * Generates test products.
   *
   * @param int $total
   *   The total number of products to generate.
   *
   * @return array
   *   Generate test products.
   */
  private function generateTestProducts($total = 1) {
    $products = [];
    for ($i = 0; $i < $total; $i++) {
      $products[] = $this->generateTestProduct();
    }
    return $products;
  }

  /**
   * {@inheritdoc}
   */
  public function updateStoreContext($store_id){}

  /**
   * {@inheritdoc}
   */
  public function getQueueStatus(): int {
    return 5;
  }

  /**
   * {@inheritdoc}
   */
  public function purgeQueue(): bool {
    return TRUE;
  }

}
