<?php

namespace Drupal\acm_cart;

use Drupal\acm_cart\Event\CartAddressEvent;
use Drupal\acm_cart\Event\CartCouponEvent;
use Drupal\acm_cart\Event\CartEvent;
use Drupal\acm_cart\Event\CartExtensionEvent;
use Drupal\acm_cart\Event\CartItemEvent;
use Drupal\acm_cart\Event\CartPushEvent;
use Drupal\acm_cart\Event\CartRawItemsEvent;
use Drupal\acm_cart\Event\Events;
use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\SessionStoreInterface;
use Drupal\acm_sku\Entity\SKU;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class CartStorage.
 *
 * @package Drupal\acm_cart
 */
class CartStorage implements CartInterface, CartStorageInterface {

  /**
   * The session storage.
   *
   * @var \Drupal\acm\SessionStoreInterface
   */
  protected $storage;

  /**
   * API Wrapper object.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  protected $apiWrapper;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The cart.
   *
   * @var \Drupal\acm_cart\CartInterface
   */
  protected $cart;

  /**
   * Constructor.
   *
   * @param \Drupal\acm\SessionStoreInterface $storage
   *   The session storage.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   ApiWrapper object.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   LoggerFactory object.
   */
  public function __construct(SessionStoreInterface $storage, APIWrapperInterface $api_wrapper, EventDispatcherInterface $event_dispatcher, LoggerChannelFactoryInterface $logger_factory) {
    $this->storage = $storage;
    $this->apiWrapper = $api_wrapper;
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger_factory->get('acm_cart');

    // Load the intial cart.
    $cart = $this->storage->get(self::STORAGE_KEY);
    if (!empty($cart) && $cart instanceof CartInterface) {
      $this->cart = $cart;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCartId($create_new = TRUE) {
    $cookies = $this->getCookies();
    $cart_id = NULL;

    if (isset($cookies['Drupal_visitor_acm_cart_id'])) {
      return $cookies['Drupal_visitor_acm_cart_id'];
    }

    if ($this->cart) {
      return $this->cart->id();
    }
    elseif ($create_new) {
      $cart = $this->createCart();
      return $cart->id();
    }
    else {
      return NULL;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function storeCart(CartInterface $cart) {
    $cart_event = $this->eventDispatcher->dispatch(Events::STORE_CART, new CartEvent($cart));
    $cart = $cart_event->getCart();

    // Store cart in memory.
    $this->cart = $cart;
    // Store cart.
    $this->storage->set(self::STORAGE_KEY, $cart);
    // Update cookies cache in Drupal to use new one.
    $this->setCookie('acm_cart_id', $cart->id());
    $this->setCookie('acm_cart_count', $this->cart->getCartItemsCount());
  }

  /**
   * {@inheritdoc}
   */
  public function restoreCart($cart_id) {
    try {
      // @TODO: Need to rethink about this and get it done in single API call.
      $cart = (object) $this->apiWrapper->getCart($cart_id, $this->getCustomerId());

      if ($cart) {
        $cart->cart_id = $cart_id;
        $cart = new Cart($cart);
        $this->storeCart($cart);
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Error occurred while restoring cart id %cart_id: %message', [
        '%cart_id' => $cart_id,
        '%message' => $e->getMessage(),
      ]);

      $this->clearCart();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function convertGuestCart($customer_id) {
    if (empty($customer_id)) {
      return;
    }

    $restored = FALSE;

    // Try to restore an existing cart for this user, but only if the current
    // guest cart is empty.
    if ($this->isEmpty()) {
      try {
        $cart = (object) $this->apiWrapper->getCart('current', $customer_id);

        if ($cart) {
          $cart = new Cart($cart);
          $this->storeCart($cart);
          $restored = TRUE;
        }
      }
      catch (\Exception $e) {
        $message = 'Could not restore a cart for %customer_id, associating cart instead.';
        $this->logger->warning($message, [
          '%customer_id' => $customer_id,
        ]);
      }
    }

    // If no cart was restored, associate the current guest cart to a customer
    // cart.
    if (!$restored) {
      $this->associateCart($customer_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearCart() {
    // Only call clear cart event if there was a previous cart.
    if ($this->cart) {
      $this->eventDispatcher->dispatch(Events::CLEAR_CART, new CartEvent($this->cart));
    }

    // Clear cart in memory.
    $this->cart = NULL;

    // Clear cart cookie.
    $this->setCookie('acm_cart_id', NULL);

    // Clear cart count cookie.
    $this->setCookie('acm_cart_count', NULL);

    // Clear the values in storage.
    $this->storage->remove(self::STORAGE_KEY);
  }

  /**
   * {@inheritdoc}
   */
  public function loadCart($create_new = TRUE) {
    // No cart in storage, try to load an updated cart.
    if (!$this->cart) {
      try {
        $this->updateCart($create_new);
      }
      catch (\Exception $e) {
        // Intentionally suppressing the error here. This will happen when there
        // is no cart and still updateCart is called.
      }
    }

    // Allow loaded cart to be altered.
    if ($this->cart) {
      $cart_event = $this->eventDispatcher->dispatch(Events::LOAD_CART, new CartEvent($this->cart));
      $this->cart = $cart_event->getCart();

      $this->setCookie('acm_cart_count', $this->cart->getCartItemsCount());
    }

    else {
      $this->setCookie('acm_cart_count', NULL);
    }

    return $this->cart;
  }

  /**
   * Get skus of current cart items.
   *
   * @return array
   *   Items in the current cart.
   */
  public function getCartSkus() {
    $items = $this->items();
    if (empty($items)) {
      return [];
    }

    $skus = [];
    foreach ($items as $item) {
      $skus[] = $item['sku'];
    }

    return $skus;
  }

  /**
   * {@inheritdoc}
   */
  public function updateCart($create_new = TRUE) {
    $cart_id = $this->getCartId($create_new);
    $update = NULL;

    $cart = $this->cart;

    if ($cart_id && empty($cart)) {
      $this->restoreCart($cart_id);
    }

    // If cart exists, derive update array and update cookie.
    if ($cart) {
      $this->setCookie('acm_cart_id', $cart->id());
      $update = $cart->getCart();
    }

    /** @var \Drupal\acm_cart\Event\CartPushEvent $cart_event */
    $cart_event = $this->eventDispatcher->dispatch(Events::UPDATE_CART, new CartPushEvent($update));
    $update = $cart_event->getRawCart();

    // Don't tell connector our stored totals for no reason.
    if (isset($update->totals)) {
      unset($update->totals);
    }

    if ($cart_id) {
      try {
        $cartObject = (object) $this->apiWrapper->updateCart($cart_id, $update);
      }
      catch (\Exception $e) {
        // Restore the cart only if exception is not related to API being down.
        if (!acm_is_exception_api_down_exception($e)) {
          $this->restoreCart($cart_id);
        }
        throw $e;
      }

      if (empty($cartObject)) {
        return;
      }

      $cartObject->cart_id = $cart_id;

      if ($cart) {
        $cart->updateCartObject($cartObject);
      }
      else {
        $cart = new Cart($cartObject);
      }

      $this->storeCart($cart);
      return $this->cart;
    }
    else {
      return NULL;
    }
  }

  /**
   * Pushes the cart to the ecommerce app via the Connector API.
   *
   * @deprecated Use updateCart() instead.
   *
   * @return null|\Drupal\acm_cart\CartInterface
   *   Returns the cart data as sent by the ecommerce backend
   */
  public function pushCart() {
    $cart = $this->cart;

    if (!$cart) {
      return;
    }

    // Cart exists, derive update array and update cookie.
    $this->setCookie('acm_cart_id', $cart->id());
    $update = $cart->getCart();
    $cart_event = $this->eventDispatcher->dispatch(Events::PUSH_CART, new CartPushEvent($update));
    $cart_response = (object) $this->apiWrapper->updateCart($cart->id(), $cart_event->getRawCart());

    if (empty($cart_response)) {
      return;
    }

    return $cart;
  }

  /**
   * {@inheritdoc}
   */
  public function createCart() {
    // @TODO: It seems this customer_id is never used by Magento.
    // We may need to edit Magento code to associate the cart if customer_id is
    // given or use the associate endpoint.
    $customer_id = $this->getCustomerId();
    $cart = (object) $this->apiWrapper->createCart($customer_id);
    $cart = new Cart($cart);
    $this->storeCart($cart);
    return $cart;
  }

  /**
   * {@inheritdoc}
   */
  public function associateCart($customer_id, $customer_email = "") {
    // We first update the cart in storage.
    $cart = $this->cart;
    if (!$cart) {
      return;
    }

    $cart_id = $cart->id();

    try {
      $response = $this->apiWrapper->associateCart($cart_id, $customer_id);
    }
    catch (\Exception $e) {
      $this->restoreCart($cart->id());
      throw $e;
    }

    // If the association worked and the API returned a new cart ID, set our
    // cart ID to the new one.
    if (isset($response['status'], $response['success']) && $response['success'] == 1) {
      $cart_id = $response['status'];
    }

    $data = [
      'cart_id' => $cart_id,
      'customer_id' => $customer_id,
    ];

    if ($customer_email) {
      $data['customer_email'] = $customer_email;
    }

    $cart->convertToCustomerCart($data);

    // Update the cart in storage/memory.
    $this->storeCart($cart);
  }

  /**
   * {@inheritdoc}
   */
  public function cartExists() {
    if (isset($this->cart) && $this->cart instanceof CartInterface) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $items = $this->items();

    if (empty($items)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Helper function to clear stock cache of all items in cart.
   */
  public function clearCartItemsStockCache() {
    $items = $this->items();

    if (empty($items)) {
      return;
    }

    foreach ($items as $item) {
      $sku_entity = SKU::loadFromSku($item['sku']);
      $sku_entity->refreshStock();
    }
  }

  /**
   * Checks if user is anonymous.
   *
   * @return bool
   *   TRUE if anonymous, FALSE otherwise.
   */
  protected function isUserAnonymous() {
    return $this->getCurrentUser()->isAnonymous();
  }

  /**
   * Gets the current user.
   *
   * @return \Drupal\Core\Session\AccountProxy
   *   The current user.
   */
  protected function getCurrentUser() {
    $use_ecomm_sessions = \Drupal::config('acm.commerce_users')
      ->get('use_ecomm_sessions');

    if ($use_ecomm_sessions) {
      return \Drupal::service('acm.commerce_user_manager');
    }
    else {
      return \Drupal::currentUser();
    }
  }

  /**
   * Gets the current customer user id.
   *
   * @return null|int|string
   *   The current customer id. NULL for none, int for Magento, string for
   *   Hybris.
   */
  protected function getCustomerId() {
    $customer_id = NULL;
    $current_user = $this->getCurrentUser();

    if ($current_user->isAnonymous()) {
      return $customer_id;
    }

    $use_ecomm_sessions = \Drupal::config('acm.commerce_users')
      ->get('use_ecomm_sessions');

    if ($use_ecomm_sessions) {
      $customer_id = $current_user->getAccount()->id();
    }
    else {
      $customer_id = $current_user->getAccount()->acm_customer_id;
    }

    return $customer_id;
  }

  /**
   * Gets cookies.
   *
   * @return array
   *   The cookies.
   */
  protected function getCookies() {
    if (isset(\Drupal::request()->cookies)) {
      return \Drupal::request()->cookies->all();
    }
    return $_COOKIE;
  }

  /**
   * Sets a cookie.
   *
   * @param string $name
   *   The cookie name.
   * @param string $value
   *   The cookie value.
   */
  protected function setCookie($name, $value = '') {
    // Don't set cookies if this service is used via drush.
    if (PHP_SAPI === 'cli') {
      return;
    }

    if (isset(\Drupal::request()->cookies)) {
      \Drupal::request()->cookies->set("Drupal_visitor_{$name}", $value);
    }

    user_cookie_save([
      $name => $value,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->cart->id();
  }

  /**
   * {@inheritdoc}
   */
  public function storeId() {
    return $this->cart->storeId();
  }

  /**
   * {@inheritdoc}
   */
  public function customerId() {
    return $this->cart->customerId();
  }

  /**
   * {@inheritdoc}
   */
  public function customerEmail() {
    return $this->cart->customerEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function totals() {
    return $this->cart->totals();
  }

  /**
   * {@inheritdoc}
   */
  public function items() {
    if (!$this->cartExists()) {
      return [];
    }
    return $this->cart->items();
  }

  /**
   * {@inheritdoc}
   */
  public function addItemToCart($sku, $quantity, array $extension = []) {
    $cart_event = $this->eventDispatcher->dispatch(Events::ADD_ITEM_TO_CART, new CartItemEvent($sku, $quantity, $extension));
    $this->cart->addItemToCart($cart_event->getSku(), $cart_event->getQuantity(), $cart_event->getExtension());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function removeItemFromCart($sku) {
    $cart_event = $this->eventDispatcher->dispatch(Events::REMOVE_ITEM_FROM_CART, new CartItemEvent($sku));
    $this->cart->removeItemFromCart($cart_event->getSku());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function addRawItemToCart(array $item) {
    $cart_event = $this->eventDispatcher->dispatch(Events::ADD_RAW_ITEM_TO_CART, new CartRawItemsEvent($item));
    $this->cart->addRawItemToCart($cart_event->getItems());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function addItemsToCart(array $items) {
    $cart_event = $this->eventDispatcher->dispatch(Events::ADD_RAW_ITEM_TO_CART, new CartRawItemsEvent($items));
    $this->cart->addItemsToCart($cart_event->getItems());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function setItemsInCart(array $items) {
    $cart_event = $this->eventDispatcher->dispatch(Events::SET_ITEMS_IN_CART, new CartRawItemsEvent($items));
    $this->cart->setItemsInCart($cart_event->getItems());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function updateItemQuantity($sku, $quantity) {
    $cart_event = $this->eventDispatcher->dispatch(Events::UPDATE_ITEM_QUANTITY, new CartItemEvent($sku, $quantity));
    $this->cart->updateItemQuantity($cart_event->getSku(), $cart_event->getQuantity());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getBilling() {
    return $this->cart->getBilling();
  }

  /**
   * {@inheritdoc}
   */
  public function setBilling($address) {
    $cart_event = $this->eventDispatcher->dispatch(Events::SET_BILLING_ADDRESS, new CartAddressEvent($address));
    $this->cart->setBilling($cart_event->getAddress());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getShipping() {
    return $this->cart->getShipping();
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingMethod() {
    return $this->cart->getShippingMethod();
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingMethodAsString() {
    return $this->cart->getShippingMethodAsString();
  }

  /**
   * {@inheritdoc}
   */
  public function clearShippingMethod() {
    $this->cart->clearShippingMethod();
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingMethod($carrier, $method, array $extension = []) {
    $this->cart->setShippingMethod($carrier, $method, $extension);
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function setShipping($address) {
    $cart_event = $this->eventDispatcher->dispatch(Events::SET_SHIPPING_ADDRESS, new CartAddressEvent($address));
    $this->cart->setShipping($cart_event->getAddress());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethod($full_details = TRUE) {
    return $this->cart->getPaymentMethod($full_details);
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentMethod($payment_method, array $data = []) {
    $this->cart->setPaymentMethod($payment_method, $data);
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethodData() {
    return $this->cart->getPaymentMethodData();
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentMethodData(array $data = []) {
    $this->cart->setPaymentMethodData($data);
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckoutStep() {
    return $this->cart->getCheckoutStep();
  }

  /**
   * {@inheritdoc}
   */
  public function setCheckoutStep($step_id) {
    $this->cart->setCheckoutStep($step_id);
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getShippable() {
    return $this->cart->getShippable();
  }

  /**
   * {@inheritdoc}
   */
  public function setShippable($shippable) {
    $this->cart->setShippable($shippable);
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getCoupon() {
    return $this->cart->getCoupon();
  }

  /**
   * {@inheritdoc}
   */
  public function setCoupon($coupon) {
    $cart_event = $this->eventDispatcher->dispatch(Events::SET_COUPON, new CartCouponEvent($coupon));
    $this->cart->setCoupon($cart_event->getCoupon());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getExtension($key) {
    return $this->cart->getExtension($key);
  }

  /**
   * {@inheritdoc}
   */
  public function setExtension($key, $value) {
    $cart_event = $this->eventDispatcher->dispatch(Events::SET_EXTENSION, new CartExtensionEvent($key, $value));
    $this->cart->setExtension($cart_event->getKey(), $cart_event->getValue());
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getCart($create_new = TRUE) {
    if (!$this->cart) {
      // loadcart() sets and returns $this->cart
      // or NULL if there is no existing cart and $create_new is false.
      return $this->loadCart($create_new);
    }
    return $this->cart;
  }

  /**
   * {@inheritdoc}
   */
  public function getCartContents() {
    return $this->cart->getCart();
  }

  /**
   * {@inheritdoc}
   */
  public function convertToCustomerCart(array $cart) {
    $this->cart->convertToCustomerCart($cart);
    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function getGuestCartEmail() {
    return $this->cart->getGuestCartEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function setGuestCartEmail($email) {
    $current_email = $this->getGuestCartEmail();

    // We store the email on the cart object that gets saved to storage, but
    // we don't want to set it on the actual cart. This is so any subsequent
    // updateCart or pushCart calls don't inadvertently try to set the guest
    // cart email again.
    $this->cart->setGuestCartEmail($email);

    // The guest cart email should only be set once, so if it was already set
    // we create a new cart and set the current cart to the new cart's ID.
    if (!empty($current_email)) {
      $customer_cart = $this->apiWrapper->createCart();
      $this->convertToCustomerCart($customer_cart);
    }

    // Set the email on the cart we send to the API, but don't set it on the
    // cart object that we store locally.
    $cart_contents = $this->getCartContents();
    $update_cart = clone $cart_contents;
    $update_cart->customer_email = $email;

    try {
      $this->apiWrapper->updateCart($this->id(), $update_cart);
    }
    catch (\Exception $e) {
      $this->logger->warning('Error occurred trying to update cart id %cart_id: %message', [
        '%cart_id' => $this->id(),
        '%message' => $e->getMessage(),
      ]);
    }

    $this->storeCart($this->cart);
  }

  /**
   * {@inheritdoc}
   */
  public function get($property_name) {
    return $this->cart->get($property_name);
  }

  /**
   * Clears the payment method info in cart.
   */
  public function clearPayment() {
    unset($this->cart->payment);
  }

}
