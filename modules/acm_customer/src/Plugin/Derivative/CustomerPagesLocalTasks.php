<?php

namespace Drupal\acm_customer\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines dynamic local tasks for customer pages plugins.
 */
class CustomerPagesLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The customer pages plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $customerPagesManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $acm_customer_pages_manager
   *   The customer pages plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, PluginManagerInterface $acm_customer_pages_manager) {
    $this->configFactory = $config_factory;
    $this->customerPagesManager = $acm_customer_pages_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.acm_customer_pages')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $plugin_name = $this->configFactory
      ->get('acm.commerce_users')
      ->get('customer_pages_plugin');

    if (!$plugin_name) {
      return $this->derivatives;
    }

    $plugin = $this->customerPagesManager->createInstance($plugin_name);
    $pages = $plugin->getVisiblePages();
    $primary_page_ids = array_keys($pages);
    $primary_page_id = reset($primary_page_ids);
    $route_name = 'acm_customer.view_page';

    foreach ($pages as $page_id => $page_config) {
      $task_id = "acm_customer.view_page_local_task_{$page_id}";

      $title = $page_id;
      if (isset($page_config['local_task'])) {
        $title = $page_config['local_task'];
      }
      elseif (isset($page_config['title'])) {
        $title = $page_config['title'];
      }

      $this->derivatives[$task_id] = [
        'route_name' => $route_name,
        'base_route' => $route_name,
        'title' => $title,
        'route_parameters' => [
          'page' => $page_id,
          'action' => 'view',
          'id' => 0,
        ],
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
