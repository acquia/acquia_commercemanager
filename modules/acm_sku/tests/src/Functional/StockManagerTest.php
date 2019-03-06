<?php

namespace Drupal\Tests\acq_sku\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @coversDefaultClass \Drupal\acm_sku\StockManager
 *
 * @group acq_sku
 */
class StockManagerTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'config',
    'field',
    'filter',
    'text',
    'file',
    'image',
    'options',
    'key_value_field',
    'node',
    'user',
    'taxonomy',
    'language',
    'rest',
    'simple_oauth',
    'simple_oauth_extras',
    'serialization',
    'acm',
    'acm_sku',
    'acm_sku_stock',
    'acquia_commercemanager',
    'pcb',
  ];

  /**
   * Stock Manager service.
   *
   * @var \Drupal\acm_sku\StockManager
   */
  protected $stockManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setup();

    // Load products and stock data.
    module_load_include('data', 'acm_sku', 'tests/data/products');
    module_load_include('data', 'acm_sku', 'tests/data/stock');

    $this->stockManager = $this->container->get('acm_sku.stock_manager');
  }

  /**
   * Test processStockMessage.
   */
  public function testProcessStockMessage() {
    // Sync products first.
    global $_acm_commerce_simple_products;
    $this->container->get('acm_sku.product_manager')->synchronizeProducts($_acm_commerce_simple_products);

    global $_acm_commerce_stock_create;
    $this->stockManager->processStockMessage($_acm_commerce_stock_create);
    $this->assertSame($this->stockManager->getStockQuantity($_acm_commerce_stock_create['sku']), 3999);

    global $_acm_commerce_stock_is_in_stock_false;
    $this->stockManager->processStockMessage($_acm_commerce_stock_is_in_stock_false);
    $this->assertSame($this->stockManager->getStockQuantity($_acm_commerce_stock_is_in_stock_false['sku']), 0);

    global $_acm_commerce_stock_quantity_zero;
    $this->stockManager->processStockMessage($_acm_commerce_stock_quantity_zero);
    $this->assertSame($this->stockManager->getStockQuantity($_acm_commerce_stock_quantity_zero['sku']), 0);
  }

}
