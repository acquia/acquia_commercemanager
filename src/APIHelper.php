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
      foreach ($customer['addresses'] as $delta => $address) {
        $address = (array) $address;
        $customer['addresses'][$delta] = $this->cleanAddress($address);
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
   * @param array $address
   *   Cart address.
   *
   * @return array
   *   Cleaned cart address.
   */
  public function cleanCartAddress(array $address) {
    $address = (array) $address;
    // @TODO: Convert cart address extension to array.
    return $this->cleanAddress($address);
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

    return $address;
  }

}
