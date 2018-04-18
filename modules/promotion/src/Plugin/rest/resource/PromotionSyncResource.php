<?php

namespace Drupal\acm_promotion\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\acm_promotion\AcmPromotionsManager;

/**
 * Class PromotionSyncResource.
 *
 * @package Drupal\acm_promotion\Plugin
 *
 * @ingroup acm_promotion
 *
 * @RestResource(
 *   id = "acm_promotionsync",
 *   label = @Translation("Acquia Commerce Promotion Sync"),
 *   uri_paths = {
 *     "canonical" = "/promotionsync",
 *     "https://www.drupal.org/link-relations/create" = "/promotionsync"
 *   }
 * )
 */
class PromotionSyncResource extends ResourceBase {

  /**
   * A Promotion Manager instance.
   *
   * @var \Drupal\acm_promotion\AcmPromotionsManager
   */
  private $promotionsManager;

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
   * @param \Drupal\acm_promotion\AcmPromotionsManager $promotions_manager
   *   A Promotion Manager instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AcmPromotionsManager $promotions_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->promotionsManager = $promotions_manager;
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
      $container->get('acm_promotion.promotions_manager')
    );
  }

  /**
   * Post.
   *
   * Handle Connector posting an array of product / SKU data for update.
   *
   * @param array $promos
   *   Promotions data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   HTTP Response object.
   */
  public function post(array $promos = []) {
    // At this time, we expect to recieve the promo code (string), enabled
    // (boolean), and sku (string[]). The already existing Promotion Manager
    // expects different parameters, so we are moving some data around to
    // reuse the existing code.
    foreach ($promos as &$promo) {
      // Name is required to create the node.
      if (!isset($promo['name'])) {
        $promo['name'] = $promo['code'];
      }

      // Many lookups rely on rule_id and it is required on the admin screen.
      if (!isset($promo['rule_id'])) {
        $promo['rule_id'] = $promo['code'];
      }

      // This allows the node to be published.
      if (!isset($promo['status'])) {
        $promo['status'] = $promo['enabled'];
      }
    }

    $response = $this->promotionsManager->processPromotions($promos);
    return (new ResourceResponse($response));
  }

}
