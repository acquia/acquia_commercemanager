<?php

namespace Drupal\acm_customer\Plugin\CustomerForm;

use Drupal\acm_customer\Plugin\CustomerPages\CustomerPagesInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for ACMCustomerForm plugins.
 */
abstract class CustomerFormBase extends PluginBase implements CustomerFormInterface {

  /**
   * The customer pages plugin manager.
   *
   * @var \Drupal\acm_customer\Plugin\CustomerPages\CustomerPagesInterface
   */
  protected $customerPagesManager;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\acm_customer\Plugin\CustomerPages\CustomerPagesInterface $customer_pages_manager
   *   The customer pages plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CustomerPagesInterface $customer_pages_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->customerPagesManager = $customer_pages_manager;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigFactory() {
    return $this->customerPagesManager->getConfigFactory();
  }

  /**
   * {@inheritdoc}
   */
  public function getCart() {
    return $this->customerPagesManager->getCart();
  }

  /**
   * {@inheritdoc}
   */
  public function getApiWrapper() {
    return $this->customerPagesManager->getApiWrapper();
  }

  /**
   * {@inheritdoc}
   */
  public function getCommerceUserManager() {
    return $this->customerPagesManager->getCommerceUserManager();
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
    $available_pages = array_keys($this->customerPagesManager->getPages());
    $default_page = $this->pluginDefinition['defaultPage'];
    if (!in_array($default_page, $available_pages)) {
      // Default page isn't available in the customer pages plugin.
      $default_page = '_disabled';
    }

    return [
      'page' => $default_page,
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
  public function getWrapperElement() {
    return $this->pluginDefinition['wrapperElement'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPageId() {
    return $this->configuration['page'];
  }

  /**
   * {@inheritdoc}
   */
  public function setPageId($page_id) {
    $this->configuration['page'] = $page_id;
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
  public function validateForm(array &$form, FormStateInterface $form_state, array &$complete_form) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, array &$complete_form) {}

}
