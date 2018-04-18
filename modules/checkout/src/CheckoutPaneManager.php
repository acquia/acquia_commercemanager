<?php

namespace Drupal\acm_checkout;

use Drupal\acm_checkout\Plugin\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the ACM Checkout Pane plugin manager.
 */
class CheckoutPaneManager extends DefaultPluginManager {

  /**
   * Default values for each checkout pane plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
    'adminLabel' => '',
    'defaultStep' => '_disabled',
    'wrapperElement' => 'container',
  ];

  /**
   * Constructor for CheckoutPaneManager objects.
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
    parent::__construct('Plugin/CheckoutPane', $namespaces, $module_handler, 'Drupal\acm_checkout\Plugin\CheckoutPane\CheckoutPaneInterface', 'Drupal\acm_checkout\Annotation\ACMCheckoutPane');

    $this->alterInfo('acm_checkout_pane_pane_info');
    $this->setCacheBackend($cache_backend, 'acm_checkout_pane_pane_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = [], CheckoutFlowInterface $checkout_flow = NULL) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);
    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      $plugin = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    }
    else {
      $plugin = new $plugin_class($configuration, $plugin_id, $plugin_definition, $checkout_flow);
    }

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    foreach (['id', 'label'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The checkout pane %s must define the %s property.', $plugin_id, $required_property));
      }
    }
  }

}
