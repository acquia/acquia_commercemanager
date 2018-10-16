<?php

namespace Drupal\acm_sku\Plugin\rest\resource;

use Drupal\acm_sku\ProductManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProductStockSyncResource.
 *
 * @package Drupal\acm_sku\Plugin
 *
 * @ingroup acm_sku
 *
 * @RestResource(
 *   id = "acm_productstocksync",
 *   label = @Translation("Acquia Commerce Product Stock Sync"),
 *   uri_paths = {
 *     "canonical" = "/productstocksync",
 *     "https://www.drupal.org/link-relations/create" = "/productstocksync"
 *   }
 * )
 */
class ProductStockSyncResource extends ResourceBase {

  /**
   * Drupal Config Factory Instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * A Product Manager instance.
   *
   * @var \Drupal\acm_sku\ProductManagerInterface
   */
  private $productManager;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $currentRequest;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\acm_sku\ProductManagerInterface $product_manager
   *   The product manager.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              array $serializer_formats,
                              ConfigFactoryInterface $config_factory,
                              LoggerInterface $logger,
                              ProductManagerInterface $product_manager,
                              Request $current_request
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );
    $this->productManager = $product_manager;
    $this->currentRequest = $current_request;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('config.factory'),
      $container->get('logger.factory')->get('acm'),
      $container->get('acm_sku.product_manager'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Post.
   *
   * Handle Connector posting an array of stock data for product update.
   *
   * @param array $stock
   *   Stock Data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   HTTP Response object.
   */
  public function post(array $stock) {
    $storeId = '';
    $requestHeaders = $this->currentRequest->headers;
    if ($requestHeaders->has('X-ACM-UUID')) {
      $storeId = $requestHeaders->get('X-ACM-UUID');
    }
    $response = $this->productManager->synchronizeStockData($stock, $storeId);
    return (new ModifiedResourceResponse($response));
  }

}
