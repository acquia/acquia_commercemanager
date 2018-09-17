<?php

namespace Drupal\acm;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Defines a base implementation that Commerce requirements will extend.
 *
 * @package acm
 */
abstract class CommerceDashboardItemBase extends PluginBase implements CommerceDashboardItemInterface, ContainerFactoryPluginInterface {

  /**
   * The current value.
   *
   * @var string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function title() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function value() {
    return $this->value;
  }

  /**
   * Sets the current value.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $value
   *   The current value.
   */
  protected function setValue($value) {
    $this->value = $value;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function render();

}
