<?php

namespace Drupal\acm_sku\Plugin\CommerceDashboardItem;

use Drupal\acm\CommerceDashboardItemBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProductStats.
 *
 * @CommerceDashboardItem(
 *   id = "product_stats",
 *   title = @Translation("# of products"),
 *   weight = -100,
 *   group = "line",
 * )
 */
class ProductStats extends CommerceDashboardItemBase {

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Node Entity Query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, QueryFactory $entity_query, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityQuery = $entity_query->get('node');
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
      $container->get('entity.query'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $productContentType = $this->configFactory->get('acm.connector')
      ->get('product_node_type');
    if (empty($productContentType)) {
      return [
        '#theme' => "dashboard_item__" . $this->pluginDefinition['group'],
        '#title' => $this->title(),
        '#value' => $this->t('Content type is not configured.'),
      ];
    }
    $num_of_products = $this->entityQuery
      ->condition('type', $productContentType)
      ->count()
      ->execute();
    return [
      '#theme' => "dashboard_item__" . $this->pluginDefinition['group'],
      '#title' => $this->title(),
      '#value' => $num_of_products,
    ];
  }

}
