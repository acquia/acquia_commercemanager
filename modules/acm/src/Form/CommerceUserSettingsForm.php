<?php

namespace Drupal\acm\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommerceUserSettingsForm.
 *
 * @package Drupal\acm\Form
 * @ingroup acm_customer
 */
class CommerceUserSettingsForm extends ConfigFormBase {

  /**
   * The router builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routerBuilder;

  /**
   * The customer pages plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $customerPagesManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Routing\RouteBuilderInterface $router_builder
   *   The router builder service.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $acm_customer_pages_manager
   *   The customer pages plugin manager.
   */
  public function __construct(RouteBuilderInterface $router_builder, PluginManagerInterface $acm_customer_pages_manager) {
    $this->routerBuilder = $router_builder;
    $this->customerPagesManager = $acm_customer_pages_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.builder'),
      $container->get('plugin.manager.acm_customer_pages')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acm_commerce_users_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['acm.commerce_users'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('acm.commerce_users')
      ->set('use_ecomm_sessions', (int) $form_state->getValue('use_ecomm_sessions'))
      ->set('use_ecomm_pass_reset', (int) $form_state->getValue('use_ecomm_pass_reset'))
      ->set('ecomm_forgot_password_path', $form_state->getValue('ecomm_forgot_password_path'))
      ->set('storage_type', $form_state->getValue('storage_type'))
      ->set('external_registration_path', $form_state->getValue('external_registration_path'))
      ->set('external_login_path', $form_state->getValue('external_login_path'))
      ->set('external_logout_path', $form_state->getValue('external_logout_path'))
      ->set('customer_pages_plugin', $form_state->getValue('customer_pages_plugin'))
      ->save();

    $this->routerBuilder->rebuild();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('acm.commerce_users');

    $options = [
      'session' => $this->t('Session Storage'),
      'database_store' => $this->t('Database Storage'),
    ];

    $form['storage_type'] = [
      '#type' => 'radios',
      '#title' => t('Storage Type'),
      '#default_value' => $config->get('storage_type') ?: 'session',
      '#options' => $options,
      '#description' => $this->t('The type of user session storage to use.'),
      '#required' => TRUE,
    ];

    $form['use_ecomm_sessions'] = [
      '#type' => 'checkbox',
      '#description' => $this->t('Enable to keep customers anonymous and instead use the e-comm backend for user sessions.'),
      '#title' => $this->t('Use E-Comm Sessions'),
      '#default_value' => $config->get('use_ecomm_sessions'),
    ];

    $form['use_ecomm_pass_reset'] = [
      '#type' => 'checkbox',
      '#description' => $this->t('Enable to let the ecommerce backend generate and send the password reset tokens and links.'),
      '#title' => $this->t('Use E-Comm Password Reset'),
      '#default_value' => $config->get('use_ecomm_pass_reset'),
      '#states' => [
        'visible' => [
          ':input[name="use_ecomm_sessions"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['ecomm_forgot_password_path'] = [
      '#type' => 'textfield',
      '#description' => $this->t('The path to use for the password reset form.'),
      '#title' => $this->t('Password Reset Page'),
      '#default_value' => $config->get('ecomm_forgot_password_path') ?: '/forgottenpassword',
      '#states' => [
        'visible' => [
          ':input[name="use_ecomm_pass_reset"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['external_registration_path'] = [
      '#type' => 'textfield',
      '#description' => $this->t('The path to use for commerce account registration.'),
      '#title' => $this->t('Commerce registration path'),
      '#default_value' => $config->get('external_registration_path') ?: '/register',
      '#states' => [
        'visible' => [
          ':input[name="use_ecomm_sessions"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['external_login_path'] = [
      '#type' => 'textfield',
      '#description' => $this->t('The path to use for commerce accounts to login.'),
      '#title' => $this->t('Commerce login path'),
      '#default_value' => $config->get('external_login_path') ?: '/login',
      '#states' => [
        'visible' => [
          ':input[name="use_ecomm_sessions"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['external_logout_path'] = [
      '#type' => 'textfield',
      '#description' => $this->t('The path to use for commerce accounts to logout.'),
      '#title' => $this->t('Commerce logout path'),
      '#default_value' => $config->get('external_logout_path') ?: '/logout',
      '#states' => [
        'visible' => [
          ':input[name="use_ecomm_sessions"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $options = [];
    foreach ($this->customerPagesManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = $plugin_definition['label'];
    }

    $form['customer_pages_plugin'] = [
      '#type' => 'select',
      '#title' => t('Customer Pages Plugin'),
      '#options' => $options,
      '#default_value' => $config->get('customer_pages_plugin'),
      '#empty_option' => $this->t('- None -'),
      '#description' => $this->t('The plugin to use for customer/account management pages.'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
