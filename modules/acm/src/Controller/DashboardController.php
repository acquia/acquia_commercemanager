<?php

namespace Drupal\acm\Controller;

use Drupal\acm\CommerceDashboardItemManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Acquia Commerce Manager routes.
 */
class DashboardController extends ControllerBase {

  /**
   * Dashboard Manager.
   *
   * @var \Drupal\acm\CommerceDashboardItemManager
   */
  protected $dashboardManager;

  /**
   * System Manager Service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected $systemManager;

  /**
   * DashboardController constructor.
   *
   * @param \Drupal\acm\CommerceDashboardItemManager $dashboardManager
   *   Dashboard manager.
   * @param \Drupal\system\SystemManager $systemManager
   *   System manager service.
   */
  public function __construct(CommerceDashboardItemManager $dashboardManager, SystemManager $systemManager) {
    $this->dashboardManager = $dashboardManager;
    $this->systemManager = $systemManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.acm_dashboard_item'),
      $container->get('system.manager')
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    $build['content'] = [
      '#theme' => 'dashboard',
      '#items' => $this->dashboardManager->getDashboardItems(),
      '#menu' => $this->systemManager->getBlockContents(),
    ];
    $build['#attached']['library'][] = 'acm/dashboard';

    return $build;
  }

}
