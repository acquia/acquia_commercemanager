<?php

namespace Drupal\acm\Connector;

/**
 * APIWrapper interface.
 */
interface APIWrapperInterface {

  /**
   * Function to override context store id for API calls.
   *
   * @param string $store_id
   *   Store ID to use for API calls.
   */
  public function updateStoreContext($store_id);

  /**
   * Creates a new cart through the API.
   *
   * @param int $customer_id
   *   Optional customer ID to create the cart for.
   *
   * @return object
   *   Contains the new cart object.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function createCart($customer_id = NULL);

  /**
   * Checks the stock for the given sku.
   *
   * @param string $sku
   *   The sku id.
   *
   * @return array|mixed
   *   Available stock detail.
   *
   * @throws \Drupal\acm\Connector\RouteException
   */
  public function skuStockCheck($sku);

  /**
   * Gets the user cart from a cart ID.
   *
   * @param int $cart_id
   *   Target cart ID.
   * @param int|string $customer_id
   *   The customer id.
   *
   * @return array
   *   Contains the retrieved cart array.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getCart($cart_id, $customer_id = NULL);

  /**
   * Update cart with the new cart array supplied.
   *
   * @param int $cart_id
   *   ID of cart to update.
   * @param object $cart
   *   Cart object to update with.
   *
   * @return array
   *   Full updated cart after submission.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function updateCart($cart_id, $cart);

  /**
   * Associate a cart with a customer.
   *
   * @param int $cart_id
   *   ID of cart to associate.
   * @param int $customer_id
   *   ID of customer to associate with.
   *
   * @return bool
   *   A status of coupon being applied.
   *
   * @throws \Drupal\acm\Connector\RouteException
   */
  public function associateCart($cart_id, $customer_id);

  /**
   * Finalizes a cart's order.
   *
   * @param int $cart_id
   *   Cart id to attempt placing an order for.
   * @param int|string $customer_id
   *   The customer id.
   *
   * @return array
   *   Result returned back from the connector.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function placeOrder($cart_id, $customer_id = NULL);

  /**
   * Gets shipping methods available on a order.
   *
   * @param int $cart_id
   *   Cart ID to retrieve shipping methods for.
   *
   * @return array
   *   If successful, returns a array of shipping methods.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getShippingMethods($cart_id);

  /**
   * Similar to getShippingMethods, retrieves methods with estimated costs.
   *
   * @param int $cart_id
   *   Cart ID to estimate for.
   * @param array|object $address
   *   Array with the target address.
   * @param null|int|string $customer_id
   *   The customer ID. NULL for guest, int for magento, string for hybris.
   *
   * @return array
   *   Array of estimates and methods.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getShippingEstimates($cart_id, $address, $customer_id = NULL);

  /**
   * Gets the payment methods for the cart ID.
   *
   * @param int $cart_id
   *   Cart ID to get methods for.
   *
   * @return array
   *   Array of methods.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getPaymentMethods($cart_id);

  /**
   * Creates a customer by calling updateCustomer with NULL customer ID.
   *
   * @param array $customer
   *   Array of customer field values.
   *   Customer array is expected to have the following required keys:
   *   - firstname: The customer's first name.
   *   - lastname: The customer's last name.
   *   - email: The customer's email address.
   * @param string $password
   *   Optional password.
   *
   * @return array
   *   New customer array.
   */
  public function createCustomer(array $customer, $password = NULL);

  /**
   * Updates a customer.
   *
   * @param array|object $customer
   *   Customer array to update (fully prepared array).
   * @param array $options
   *   Array of options needed for different types of updates. Possible keys:
   *   - password: Optional password to set for the user.
   *   - password_old: Optional old password. Required if updating a logged in
   *   user's password.
   *   - password_token: Optional password token. Required if updating an
   *   anonymous user's password.
   *   - access_token: Optional depending on ecommerce backend. In Hybris, if
   *   updating a current user's addresses or password this is required.
   *
   * @return array
   *   New customer array.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function updateCustomer($customer, array $options = []);

  /**
   * Deletes an address.
   *
   * @param int|string $customer_id
   *   The ID of the customer who owns the address.
   * @param int|string $address_id
   *   The ID of the address being deleted.
   *
   * @return bool
   *   TRUE if deleted, FALSE otherwise.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function deleteCustomerAddress($customer_id, $address_id);

  /**
   * Validate a customer address.
   *
   * @param array|object $address
   *   An address to validate.
   *
   * @return array
   *   The validation results.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function validateCustomerAddress($address);

  /**
   * Requests a password reset.
   *
   * Please note this is not a V2 endpoint.
   *
   * @param string $email
   *   The email of the customer who wants to reset their password. An email
   *   will then be sent out from the ecommerce backend with a password reset
   *   link that includes a password reset token that the ecommerce backend
   *   generated for that customer.
   *
   * @return bool
   *   TRUE if deleted, FALSE otherwise.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function resetCustomerPassword($email);

  /**
   * Authenticate customer.
   *
   * @param string $email
   *   Customer e-mail.
   * @param string $password
   *   Password.
   *
   * @return array
   *   New customer array.
   */
  public function authenticateCustomer($email, $password);

  /**
   * Gets customer by email.
   *
   * To test if a customer email exists in the e-commerce application,
   * set $throwRouteException to false. When $throwRouteException is false
   * and the customer does not exist then this function
   * returns an empty array, indicating that the customer does not exist.
   *
   * @param string $email
   *   Customer Email.
   * @param bool $throwCustomerNotFound
   *   Flag to throw exception or not. Default true.
   *
   * @return array
   *   Customer array. Empty if email does not exist in eCommerce system
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getCustomer($email, $throwCustomerNotFound = TRUE);

  /**
   * Gets customer orders by email.
   *
   * @param string $email
   *   The customer email.
   * @param int|string $order_id
   *   The order id. If this is set it will only load that specific order
   *   instead of all orders.
   *
   * @return array
   *   Orders array.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getCustomerOrders($email, $order_id = NULL);

  /**
   * Gets an auth token for a customer.
   *
   * @param string $email
   *   Customer e-mail.
   * @param string $password
   *   Password.
   *
   * @return string
   *   The auth token.
   */
  public function getCustomerToken($email, $password);

  /**
   * Gets the currently logged in user.
   *
   * @param string $token
   *   The auth token.
   *
   * @return array
   *   Customer array.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getCurrentCustomer($token);

  /**
   * Update order status and provide comment for update.
   *
   * @param int $order_id
   *   Order id.
   * @param string $status
   *   Order status.
   * @param string $comment
   *   Optional comment.
   *
   * @return bool|mixed
   *   Status of the update (TRUE/FALSE).
   *
   * @throws \Drupal\acm\Connector\RouteException
   */
  public function updateOrderStatus($order_id, $status, $comment = '');

  /**
   * Fetches product categories.
   *
   * @return array
   *   Array of product categories.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getCategories();

  /**
   * Fetches product attribute options.
   *
   * @return array
   *   Array of product attribute options.
   */
  public function getProductOptions();

  /**
   * Fetches all promotions.
   *
   * @param string $type
   *   The type of promotion to retrieve from the API.
   *
   * @return array
   *   Array of promotions.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getPromotions($type = 'category');

  /**
   * Gets products by updated time.
   *
   * @param \DateTime $date_time
   *   Datetime of the last update.
   *
   * @return array
   *   Array of products.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getProductsByUpdatedDate(\DateTime $date_time);

  /**
   * Invoke product full sync through agent.
   *
   * Synchronous version of the full product sync.
   *
   * @param string $skus
   *   String of SKUs.
   * @param int $page_size
   *   Size of page.
   * @param string $acm_uuid
   *   The acm_uuid to pass into the Commerce Connector (via the client).
   * @param string $categoryId
   *   Optional. The category ID to sync from. If specified, skus are ignored
   *   and all skus of that category are synchronised.
   *
   * @return array
   *   An array of product data.
   */
  public function productFullSync($skus = '', $page_size = 0, $acm_uuid = '', $categoryId = "");

  /**
   * TODO (malachy): NOT USED. Consider removal.
   *
   * Gets latest updated products for syncing.
   *
   * Primarily for dev / testing when async API traffic is not possible.
   *
   * @param int $count
   *   Number of products to sync.
   *
   * @return array
   *   Array of products.
   */
  public function getProducts($count = 100);

  /**
   * Fetches a token for the requested payment method.
   *
   * @param string $method
   *   The ID of the requested payment token.
   *
   * @return string
   *   Payment token.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function getPaymentToken($method);

  /**
   * Function to subscribe an email for newsletter.
   *
   * @param string $email
   *   E-Mail to subscribe.
   */
  public function subscribeNewsletter($email);

  /**
   * Preforms a test call to connector.
   *
   * @return array
   *   Test request result.
   *
   * @throws \Drupal\acm\Connector\RouteException
   *   Failed request exception.
   */
  public function systemWatchdog();

  /**
   * Get number of items in site specific queue.
   *
   * @return int
   *   Number of items in queue.
   *
   * @throws \Exception
   *   Failed request exception.
   */
  public function getQueueStatus(): int;

  /**
   * Purge items in site specific queue.
   *
   * @return bool
   *   Success of operation.
   *
   * @throws \Exception
   *   Failed request exception.
   */
  public function purgeQueue(): bool;

}
