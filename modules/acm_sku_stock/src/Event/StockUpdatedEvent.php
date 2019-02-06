<?php

namespace Drupal\acm_sku_stock\Event;

use Drupal\acm_sku\Entity\SKU;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class StockUpdatedEvent.
 *
 * @package Drupal\acm_sku_stock
 */
class StockUpdatedEvent extends Event {

  const EVENT_NAME = 'stock_updated';

  /**
   * SKU Entity.
   *
   * @var \Drupal\acm_sku\Entity\SKU
   */
  private $sku;

  /**
   * Flag for stock status changed.
   *
   * @var bool
   */
  private $statusChanged;

  /**
   * Flag for Low Quantity.
   *
   * @var bool
   */
  private $lowQuantity;

  /**
   * StockUpdatedEvent constructor.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   SKU Entity.
   * @param bool $status_changed
   *   If stock status changed.
   * @param bool $low_quantity
   *   If new stock is low.
   */
  public function __construct(SKU $sku, $status_changed, $low_quantity) {
    $this->sku = $sku;
    $this->statusChanged = $status_changed;
    $this->lowQuantity = $low_quantity;
  }

  /**
   * Get SKU Entity.
   *
   * @return \Drupal\acm_sku\Entity\SKU
   *   SKU Entity.
   */
  public function getSku() {
    return $this->sku;
  }

  /**
   * Is stock status changed.
   *
   * @return bool
   *   TRUE if stock status changed.
   */
  public function isStockStatusChanged() {
    return $this->statusChanged;
  }

  /**
   * Is stock low.
   *
   * @return bool
   *   TRUE if stock is low.
   */
  public function isLowQuantity() {
    return $this->lowQuantity;
  }

}
