<?php

namespace Drupal\acm_sku;

/**
 * Class ProductInfoRequestedEvents.
 *
 * @package Drupal\acm_sku
 */
final class ProductInfoRequestedEvents {

  /**
   * Product info requested.
   *
   * This event occurs when a user requests specific information related to
   * products through acm_sku.product_info_helper service (ProductInfoHelper).
   *
   * Check the service class know what all information can be requested through
   * service and allow custom code / brand specific modules to provide
   * information after applying brand specific logic.
   *
   * @Event("Drupal\acm_sku\ProductInfoRequestedEvent")
   */
  const EVENT_NAME = 'product_info_requested';

}
