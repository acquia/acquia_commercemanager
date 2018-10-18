<?php

namespace Drupal\acm_sku\AcquiaCommerce;

use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\acm_sku\Entity\SKU;

/**
 * Provides an SKU plugin manager.
 *
 * @see \Drupal\acm_sku\AcquiaCommerce\Annotation\SKUType
 * @see \Drupal\acm_sku\Entity\SKUTypeInterface
 * @see plugin_api
 */
class SKUPluginManager extends DefaultPluginManager {

  /**
   * Constructs a SKUPluginManager object.
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
    parent::__construct(
      'Plugin/AcquiaCommerce/SKUType',
      $namespaces,
      $module_handler,
      'Drupal\acm_sku\Entity\SKUTypeInterface',
      'Drupal\acm_sku\AcquiaCommerce\Annotation\SKUType'
    );
    $this->alterInfo('sku_type_info');
    $this->setCacheBackend($cache_backend, 'sku_type_info_plugins');
    $this->factory = new ContainerFactory($this->getDiscovery());
  }

  /**
   * Takes a SKU entity and checks it for a matching plugin.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   The SKU to check for a plugin.
   *
   * @return array|null
   *   Returns the plugin definition if found, otherwise returns NULL.
   */
  public function pluginFromSku(SKU $sku) {
    if (empty($sku)) {
      return NULL;
    }

    $bundle = $sku->getType();

    if (empty($bundle)) {
      return NULL;
    }

    $plugin = NULL;

    try {
      $plugin = $this->getDefinition($bundle);
    }
    catch (PluginNotFoundException $exception) {
      \Drupal::logger('acm_sku')->notice(t("Bundle @bundle doesn't appear to
        have an implementation, please check the plugin id on your implementation
        of the SKU plugin", ['@bundle' => $bundle]));
    } finally {
      return $plugin;
    }
  }

  /**
   * Takes a type and checks it for a matching plugin.
   *
   * @param string $type
   *   The SKU to check for a plugin.
   *
   * @return array|null
   *   Returns the plugin definition if found, otherwise returns NULL.
   */
  public function pluginInstanceFromType($type) {
    $plugin = NULL;

    try {
      $plugin = $this->getDefinition($type);
      $plugin = $this->createInstance($plugin['id']);
    }
    catch (\Exception $exception) {
      \Drupal::logger('acm_sku')->notice(t("Bundle @bundle doesn't appear to
        have an implementation, please check the plugin id on your implementation
        of the SKU plugin", ['@bundle' => $type]));
    } finally {
      return $plugin;
    }
  }

}
