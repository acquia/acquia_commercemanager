<?php

namespace Drupal\acm_cart;

use Drupal\acm_sku\Entity\SKU;

/**
 * Class Cart.
 *
 * @package Drupal\acm_cart
 */
class Cart implements CartInterface {

  /**
   * The cart object.
   *
   * @var object
   */
  protected $cart;

  /**
   * The current checkout step id.
   *
   * @var string
   */
  protected $checkoutStepId;

  /**
   * Whether or not the cart items can be shipped.
   *
   * @var bool
   */
  protected $shippable = FALSE;

  /**
   * The total quantity in the cart.
   *
   * @var int
   */
  protected $cartTotalCount = 0;

  /**
   * The cart's guest email address.
   *
   * @var string
   */
  protected $guestCartEmail;

  /**
   * Constructor.
   *
   * @param object $cart
   *   The cart.
   */
  public function __construct($cart) {
    $this->cart = $cart;
    // Calculate the cart quantity items.
    //
    // There won't be any quantity count exists when we initialize the cart
    // object. So, we have to calculate it explicitly here.
    $this->updateCartItemsCount();
  }

  /**
   * Function to update cart object.
   *
   * @param object $cart
   *   The cart.
   */
  public function updateCartObject($cart) {
    if (isset($cart->customer_id)) {
      $cart->customer_id = (string) $cart->customer_id;
    }

    // Some ecommerce backends, like hybris, don't save the billing like they
    // do with shipping. So if a billing was set we don't want it to be
    // overwritten when the API response comes back.
    $current_billing = $this->getBilling();
    if (!empty($current_billing) && empty($cart->billing)) {
      $cart->billing = $current_billing;
    }

    if (isset($cart->carrier)) {
      // We use it as array internally everywhere, even set as array.
      $cart->carrier = (array) $cart->carrier;

      // If carrier is with empty structure, we remove it.
      if (empty($cart->carrier['carrier_code'])) {
        unset($cart->carrier);
      }
    }

    $this->cart = $cart;
    $this->updateCartItemsCount();
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    if (isset($this->cart, $this->cart->cart_id)) {
      return $this->cart->cart_id;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function storeId() {
    if (isset($this->cart, $this->cart->store_id)) {
      return $this->cart->store_id;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function customerId() {
    if (isset($this->cart, $this->cart->customer_id)) {
      return (string) $this->cart->customer_id;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function customerEmail() {
    if (isset($this->cart, $this->cart->customer_email)) {
      return $this->cart->customer_email;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function totals() {
    if (isset($this->cart, $this->cart->totals)) {
      // Remove the comma coming from Magento, we will add while formatting.
      // Casting to float or using floatval stops at comma and doesn't return
      // whole value.
      // NumberFormatter.parse requires locale, we don't have expected value
      // available anywhere in the system for now (kw_AR) so not using it.
      foreach ($this->cart->totals as &$value) {
        $value = str_replace(',', '', $value);
      }
      return $this->cart->totals;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Process items to get their names from their plugins.
   */
  public function items() {
    if (!isset($this->cart, $this->cart->items)) {
      return [];
    }

    $items = $this->cart->items;

    foreach ($items as &$item) {
      if (!isset($item['sku'])) {
        continue;
      }

      $sku = SKU::loadFromSku($item['sku']);

      if (!($sku instanceof SKU)) {
        // We may have some products in cart which are now deleted in Drupal.
        // This can happen when an item is blocked or deleted in Magento.
        $this->logger->warning('Invalid SKU @sku found in Cart id: @cart_id.', [
          '@sku' => $item['sku'],
          '@cart_id' => $this->id(),
        ]);

        // Remove the item from cart in session.
        $this->removeItemFromCart($item['sku']);

        // Continue to next item, this item is removed in call above.
        continue;
      }

      // Get Plugin instance from SKU entity.
      $plugin = $sku->getPluginInstance();

      $item['name'] = $plugin->cartName($sku, $item);
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function addItemToCart($sku, $quantity, array $extension = []) {
    $items = $this->items();

    // Check if cart contains the same item.
    foreach ($items as $item) {
      if (!isset($item['sku'])) {
        continue;
      }

      if ($item['sku'] == $sku) {
        $new_qty = (int) $item['qty'] + (int) $quantity;
        if ($new_qty > 0) {
          $this->updateItemQuantity($sku, $new_qty);
        }
        else {
          $this->removeItemFromCart($sku);
        }
        return;
      }
    }

    $items[] = ['sku' => $sku, 'qty' => $quantity, 'extension' => $extension];

    $this->cart->items = $items;

    // We changed items in cart, lets ask user to go through the checkout flow
    // again to ensure everything is updated properly.
    $this->setCheckoutStep('');
  }

  /**
   * {@inheritdoc}
   */
  public function removeItemFromCart($sku) {
    $items = $this->items();
    foreach ($items as $key => &$item) {
      if (!isset($item['sku'])) {
        continue;
      }
      if ($item['sku'] == $sku) {
        unset($items[$key]);
        break;
      }
    }
    $this->cart->items = $items;

    // We changed items in cart, lets ask user to go through the checkout flow
    // again to ensure everything is updated properly.
    $this->setCheckoutStep('');
  }

  /**
   * {@inheritdoc}
   */
  public function addRawItemToCart(array $item) {
    $items = $this->items();

    $items[] = $item;

    $this->cart->items = $items;
  }

  /**
   * {@inheritdoc}
   */
  public function addItemsToCart(array $items) {
    foreach ($items as $item) {
      if (!isset($item['sku'])) {
        continue;
      }

      if (!isset($item['qty'])) {
        $item['qty'] = 1;
      }

      $extension = [];
      if (isset($item['extension'])) {
        $extension = $item['extension'];
      }

      $this->addItemToCart($item['sku'], $item['qty'], $extension);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setItemsInCart(array $items) {
    $this->cart->items = $items;

    // We changed items in cart, lets ask user to go through the checkout flow
    // again to ensure everything is updated properly.
    $this->setCheckoutStep('');
  }

  /**
   * {@inheritdoc}
   */
  public function updateItemQuantity($sku, $quantity) {
    $items = $this->items();

    foreach ($items as &$item) {
      if (!isset($item['sku'])) {
        continue;
      }

      if ($item['sku'] == $sku) {
        $item['qty'] = $quantity;
        break;
      }
    }

    $this->cart->items = $items;

    // We changed items in cart, lets ask user to go through the checkout flow
    // again to ensure everything is updated properly.
    $this->setCheckoutStep('');
  }

  /**
   * Get the total quantity of all items in the cart.
   *
   * @return int
   *   Return total number of items in the cart.
   */
  public function getCartItemsCount() {
    return $this->cartTotalCount;
  }

  /**
   * Calculate the cart items quantity.
   */
  public function updateCartItemsCount() {
    $this->cartTotalCount = 0;
    foreach ($this->items() as $item) {
      $this->cartTotalCount += $item['qty'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBilling() {
    $billing = new \stdClass();
    if (isset($this->cart, $this->cart->billing)) {
      $billing = $this->cart->billing;
    }
    return $billing;
  }

  /**
   * Address normalizer.
   *
   * @param object $address
   *   Object of address.
   *
   * @return object
   *   Normalized address.
   */
  private function normalizeAddress($address) {
    if (isset($address->extension)) {
      if (!is_object($address->extension)) {
        $anObject = (object) $address->extension;
        $address->extension = $anObject;
      }
    }
    return $address;
  }

  /**
   * {@inheritdoc}
   */
  public function setBilling($address) {
    $normalizedAddress = $this->normalizeAddress((object) $address);
    $this->cart->billing = $normalizedAddress;

    if (isset($this->cart->billing->first_name)) {
      $this->cart->billing->firstname = $this->cart->billing->first_name;
      unset($this->cart->billing->first_name);
    }

    if (isset($this->cart->billing->last_name)) {
      $this->cart->billing->lastname = $this->cart->billing->last_name;
      unset($this->cart->billing->last_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getShipping() {
    $shipping = new \stdClass();
    if (isset($this->cart, $this->cart->shipping)) {
      $shipping = $this->cart->shipping;
    }
    return $shipping;
  }

  /**
   * {@inheritdoc}
   */
  public function setShipping($address) {
    $normalizedAddress = $this->normalizeAddress((object) $address);
    $this->cart->shipping = $normalizedAddress;

    if (isset($this->cart->shipping->first_name)) {
      $this->cart->shipping->firstname = $this->cart->shipping->first_name;
      unset($this->cart->shipping->first_name);
    }

    if (isset($this->cart->shipping->last_name)) {
      $this->cart->shipping->lastname = $this->cart->shipping->last_name;
      unset($this->cart->shipping->last_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingMethod($carrier, $method, array $extension = []) {

    $this->cart->carrier = [
      'carrier_code' => $carrier,
      'method_code' => $method,
      'extension' => $extension,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingMethod() {
    // If cart is not updated yet and we are reading from session.
    if (isset($this->cart, $this->cart->carrier)) {
      return $this->cart->carrier;
    }

    if (isset($this->cart, $this->cart->extension, $this->cart->extension['shipping_method'])) {
      return explode('_', $this->cart->extension['shipping_method']);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingMethodAsString() {
    // If cart is not updated yet and we are reading from session.
    if (isset($this->cart, $this->cart->carrier)) {
      $method = $this->cart->carrier;
      return implode(',', [$method['carrier_code'], $method['method_code']]);
    }

    if (isset($this->cart, $this->cart->extension, $this->cart->extension['shipping_method'])) {
      return $this->cart->extension['shipping_method'];
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function clearShippingMethod() {
    // Unset the value set just now (before update cart).
    unset($this->cart->carrier);

    // Unset the value set in extension (updated cart response).
    if (isset($this->cart, $this->cart->extension, $this->cart->extension['shipping_method'])) {
      unset($this->cart->extension['shipping_method']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethod($full_details = TRUE) {
    if (!isset($this->cart, $this->cart->payment)) {
      return [];
    }

    if ($full_details) {
      return $this->cart->payment;
    }

    return $this->cart->payment['method'];
  }

  /**
   * {@inheritdoc}
   */
  public function clearPayment() {
    unset($this->cart->payment);
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentMethod($payment_method, array $data = []) {
    $this->cart->payment['method'] = $payment_method;
    if (!empty($data)) {
      $this->cart->payment['additional_data'] = $data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethodData() {
    if (isset($this->cart, $this->cart->payment)) {
      return $this->cart->payment['additional_data'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentMethodData(array $data = []) {
    if (isset($this->cart, $this->cart->payment)) {
      $this->cart->payment['additional_data'] = $data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckoutStep() {
    return $this->checkoutStepId;
  }

  /**
   * {@inheritdoc}
   */
  public function setCheckoutStep($step_id) {
    $this->checkoutStepId = $step_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippable() {
    return $this->shippable;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippable($shippable) {
    $this->shippable = $shippable;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoupon() {
    if (isset($this->cart, $this->cart->coupon)) {
      return $this->cart->coupon;
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setCoupon($coupon) {
    $this->cart->coupon = $coupon;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtension($key) {
    if (isset($this->cart, $this->cart->extension, $this->cart->extension[$key])) {
      return $this->cart->extension[$key];
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setExtension($key, $value) {
    $this->cart->extension[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCart() {
    if (isset($this->cart)) {
      $cart = $this->cart;

      if (isset($cart->shipping)) {
        // Don't set blank addresses, Magento doesn't like this.
        if (is_object($cart->shipping) && empty($cart->shipping->country_id)) {
          unset($cart->shipping);
        }
        elseif (is_array($cart->shipping) && empty($cart->shipping['country_id'])) {
          unset($cart->shipping);
        }
      }

      if (isset($cart->billing)) {
        if (is_object($cart->billing) && empty($cart->billing->country_id)) {
          unset($cart->billing);
        }
        elseif (is_array($cart->billing) && empty($cart->billing['country_id'])) {
          unset($cart->billing);
        }
      }

      return $this->cart;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function convertToCustomerCart(array $cart) {
    $this->cart->cart_id = $cart['cart_id'];
    $this->cart->customer_id = $cart['customer_id'];
    if (isset($cart['customer_email'])) {
      $this->cart->customer_email = $cart['customer_email'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGuestCartEmail() {
    return $this->guestCartEmail;
  }

  /**
   * {@inheritdoc}
   */
  public function setGuestCartEmail($email) {
    $this->guestCartEmail = $email;
  }

  /**
   * {@inheritdoc}
   */
  public function get($property_name) {
    if (isset($this->cart, $this->cart->{$property_name})) {
      return $this->cart->{$property_name};
    }
    return NULL;
  }

}
