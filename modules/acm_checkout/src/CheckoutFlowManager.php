<?php

namespace Drupal\acm_checkout;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the ACM Checkout Flow plugin manager.
 */
class CheckoutFlowManager extends DefaultPluginManager {

  /**
   * Default values for each checkout pane plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
  ];

  /**
   * Constructor for CheckoutFlowManager objects.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/CheckoutFlow', $namespaces, $module_handler, 'Drupal\acm_checkout\Plugin\CheckoutFlow\CheckoutFlowInterface', 'Drupal\acm_checkout\Annotation\ACMCheckoutFlow');

    $this->alterInfo('acm_checkout_flow_pane_info');
    $this->setCacheBackend($cache_backend, 'acm_checkout_flow_pane_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    foreach (['id', 'label'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The checkout flow %s must define the %s property.', $plugin_id, $required_property));
      }
    }
  }

}
