<?php

namespace Drupal\acm_payment\Plugin\PaymentMethod;

use Drupal\acm_cart\CartInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for ACM Payment Method plugins.
 */
abstract class PaymentMethodBase extends PluginBase implements PaymentMethodInterface {

  /**
   * The shopping cart.
   *
   * @var \Drupal\acm_cart\Cart
   */
  protected $cart;

  /**
   * Constructs a new PaymentMethodBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\acm_cart\CartInterface $cart
   *   The shopping cart.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CartInterface $cart) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->cart = $cart;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getCart() {
    return $this->cart;
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
    return [
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
  public function buildPaymentSummary() {
    return 'Credit Cart Details here';
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['payment_details'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['payment_details'],
      ],
    ];

    $pane_form['payment_details'] += $this->buildPaymentForm($pane_form['payment_details'], $form_state, $complete_form);
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['cc_number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Credit Card Number'),
      '#default_value' => '',
      '#required' => TRUE,
      '#placeholder' => $this->t('1111 1111 1111 1111'),
    ];
    $pane_form['cc_exp_month'] = [
      '#type' => 'select',
      '#title' => $this->t('Expiration Month'),
      '#options' => [
        '01' => '01',
        '02' => '02',
        '03' => '03',
        '04' => '04',
        '05' => '05',
        '06' => '06',
        '07' => '07',
        '08' => '08',
        '09' => '09',
        '10' => '10',
        '11' => '11',
        '12' => '12',
      ],
      '#default_value' => '',
      '#required' => TRUE,
    ];
    $year_options = [];
    $years_out = 10;
    for ($i = 0; $i <= $years_out; $i++) {
      $year = date('Y', strtotime("+{$i} year"));
      $year_options[$year] = $year;
    }
    $pane_form['cc_exp_year'] = [
      '#type' => 'select',
      '#title' => $this->t('Expiration Year'),
      '#options' => $year_options,
      '#default_value' => '',
      '#required' => TRUE,
    ];
    $pane_form['cc_cvv'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CVV'),
      '#default_value' => '',
      '#required' => TRUE,
      '#placeholder' => $this->t('123'),
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaymentForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaymentForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $payment_details = $values['payment_details'];

    $cart = $this->getCart();
    $cart->setPaymentMethodData([
      'cc_type' => '',
      'cc_exp_month' => $payment_details['cc_exp_month'],
      'cc_exp_year' => $payment_details['cc_exp_year'],
      'cc_number' => $payment_details['cc_number'],
      'cc_cvv' => $payment_details['cc_cvv'],
    ]);
  }

}
