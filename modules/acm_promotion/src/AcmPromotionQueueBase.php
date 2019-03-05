<?php

namespace Drupal\acm_promotion;

use Drupal\acm\Connector\IngestAPIWrapper;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Class AcmPromotionQueueBase.
 *
 * @package Drupal\acm_promotion
 */
abstract class AcmPromotionQueueBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * IngestAPIWrapper Service object.
   *
   * @var \Drupal\acm\Connector\IngestAPIWrapper
   */
  protected $ingestApiWrapper;

  /**
   * Promotion manager.
   *
   * @var \Drupal\acm_promotion\AcqPromotionsManager
   */
  protected $promotionManager;

  /**
   * LoggerChannelInterface object.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $tagInvalidate;

  /**
   * AcmPromotionAttachQueue constructor.
   *
   * @param array $configuration
   *   Plugin config.
   * @param string $plugin_id
   *   Plugin unique id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\acm\Connector\IngestAPIWrapper $ingestApiWrapper
   *   IngestAPIWrapper Service object.
   * @param \Drupal\acm_promotion\AcqPromotionsManager $promotionManager
   *   Promotion manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $tag_invalidate
   *   The cache tags invalidator.
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              IngestAPIWrapper $ingestApiWrapper,
                              AcqPromotionsManager $promotionManager,
                              LoggerChannelFactory $loggerFactory,
                              CacheTagsInvalidatorInterface $tag_invalidate) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->ingestApiWrapper = $ingestApiWrapper;
    $this->promotionManager = $promotionManager;
    $this->logger = $loggerFactory->get('acm_sku');
    $this->tagInvalidate = $tag_invalidate;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('acm.ingest_api'),
      $container->get('acm_promotion.promotions_manager'),
      $container->get('logger.factory'),
      $container->get('cache_tags.invalidator')
    );
  }

}
