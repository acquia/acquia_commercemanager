<?php

namespace Drupal\acm_sku;

use Drupal\acm\Connector\APIWrapper;
use Drupal\acm_sku\Entity\SKU;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class AcmSkuLinkedSku.
 */
class AcmSkuLinkedSku {

  /**
   * The connector api wrapper.
   *
   * @var \Drupal\acm\Connector\APIWrapper
   */
  protected $apiWrapper;

  /**
   * The cache bin object.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * AcmSkuLinkedSku constructor.
   *
   * @param \Drupal\acm\Connector\APIWrapper $api_wrapper
   *   The connector api wrapper.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache bin object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(APIWrapper $api_wrapper, CacheBackendInterface $cache, ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->apiWrapper = $api_wrapper;
    $this->cache = $cache;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->languageManager = $language_manager;
  }

  /**
   * Get linked skus for a given sku by linked type.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   The sku entity.
   * @param string $type
   *   The linked type. Like - related/crosssell/upsell.
   *
   * @return array
   *   All linked skus of given type.
   */
  public function getLinkedskus(SKU $sku, $type = LINKED_SKU_TYPE_ALL) {
    $linked_skus = [];

    // Cache key is like - 'acm_sku:linked_skus:123'.
    $cache_key = 'acm_sku:linked_skus:' . $sku->id();

    // Get cached data.
    $cache = $this->cache->get($cache_key);

    // If already cached.
    if ($cache) {
      // If only for specific type like cross_sell/upsell/related.
      if (isset($cache->data[$type])) {
        return $cache->data[$type];
      }
      elseif ($type == LINKED_SKU_TYPE_ALL && isset($cache->data[LINKED_SKU_TYPE_RELATED]) && isset($cache->data[LINKED_SKU_TYPE_CROSSSELL]) && isset($cache->data[LINKED_SKU_TYPE_UPSELL])) {
        // Returning everything in case of 'all' and all keys set.
        return $cache->data;
      }
    }

    try {
      // Get linked skus and set the cache.
      $linked_skus = $this->apiWrapper->getLinkedskus($sku->getSku(), $type);

      // If cache is set already and we just fetching info of perticular type,
      // just updating the existing cache.
      if ($type != LINKED_SKU_TYPE_ALL && $cache) {
        $cache->data[$type] = $linked_skus[$type];
        $linked_skus = $cache->data;
      }
      elseif ($type != LINKED_SKU_TYPE_ALL && !$cache) {
        $linked_skus = [$type => $linked_skus[$type]];
      }

      // Set the cache.
      if ($cache_lifetime = $this->configFactory->get('acm_sku.settings')->get('linked_skus_cache_max_lifetime')) {
        $cache_lifetime += \Drupal::time()->getRequestTime();
        $this->cache->set($cache_key, $linked_skus, $cache_lifetime, ['acm_sku:' . $sku->id()]);
      }

      // Return the data.
      return $type != LINKED_SKU_TYPE_ALL ? $linked_skus[$type] : $linked_skus;
    }
    catch (\Exception $e) {
      // If something bad happens, log the error.
      $this->loggerFactory->get('acm_sku')->emergency('Unable to get the @linked_sku_type linked skus for @sku : @message', [
        '@linked_sku_type' => $type,
        '@sku' => $sku->getSku(),
        '@message' => $e->getMessage(),
      ]
      );
    }

    return $linked_skus;
  }

}
