<?php

namespace Drupal\acm_sku\Plugin\CommerceDashboardItem;

use Drupal\acm\CommerceDashboardItemBase;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SkuStats.
 *
 * @CommerceDashboardItem(
 *   id = "skus_stats",
 *   title = @Translation("# of SKUs"),
 *   weight = -200,
 *   group = "line",
 * )
 */
class SkuStats extends CommerceDashboardItemBase {

  /**
   * SKU entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, QueryFactory $entity_query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityQuery = $entity_query->get('acm_sku');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $num_of_skus = $this->entityQuery->count()->execute();
    return [
      '#theme' => "dashboard_item__" . $this->pluginDefinition['group'],
      '#title' => $this->title(),
      '#value' => $num_of_skus,
    ];
  }

}
