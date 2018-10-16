<?php

namespace Drupal\acm;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the CommerceRequirementsManager plugin manager.
 *
 * @package acm
 */
class CommerceDashboardItemManager extends DefaultPluginManager {

  /**
   * CommerceRequirementManager constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/CommerceDashboardItem',
      $namespaces,
      $module_handler,
      'Drupal\acm\CommerceDashboardItemInterface',
      'Drupal\acm\Annotation\CommerceDashboardItem'
    );

    $this->alterInfo('acm_dashboard');
  }

  /**
   * Creates pre-configured instances of all plugins.
   *
   * @return \Drupal\acm\CommerceDashboardItemInterface[][]
   *   An array of fully configured plugin instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If an instance cannot be created, such as if the ID is invalid.
   */
  public function getDashboardItems() {
    $instances = [];
    $sortedDefinitions = [];
    $definitions = $this->getDefinitions();

    foreach ($definitions as $id => $definition) {
      $sortedDefinitions[$definition['group']][$id] = $definition;
    }

    foreach ($sortedDefinitions as &$localSortedDefinitions) {
      usort($localSortedDefinitions, [$this, 'sortPlugins']);
    }

    foreach ($sortedDefinitions as $group => $localDefinitions) {
      foreach ($localDefinitions as $definition) {
        $id = $definition['id'];
        $instances[$group][$id] = $this->createInstance($id)->render();
      }
    }

    return $instances;
  }

  /**
   * Helper function for sorting Tax Types by weight.
   *
   * @param array $a
   *   Tax Type A for comparison.
   * @param array $b
   *   Tax Type B for comparison.
   *
   * @return int
   *   Returns -1 if A<B; 1 if A>B; 0 id A=B
   */
  protected function sortPlugins(array $a, array $b):int {
    if ($a['weight'] < $b['weight']) {
      return -1;
    }
    elseif ($a['weight'] > $b['weight']) {
      return 1;
    }
    else {
      return 0;
    }
  }

}
