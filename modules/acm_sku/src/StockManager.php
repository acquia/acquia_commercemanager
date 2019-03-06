<?php

namespace Drupal\acm_sku;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\I18nHelper;
use Drupal\acm_sku\Entity\SKUInterface;
use Drupal\acm_sku\Entity\SKU;
use Drupal\acm_sku_stock\Event\StockUpdatedEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class StockManager.
 *
 * @package Drupal\acm_sku
 */
class StockManager {

  /**
   * DB Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * API Wrapper.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  private $apiWrapper;

  /**
   * I18n Helper.
   *
   * @var \Drupal\acm\I18nHelper
   */
  private $i18nHelper;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Lock.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  private $lock;

  /**
   * Event Dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $dispatcher;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * StockManager constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   DB Connection.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   API Wrapper.
   * @param \Drupal\acm\I18nHelper $i18n_helper
   *   I18n Helper.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   Lock.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event Dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger.
   */
  public function __construct(Connection $connection,
                              APIWrapperInterface $api_wrapper,
                              I18nHelper $i18n_helper,
                              ConfigFactoryInterface $config_factory,
                              LockBackendInterface $lock,
                              EventDispatcherInterface $dispatcher,
                              LoggerChannelFactoryInterface $logger_factory) {
    $this->connection = $connection;
    $this->apiWrapper = $api_wrapper;
    $this->i18nHelper = $i18n_helper;
    $this->configFactory = $config_factory;
    $this->lock = $lock;
    $this->dispatcher = $dispatcher;
    $this->logger = $logger_factory->get('ACMStockManager');
  }

  /**
   * Check if product is in stock.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   SKU Entity.
   *
   * @return bool
   *   TRUE if product is in stock.
   */
  public function isProductInStock(SKU $sku) {
    $sku_string = $sku->getSku();

    $static = &drupal_static(self::class . '_' . __FUNCTION__, []);
    if (isset($static[$sku_string])) {
      return $static[$sku_string];
    }

    // Initialise static value with FALSE.
    $static[$sku_string] = FALSE;

    $stock = $this->getStock($sku_string);
    if (empty($stock['status'])) {
      return FALSE;
    }

    // Check status + quantity of children if configurable.
    switch ($sku->bundle()) {
      case 'configurable':
        // For configurable product to be in-stock only one in-stock child
        // is enough.
        foreach ($sku->get('field_configured_skus')->getValue() as $child) {
          if (empty($child['value'])) {
            continue;
          }

          $child_sku = SKU::loadFromSku($child['value']);
          if ($child_sku instanceof SKU) {
            if ($this->getStockQuantity($child_sku->getSku()) > 0) {
              $static[$sku_string] = TRUE;
              break;
            }
          }
        }
        break;

      case 'simple':
      default:
        $static[$sku_string] = (bool) $this->getStockQuantity($sku->getSku());
        break;
    }

    return $static[$sku_string];
  }

  /**
   * Get stock quantity.
   *
   * @param string $sku
   *   SKU string.
   *
   * @return int
   *   Quantity, 0 if status flag is set to false.
   */
  public function getStockQuantity(string $sku) {
    $stock = $this->getStock($sku);

    if (empty($stock['status'])) {
      return 0;
    }

    // @TODO: For now there is no scenario in which we have quantity in float.
    // We have kept the database field to match what is there in MDC and code
    // can be updated later to match that. Casting it to int for now.
    return (int) $stock['quantity'];
  }

  /**
   * Get current stock data for SKU from DB.
   *
   * @param string $sku
   *   SKU string.
   *
   * @return array
   *   Stock data with keys [sku, status, quantity].
   */
  public function getStock(string $sku) {
    $query = $this->connection->select('acm_sku_stock');
    $query->fields('acm_sku_stock');
    $query->condition('sku', $sku);
    $result = $query->execute()->fetchAll();

    // We may not have any entry.
    if (empty($result)) {
      return [];
    }

    // Log if more than one found.
    if (count($result) > 1) {
      $this->logger->error('Duplicate entries found for stock of sku @sku.', [
        '@sku' => $sku,
      ]);
    }

    // Get the first result.
    $data = reset($result);

    return (array) $data;
  }

  /**
   * Refresh stock for an SKU from API.
   *
   * @param string $sku
   *   SKU string.
   */
  public function refreshStock(string $sku) {
    try {
      $stock = $this->apiWrapper->skuStockCheck($sku);
      $this->processStockMessage($stock);
    }
    catch (\Exception $e) {
      $this->logger->error('Exception occurred while resetting stock for sku: @sku, message: @message', [
        '@sku' => $sku,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Update stock data for particular sku.
   *
   * @param string $sku
   *   SKU.
   * @param int|float $quantity
   *   Quantity.
   * @param int $status
   *   Stock status.
   *
   * @return bool
   *   TRUE if stock status changed.
   *
   * @throws \Exception
   */
  public function updateStock($sku, $quantity, $status) {
    // Update the stock now.
    $this->acquireLock($sku);

    // First try to check if stock changed.
    $current = $this->getStock($sku);

    // Update only if value changed.
    if (empty($current) || $current['status'] != $status || $current['quantity'] != $quantity) {
      $new = [
        'quantity' => $quantity,
        'status' => $status,
      ];

      $this->connection->merge('acm_sku_stock')
        ->key(['sku' => $sku])
        ->fields($new)
        ->execute();

      $status_changed = $current
        ? $this->isStockStatusChanged($current, $new)
        : TRUE;

      $sku_entity = SKU::loadFromSku($sku);
      if ($sku_entity instanceof SKUInterface) {
        $low_quantity = $this->isQuantityLow($new);
        $event = new StockUpdatedEvent($sku_entity, $status_changed, $low_quantity);
        $this->dispatcher->dispatch(StockUpdatedEvent::EVENT_NAME, $event);
      }

      return $status_changed;
    }

    $this->releaseLock($sku);
    return FALSE;
  }

  /**
   * Process stock message received in API.
   *
   * @param array $stock
   *   Stock data for particular SKU.
   * @param int $store_id
   *   Store ID.
   *
   * @throws \Exception
   */
  public function processStockMessage(array $stock, $store_id = 0) {
    // Sanity check.
    if (!isset($stock['sku']) || !strlen($stock['sku'])) {
      $this->logger->error('Invalid or empty product SKU. Stock message: @message', [
        '@message' => json_encode($stock),
      ]);

      return;
    }

    $langcode = NULL;

    if (empty($store_id) && isset($stock['store_id'])) {
      $store_id = $stock['store_id'];
    }

    // Check for stock is valid for current site.
    // If store id not available, we consider it as valid message.
    if ($store_id) {
      $langcode = $this->i18nHelper->getLangcodeFromStoreId($store_id);

      if (empty($langcode)) {
        // It could be for a different store/website, don't do anything.
        $this->logger->info('Ignored stock message for different store. Message: @message', [
          '@message' => json_encode($stock),
        ]);

        return;
      }
    }

    // We get qty in product data and quantity in stock push or from stock api.
    $quantity = array_key_exists('qty', $stock) ? $stock['qty'] : $stock['quantity'];
    $stock_status = isset($stock['is_in_stock']) ? (int) $stock['is_in_stock'] : 1;

    $changed = $this->updateStock($stock['sku'], $quantity, $stock_status);

    $this->logger->info('@operation stock for sku @sku. Message: @message', [
      '@operation' => $changed ? 'Updated' : 'Processed',
      '@sku' => $stock['sku'],
      '@message' => json_encode($stock),
    ]);
  }

  /**
   * Helper function to acquire lock.
   *
   * @param string $sku
   *   SKU string.
   */
  private function acquireLock(string $sku) {
    $lock_key = self::class . ':' . $sku;
    do {
      $lock_acquired = $this->lock->acquire($lock_key);

      // Sleep for half a second before trying again.
      if (!$lock_acquired) {
        usleep(500000);
      }
    } while (!$lock_acquired);
  }

  /**
   * Helper function to release lock.
   *
   * @param string $sku
   *   SKU string.
   */
  private function releaseLock(string $sku) {
    $lock_key = self::class . ':' . $sku;
    $this->lock->release($lock_key);
  }

  /**
   * Get field code for which value is requested.
   *
   * @param array $old
   *   Old stock.
   * @param array $new
   *   New stock.
   *
   * @return bool
   *   TRUE if stock status changed.
   */
  private function isStockStatusChanged(array $old, array $new) {
    if ($old['status'] != $new['status']) {
      return TRUE;
    }

    $had_quantity = (bool) $old['quantity'];
    $has_quantity = (bool) $new['quantity'];

    // Either it was zero before or zero now and status says in stock,
    // we consider it is changed.
    if ($new['status'] && $had_quantity != $has_quantity) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if quantity is low.
   *
   * @param array $new
   *   New stock.
   *
   * @return bool
   *   TRUE if quantity is low.
   */
  private function isQuantityLow(array $new) {
    // No need to say low stock for OOS items.
    if (!($new['status'])) {
      return FALSE;
    }

    $low_stock = (int) $this->configFactory
      ->get('acm_sku_stock.settings')
      ->get('low_stock');

    return ($new['quantity'] && $new['quantity'] < $low_stock);
  }

  /**
   * Remove stock entry.
   *
   * @param string $sku
   *   SKU for which stock entry needs to be removed.
   */
  public function removeStockEntry(string $sku) {
    if (empty($sku)) {
      return;
    }

    // Confirm SKU is not available in any language.
    $query = $this->connection->select('acm_sku_field_data', 'sku');
    $query->condition('sku', $sku);
    $query->addField('sku', 'sku');
    $result = $query->execute()->fetchAssoc();

    if (!empty($result)) {
      return;
    }

    // Remove stock only after SKU is removed in all the languages.
    $this->connection->delete('acm_sku_stock')
      ->condition('sku', $sku)
      ->execute();
  }

}
