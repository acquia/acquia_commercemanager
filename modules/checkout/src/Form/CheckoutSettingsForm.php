<?php

namespace Drupal\acm_checkout\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CheckoutSettingsForm.
 *
 * @package Drupal\acm_checkout\Form
 * @ingroup acm_checkout
 */
class CheckoutSettingsForm extends ConfigFormBase {

  /**
   * The checkout flow plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $acmCheckoutFlowManager;

  /**
   * Constructs a \Drupal\acm_checkout\CheckoutSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $acm_checkout_flow_manager
   *   The checkout flow manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, PluginManagerInterface $acm_checkout_flow_manager) {
    $this->setConfigFactory($config_factory);
    $this->acmCheckoutFlowManager = $acm_checkout_flow_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.acm_checkout_flow')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acm_checkout_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['acm_checkout.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('acm_checkout.settings')
      ->set('use_ajax', (int) $form_state->getValue('use_ajax'))
      ->set('validate_saved_address', (int) $form_state->getValue('validate_saved_address'))
      ->set('saved_address_review_text', $form_state->getValue('saved_address_review_text'))
      ->set('saved_address_failed_text', $form_state->getValue('saved_address_failed_text'))
      ->set('validate_billing_address', (int) $form_state->getValue('validate_billing_address'))
      ->set('billing_address_review_text', $form_state->getValue('billing_address_review_text'))
      ->set('billing_address_failed_text', $form_state->getValue('billing_address_failed_text'))
      ->set('validate_shipping_address', (int) $form_state->getValue('validate_shipping_address'))
      ->set('shipping_address_review_text', $form_state->getValue('shipping_address_review_text'))
      ->set('shipping_address_failed_text', $form_state->getValue('shipping_address_failed_text'))
      ->set('allow_guest_checkout', (int) $form_state->getValue('allow_guest_checkout'))
      ->set('checkout_flow_plugin', $form_state->getValue('checkout_flow_plugin'))
      ->save();

    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('acm_checkout.settings');
    $default_address_review_text = $this->t('Your addresses have gone through an address validation system, the information may have changed. Your billing address must match the address on your credit card statement. Please confirm all of the details for your order before proceeding.');
    $default_address_failed_text = $this->t('One of your addresses cannot be validated. Please click back to re-enter the address and try again.');

    $form['use_ajax'] = [
      '#type' => 'checkbox',
      '#title' => t('Use AJAX'),
      '#default_value' => $config->get('use_ajax'),
      '#description' => $this->t('Turns the checkout flow into a single page application.'),
    ];

    $form['allow_guest_checkout'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow guest checkout'),
      '#default_value' => $config->get('allow_guest_checkout'),
      '#description' => $this->t('Determines whether or not customers can checkout as a guest.'),
    ];

    $form['validate_saved_address'] = [
      '#type' => 'checkbox',
      '#title' => t('Validate saved addresses'),
      '#default_value' => $config->get('validate_saved_address'),
      '#description' => $this->t('If enabled, this will validate addresses when saved on the account page.'),
    ];

    $form['saved_address_review_text'] = [
      '#type' => 'textarea',
      '#description' => $this->t('The text to display when a validated saved address needs to be reviewed.'),
      '#title' => $this->t('Saved address validation review text'),
      '#default_value' => $config->get('saved_address_review_text') ?: $default_address_review_text,
      '#states' => [
        'visible' => [
          ':input[name="validate_saved_address"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['saved_address_failed_text'] = [
      '#type' => 'textarea',
      '#description' => $this->t('The text to display when an address fails validation.'),
      '#title' => $this->t('Saved address validation failed text'),
      '#default_value' => $config->get('saved_address_failed_text') ?: $default_address_failed_text,
      '#states' => [
        'visible' => [
          ':input[name="validate_saved_address"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['validate_billing_address'] = [
      '#type' => 'checkbox',
      '#title' => t('Validate billing addresses'),
      '#default_value' => $config->get('validate_billing_address'),
      '#description' => $this->t('If enabled, this will validate billing addresses.'),
    ];

    $form['billing_address_review_text'] = [
      '#type' => 'textarea',
      '#description' => $this->t('The text to display when a validated billing address needs to be reviewed.'),
      '#title' => $this->t('Billing address validation review text'),
      '#default_value' => $config->get('billing_address_review_text') ?: $default_address_review_text,
      '#states' => [
        'visible' => [
          ':input[name="validate_billing_address"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['billing_address_failed_text'] = [
      '#type' => 'textarea',
      '#description' => $this->t('The text to display when a billing address fails validation.'),
      '#title' => $this->t('Billing address validation failed text'),
      '#default_value' => $config->get('billing_address_failed_text') ?: $default_address_failed_text,
      '#states' => [
        'visible' => [
          ':input[name="validate_billing_address"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['validate_shipping_address'] = [
      '#type' => 'checkbox',
      '#title' => t('Validate shipping addresses'),
      '#default_value' => $config->get('validate_shipping_address'),
      '#description' => $this->t('If enabled, this will validate shipping addresses.'),
    ];

    $form['shipping_address_review_text'] = [
      '#type' => 'textarea',
      '#description' => $this->t('The text to display when a validated shipping address needs to be reviewed.'),
      '#title' => $this->t('Shipping address validation review text'),
      '#default_value' => $config->get('shipping_address_review_text') ?: $default_address_review_text,
      '#states' => [
        'visible' => [
          ':input[name="validate_shipping_address"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['shipping_address_failed_text'] = [
      '#type' => 'textarea',
      '#description' => $this->t('The text to display when a shipping address fails validation.'),
      '#title' => $this->t('Shipping address validation failed text'),
      '#default_value' => $config->get('shipping_address_failed_text') ?: $default_address_failed_text,
      '#states' => [
        'visible' => [
          ':input[name="validate_shipping_address"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $options = [];
    foreach ($this->acmCheckoutFlowManager->getDefinitions() as $plugin_id => $plugin) {
      $options[$plugin_id] = $plugin['label'];
    }

    $form['checkout_flow_plugin'] = [
      '#type' => 'radios',
      '#title' => t('Checkout Flow Plugin'),
      '#options' => $options,
      '#default_value' => $config->get('checkout_flow_plugin') ?: 'multistep_default',
      '#description' => $this->t('The checkout flow plugin to use.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

}
