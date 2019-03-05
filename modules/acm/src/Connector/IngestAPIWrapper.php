<?php

namespace Drupal\acm\Connector;

use Drupal\acm\I18nHelper;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * IngestAPIWrapper class.
 */
class IngestAPIWrapper {

  use \Drupal\acm\Connector\IngestRequestTrait;

  /**
   * Configured size of page.
   *
   * @var int
   */
  protected $productPageSize;

  /**
   * Instance of I18nHelper service.
   *
   * @var \Drupal\acm\I18nHelper
   */
  protected $i18nHelper;

  /**
   * Debug dir path.
   *
   * @var string
   */
  protected $debugDir;

  /**
   * API version identifier.
   *
   * @var string
   */
  protected $apiVersion;

  /**
   * Constructor.
   *
   * @param ClientFactory $client_factory
   *   Object of ClientFactory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Object of ConfigFactoryInterface.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   Object of LoggerFactory.
   * @param \Drupal\acm\I18nHelper $i18nHelper
   *   Instance of I18nHelper service.
   */
  public function __construct(ClientFactory $client_factory, ConfigFactoryInterface $config_factory, LoggerChannelFactory $logger_factory, I18nHelper $i18nHelper) {
    $this->clientFactory = $client_factory;
    $this->logger = $logger_factory->get('acm_sku');
    $this->apiVersion = $config_factory->get('acm.connector')->get('api_version');
    $this->debug = $config_factory->get('acm.connector')->get('debug');
    $this->debugDir = $config_factory->get('acm.connector')->get('debug_dir');
    $this->productPageSize = $config_factory->get('acm.connector')->get('product_page_size');
    $this->i18nHelper = $i18nHelper;
  }

  /**
   * Performs full connector sync.
   *
   * @param string $skus
   *   List of SKUs.
   * @param int $product_page_size
   *   Override for product page size.
   * @param string $store_id
   *   Optional store_id if specified you must specify a langcode.
   * @param string $langcode
   *   Optional laguage code. If specified you must specify a $storeId.
   * @param string $categoryId
   *   Optional. The category ID to sync from. If specified, skus are ignored
   *   and all skus of that category are synchronised.
   */
  public function productFullSync($skus = '', $product_page_size = 0, $store_id = "", $langcode = "", $categoryId = "") {
    if (empty($product_page_size)) {
      $product_page_size = (int) $this->productPageSize;
    }
    else {
      $product_page_size = (int) $product_page_size;
    }

    if (!($store_id && $langcode)) {
      $languageMap = $this->i18nHelper->getStoreLanguageMapping();
    }
    else {
      $languageMap = [$langcode => $store_id];
    }

    foreach ($languageMap as $langcode => $store_id) {
      if (empty($store_id)) {
        continue;
      }

      // At 20180228 store_id *is* acm_uuid is enforced
      // $store_id is sent in the query string
      // $acm_uuid is sent in the X-ACM-UUID header
      // It must only be this way:
      $acm_uuid = $store_id;

      if ($this->debug && !empty($this->debugDir)) {
        // Export product data into file.
        $filename = $this->debugDir . '/products_' . $langcode . '.data';
        $fp = fopen($filename, 'w');
        fclose($fp);
      }

      $endpoint = $this->apiVersion . '/ingest/product/sync';

      // $store_id is sent in the query string.
      $doReq = function ($client, $opt) use ($endpoint, $store_id, $skus, $product_page_size, $categoryId) {
        if ($product_page_size > 0) {
          $opt['query']['page_size'] = $product_page_size;
        }

        if (!empty($categoryId)) {
          $opt['query']['category_id'] = (string) $categoryId;

          // Function tryIngestRequest always sets the skus.
          // But in this case (category sync) we force no skus
          // always send all the skus in the category.
          unset($opt['query']['skus']);
        }
        elseif (!empty($skus)) {
          $opt['query']['skus'] = $skus;
        }

        $opt['query']['store_id'] = $store_id;

        // To allow hmac sign to be verified properly we need them in asc order.
        // Really?
        ksort($opt['query']);

        return $client->post($endpoint, $opt);
      };

      try {
        // $acm_uuid is set in the header of the client.
        $this->tryIngestRequest($doReq, 'productFullSync', 'products', $skus, $acm_uuid);
      }
      catch (ConnectorException $e) {
        throw new RouteException(__FUNCTION__, $e->getMessage(), $e->getCode());
      }
    }
  }

}
