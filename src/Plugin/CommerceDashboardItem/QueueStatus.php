<?php

namespace Drupal\acm\Plugin\CommerceDashboardItem;

use Drupal\acm\CommerceDashboardItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConnectorAccessiblityRequirement.
 *
 * @CommerceDashboardItem(
 *   id = "queue_status",
 *   title = @Translation("Queue status"),
 *   weight = -100,
 *   group = "tile",
 * )
 */
class QueueStatus extends CommerceDashboardItemBase {

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

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
    // @TODO: Inject via constructor.
    // @TODO: Add button to clear queue.
    $number = \Drupal::service('acm.api')->getQueueStatus();
    return [
      '#theme' => "dashboard_item__" . $this->pluginDefinition['group'],
      '#title' => $this->title(),
      '#value' => [
        '#markup' => "<div class='heading-a'>" . $number . "</div><span>items</span>",
      ],
    ];
  }

}
