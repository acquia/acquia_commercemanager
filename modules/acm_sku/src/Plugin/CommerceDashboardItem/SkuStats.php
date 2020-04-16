<?php

namespace Drupal\acm_sku\Plugin\CommerceDashboardItem;

use Drupal\acm\CommerceDashboardItemBase;
use Drupal\Core\Entity\EntityTypeManager;
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
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityQuery = $entity_type_manager->getStorage('acm_sku')->getQuery();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
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
