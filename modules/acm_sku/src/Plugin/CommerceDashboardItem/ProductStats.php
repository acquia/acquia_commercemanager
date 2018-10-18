<?php

namespace Drupal\acm_sku\Plugin\CommerceDashboardItem;

use Drupal\acm\CommerceDashboardItemBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Url;
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
      $config_url = Url::fromRoute('acm.configuration.connector')->toString();
      return [
        '#theme' => "dashboard_item__" . $this->pluginDefinition['group'],
        '#title' => $this->title(),
        '#value' => $this->t('The connector has not been configured to use a node type for products. <a href="@config-page">Set the product node type</a>.', [
          '@config-page' => $config_url,
        ]),
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
