<?php

namespace Drupal\acm\Plugin\CommerceDashboardItem;

use Drupal\acm\CommerceDashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConnectorAccessiblityRequirement.
 *
 * @CommerceDashboardItem(
 *   id = "dummy_tile",
 *   title = @Translation("Dummy Tile"),
 *   weight = -1,
 *   group = "tile",
 * )
 */
class DummyTile extends CommerceDashboardItemBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      '#theme' => "dashboard_item__" . $this->pluginDefinition['group'],
      '#title' => $this->title(),
      '#value' => 'Hello dashboard tile',
    ];
  }

}
