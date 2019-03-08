<?php

namespace Drupal\acm_sku;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class CartFormHelper.
 *
 * @package Drupal\acm_sku
 */
class CartFormHelper {

  const CONFIG_KEY = 'acm_sku.configurable_form_settings';

  /**
   * Configurable form settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * CartFormHelper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    $this->config = $this->configFactory->get(self::CONFIG_KEY);
  }

  /**
   * Get the attribute codes with weight for particular attribute set.
   *
   * @param string $attribute_set
   *   Attribute set.
   *
   * @return array
   *   Attribute codes with weight as value.
   */
  public function getConfigurableAttributeWeights($attribute_set = 'default') {
    $attribute_set = strtolower($attribute_set);
    $weights = $this->config->get('attribute_weights');
    $set_weights = $weights[$attribute_set] ?? $weights['default'];
    asort($set_weights);
    return $set_weights;
  }

  /**
   * Set the attribute codes with weight for particular attribute set.
   *
   * @param string $attribute_set
   *   Attribute set.
   * @param array $weights
   *   Weights to set for the selected attribute set.
   */
  public function setConfigurableAttributeWeights($attribute_set = 'default', array $weights = []) {
    $attribute_set = strtolower($attribute_set);

    // Update weights for particular attribute set in config.
    $existing_weights = $this->config->get('attribute_weights');
    $existing_weights[$attribute_set] = $weights;

    $config = $this->configFactory->getEditable(self::CONFIG_KEY);
    $config->set('attribute_weights', $existing_weights);
    $config->save();

    // Reload config.
    $this->config = $this->configFactory->get(self::CONFIG_KEY);
  }

  /**
   * Check if attribute needs sorting.
   *
   * @param string $attribute_code
   *   Attribute code.
   *
   * @return bool
   *   TRUE if attribute needs to be sorted.
   */
  public function isAttributeSortable($attribute_code) {
    $sortable_options = $this->config->get('sortable_options');
    return in_array($attribute_code, $sortable_options);
  }

  /**
   * Get first attribute code based on weights for particular attribute set.
   *
   * @param string $attribute_set
   *   Attribute set.
   *
   * @return string
   *   First attribute code based on weights for particular attribute set.
   */
  public function getFirstAttribute($attribute_set = 'default') {
    $weights = $this->getConfigurableAttributeWeights($attribute_set);
    $attributes = $weights ? array_keys($weights) : [];
    return !empty($attributes) ? reset($attributes) : '';
  }

  /**
   * Check if we need to show quantity field.
   *
   * @return bool
   *   TRUE if quantity field is to be shown.
   */
  public function showQuantity() {
    return (bool) $this->config->get('show_quantity');
  }

}
