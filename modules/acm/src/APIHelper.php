<?php

namespace Drupal\acm;

/**
 * Class APIHelper.
 *
 * Contains all reusable functions which don't require any other service.
 *
 * @package Drupal\acm
 */
class APIHelper {

  /**
   * Clean customer data before sending to API.
   *
   * @param array $customer
   *   Customer data.
   *
   * @return array
   *   Cleaned customer data.
   */
  public function cleanCustomerData(array $customer) {
    if (isset($customer['customer_id'])) {
      $customer['customer_id'] = (string) $customer['customer_id'];
    }

    if (isset($customer['addresses'])) {
      // When deleting an address need to re-index array.
      $customer['addresses'] = array_values($customer['addresses']);
      foreach ($customer['addresses'] as $delta => $address) {
        $address = (array) $address;
        $customer['addresses'][$delta] = $this->cleanCustomerAddress($address);
      }
    }

    return $customer;
  }

  /**
   * Get cleaned customer address.
   *
   * @param array $address
   *   Customer address.
   *
   * @return array
   *   Cleaned customer address.
   */
  public function cleanCustomerAddress(array $address) {
    if (isset($address['customer_address_id']) && empty($address['address_id'])) {
      $address['address_id'] = $address['customer_address_id'];
    }

    return $this->cleanAddress($address);
  }

  /**
   * Get cleaned cart address.
   *
   * @param mixed $address
   *   Cart address object/array.
   *
   * @return array
   *   Cleaned cart address.
   */
  public function cleanCartAddress($address) {
    $address = (array) $address;

    $address = $this->cleanAddress($address);

    if (isset($address['customer_address_id'])) {
      $address['customer_address_id'] = (int) $address['customer_address_id'];
    }

    // Never send address_id in API request, it confuses Magento.
    if (isset($address['address_id'])) {
      unset($address['address_id']);
    }

    return $address;
  }

  /**
   * Clean address (applicable for all type of addresses).
   *
   * @param array $address
   *   Address array.
   *
   * @return array
   *   Cleaned address array.
   */
  public function cleanAddress(array $address) {
    if (isset($address['customer_id'])) {
      $address['customer_id'] = (string) $address['customer_id'];
    }

    if (isset($address['default_billing'])) {
      $address['default_billing'] = (bool) $address['default_billing'];
    }

    if (isset($address['default_shipping'])) {
      $address['default_shipping'] = (bool) $address['default_shipping'];
    }

    return $this->normaliseExtension($address);
  }

  /**
   * Extensions must always be objects and not arrays.
   *
   * @param mixed $data
   *   Array/Object data.
   *
   * @return array
   *   Data in same type but with extension as object.
   */
  public function normaliseExtension($data) {
    if (is_object($data)) {
      if (isset($data->extension)) {
        $data->extension = (object) $data->extension;
      }
    }
    elseif (is_array($data)) {
      if (isset($data['extension'])) {
        $data['extension'] = (object) $data['extension'];
      }
    }

    return $data;
  }

  /**
   * Clean up cart data.
   *
   * @param object $cart
   *   Cart object.
   *
   * @return object
   *   Cleaned cart object.
   */
  public function cleanCart($cart) {
    // Check if there's a customer ID and remove it if it's empty.
    if (isset($cart->customer_id) && empty($cart->customer_id)) {
      unset($cart->customer_id);
    }
    elseif (isset($cart->customer_id)) {
      $cart->customer_id = (string) $cart->customer_id;
    }

    // Check if there's a customer email and remove it if it's empty.
    if (isset($cart->customer_email) && empty($cart->customer_email)) {
      unset($cart->customer_email);
    }

    // Don't tell conductor our stored totals for no reason.
    if (isset($cart->totals)) {
      unset($cart->totals);
    }

    // Cart extensions must always be objects and not arrays.
    if (isset($cart->carrier)) {
      $cart->carrier = $this->normaliseExtension($cart->carrier);
    }
    // Remove shipping address if carrier not set.
    else {
      unset($cart->shipping);
    }

    // Cart constructor sets cart to any object passed in,
    // circumventing ->setBilling() so trap any wayward extension[] here.
    if (isset($cart->billing)) {
      $cart->billing = $this->cleanCartAddress($cart->billing);
    }

    if (isset($cart->shipping)) {
      $cart->shipping = $this->cleanCartAddress($cart->shipping);
    }

    // Never send response_message back.
    if (isset($cart->response_message)) {
      unset($cart->response_message);
    }

    // When we remove an item from cart, we have to reset the keys to have
    // proper indexed array.
    if (isset($cart->items)) {
      $cart->items = array_values($cart->items);

      foreach ($cart->items as &$item) {
        $item['sku'] = (string) $item['sku'];
      }
    }

    return $cart;
  }

}
