<?php

namespace Drupal\acm_diagnostic;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Defines a base implementation that Commerce requirements will extend.
 *
 * @package acm_diagnostic
 */
abstract class CommerceRequirementBase extends PluginBase implements CommerceRequirementInterface, ContainerFactoryPluginInterface {

  /**
   * The current value.
   *
   * @var string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected $value;

  /**
   * The description of the requirement/status.
   *
   * @var string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected $description;

  /**
   * The requirement's result/severity level.
   *
   * @var int
   */
  protected $severity;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * CommerceRequirementBase constructor.
   *
   * @param array $configuration
   *   The configuration array for the plugin instance.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    try {
      $this->severity = $this->verify();
    }
    catch (\Exception $e) {
      $this->setValue($this->t("An exception occurred."));
      $this->setDescription($this->t('Error: @error', ['@error' => $e->getMessage()]));
      $this->severity = REQUIREMENT_ERROR;
    }
  }

  /**
   * Ensure the requirement is met from the site.
   *
   * @return int
   *   The requirement status code:
   *     - REQUIREMENT_INFO: For info only.
   *     - REQUIREMENT_OK: The requirement is satisfied.
   *     - REQUIREMENT_WARNING: The requirement failed with a warning.
   *     - REQUIREMENT_ERROR: The requirement failed with an error.
   */
  abstract public function verify();

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
   * {@inheritdoc}
   */
  public function description() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function severity() {
    return $this->severity;
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
   * Sets the description of the requirement/status.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the requirement/status.
   */
  protected function setDescription($description) {
    $this->description = $description;
  }

}
