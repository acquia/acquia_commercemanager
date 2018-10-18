<?php

namespace Drupal\acm_sku\Plugin\rest\resource;

use Drupal\acm_sku\CategoryManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class CategorySyncResource.
 *
 * @package Drupal\acm_sku\Plugin
 *
 * @ingroup acm_sku
 *
 * @RestResource(
 *   id = "acm_categorysync",
 *   label = @Translation("Acquia Commerce Category Sync"),
 *   uri_paths = {
 *     "canonical" = "/categorysync",
 *     "https://www.drupal.org/link-relations/create" = "/categorysync"
 *   }
 * )
 */
class CategorySyncResource extends ResourceBase {

  /**
   * Taxonomy Vocabulary ID of Acquia Commerce Category Taxonomy.
   *
   * @var string
   */
  private $categoryVid;

  /**
   * Drupal Config Factory Instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * A CategoryManager instance.
   *
   * @var \Drupal\acm_sku\CategoryManagerInterface
   */
  private $categoryManager;

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
   * @param \Drupal\acm_sku\CategoryManagerInterface $category_manager
   *   A CategoryManager instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   Request.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, CategoryManagerInterface $category_manager, ConfigFactoryInterface $config_factory, Request $current_request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->categoryManager = $category_manager;
    $this->configFactory = $config_factory;
    $this->categoryVid = $config_factory
      ->get('acm.connector')
      ->get('category_vid');
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
      $container->get('acm_sku.category_manager'),
      $container->get('config.factory'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Post.
   *
   * Handle Connector posting an array of category data for update.
   *
   * @param array $categories
   *   Category data for update.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   HTTP Response.
   */
  public function post(array $categories) {
    $storeId = '';
    $requestHeaders = $this->currentRequest->headers;
    if ($requestHeaders->has('X-ACM-UUID')) {
      $storeId = $requestHeaders->get('X-ACM-UUID');
    }

    if (!$this->categoryVid) {
      return (new ModifiedResourceResponse(['success' => FALSE]));
    }

    $response = $this->categoryManager->synchronizeCategory(
      $this->categoryVid,
      $categories,
      $storeId
    );

    $response['success'] = (bool) (($response['created'] > 0) || ($response['updated'] > 0));

    return (new ModifiedResourceResponse($response));
  }

}
