<?php

namespace Drupal\acm\Controller;

use Drupal\acm\CommerceDashboardItemManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Acquia Commerce Manager routes.
 */
class DashboardController extends ControllerBase {

  /**
   * Dashboard Manager
   *
   * @var \Drupal\acm\CommerceDashboardItemManager
   */
  protected $dashboardManager;

  /**
   * DashboardController constructor.
   *
   * @param \Drupal\acm\CommerceDashboardItemManager $dashboardManager
   */
  public function __construct(CommerceDashboardItemManager $dashboardManager) {
    $this->dashboardManager = $dashboardManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.acm_dashboard_item')
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    $build['content'] = [
      '#theme' => 'dashboard',
      '#items' => $this->dashboardManager->getDashboardItems(),
    ];
    $build['#attached']['library'][] = 'acm/dashboard';

    return $build;
  }

}
