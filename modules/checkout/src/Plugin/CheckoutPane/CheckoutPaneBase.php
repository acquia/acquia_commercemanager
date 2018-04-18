<?php

namespace Drupal\acm_checkout\Plugin\CheckoutPane;

use Drupal\acm_checkout\Plugin\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for ACM Checkout Pane plugins.
 */
abstract class CheckoutPaneBase extends PluginBase implements CheckoutPaneInterface {

  /**
   * The parent checkout flow.
   *
   * @var \Drupal\acm_checkout\Plugin\CheckoutFlow\CheckoutFlowWithPanesInterface
   */
  protected $checkoutFlow;

  /**
   * Constructs a new CheckoutPaneBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\acm_checkout\Plugin\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->checkoutFlow = $checkout_flow;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentUser() {
    return $this->checkoutFlow->getCurrentUser();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentCommerceUser() {
    return $this->checkoutFlow->getCurrentCommerceUser();
  }

  /**
   * {@inheritdoc}
   */
  public function getCart() {
    return $this->checkoutFlow->getCart();
  }

  /**
   * {@inheritdoc}
   */
  public function getApiWrapper() {
    return $this->checkoutFlow->getApiWrapper();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [
      'module' => [$this->pluginDefinition['provider']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $available_steps = array_keys($this->checkoutFlow->getSteps());
    $default_step = $this->pluginDefinition['defaultStep'];
    if (!in_array($default_step, $available_steps)) {
      // The specified default step isn't available on the parent checkout flow.
      $default_step = '_disabled';
    }

    return [
      'step' => $default_step,
      'weight' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminLabel() {
    return $this->pluginDefinition['adminLabel'];
  }

  /**
   * {@inheritdoc}
   */
  public function getWrapperElement() {
    return $this->pluginDefinition['wrapperElement'];
  }

  /**
   * {@inheritdoc}
   */
  public function getStepId() {
    return $this->configuration['step'];
  }

  /**
   * {@inheritdoc}
   */
  public function setStepId($step_id) {
    $this->configuration['step'] = $step_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->configuration['weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->configuration['weight'] = $weight;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {}

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {}

}
