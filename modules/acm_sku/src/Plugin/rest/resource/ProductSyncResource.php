<?php

namespace Drupal\acm_sku\Plugin\rest\resource;

use Drupal\acm_sku\ProductManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ConnectorProductResource.
 *
 * @package Drupal\acm_sku\Plugin
 *
 * @ingroup acm_sku
 *
 * @RestResource(
 *   id = "acm_productsync",
 *   label = @Translation("Acquia Commerce Product Sync"),
 *   uri_paths = {
 *     "canonical" = "/productsync",
 *     "https://www.drupal.org/link-relations/create" = "/productsync"
 *   }
 * )
 */
class ProductSyncResource extends ResourceBase {

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
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\acm_sku\ProductManagerInterface $product_manager
   *   A Product Manager instance.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
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
      $container->get('logger.factory')->get('acm'),
      $container->get('acm_sku.product_manager'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Post.
   *
   * Handle Connector posting an array of product / SKU data for update.
   *
   * @param array $products
   *   Product / SKU Data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   HTTP Response object.
   */
  public function post(array $products) {
    $storeId = '';
    $requestHeaders = $this->currentRequest->headers;
    if ($requestHeaders->has('X-ACM-UUID')) {
      $storeId = $requestHeaders->get('X-ACM-UUID');
    }

    \Drupal::logger('acm_sku')->info("Updating products for acm_uuid " . $storeId . ".");

    // To avoid even E_RECOVERABLE_ERROR, we set our custom error handler.
    set_error_handler([$this, 'errorHandler']);

    $response = $this->productManager->synchronizeProducts($products, $storeId);
    return (new ModifiedResourceResponse($response));
  }

  /**
   * Custom error handler that converts E_RECOVERABLE_ERROR into an exception.
   *
   * @param int $error_number
   *   The error number.
   * @param string $error_message
   *   The error message.
   *
   * @throws \Exception
   */
  public function errorHandler(int $error_number, string $error_message) {
    switch ($error_number) {
      case E_RECOVERABLE_ERROR:
        throw new Exception($error_message, $error_number);
    }
  }

}
