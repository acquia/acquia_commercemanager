<?php

namespace Drupal\acm_promotion;

use Drupal\acm\Connector\IngestAPIWrapper;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * LoggerChannelInterface object.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

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
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Logger service.
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              IngestAPIWrapper $ingestApiWrapper,
                              LoggerChannelFactory $loggerFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->ingestApiWrapper = $ingestApiWrapper;
    $this->logger = $loggerFactory->get('acm_sku');
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
      $container->get('logger.factory')
    );
  }

}
