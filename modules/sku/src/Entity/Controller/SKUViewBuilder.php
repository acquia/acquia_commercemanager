<?php

namespace Drupal\acm_sku\Entity\Controller;

use Drupal\Core\Entity\EntityViewBuilder;

/**
 * SKUViewBuilder class.
 */
class SKUViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(array $build) {
    $build = parent::build($build);

    $sku = $build['#acm_sku'];
    $plugin_manager = \Drupal::service('plugin.manager.sku');
    $plugin_definition = $plugin_manager->pluginFromSKU($sku);

    if (empty($plugin_definition)) {
      return $build;
    }

    $plugin = $plugin_manager->createInstance($plugin_definition['id']);

    // Allow blocking of add to cart render.
    if (!isset($build['#no_add_to_cart']) || !($build['#no_add_to_cart'])) {
      // @TODO: remove custom form_builder once https://www.drupal.org/node/766146 is fixed.
      $build['add_to_cart'] = \Drupal::service('acm_sku.form_builder')->getForm($plugin, $sku);
      $build['add_to_cart']['#weight'] = 50;
    }

    $build = $plugin->build($build);

    return $build;
  }

}
