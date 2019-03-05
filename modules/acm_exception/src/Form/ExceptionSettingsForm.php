<?php

namespace Drupal\acm_exception\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ExceptionSettingsForm controller.
 */
class ExceptionSettingsForm extends ConfigFormBase {

  /**
   * The Connector API Wrapper.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $apiWrapper;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   The Connector API Wrapper.
   */
  public function __construct(ConfigFactoryInterface $config_factory, APIWrapperInterface $api_wrapper) {
    parent::__construct($config_factory);
    $this->apiWrapper = $api_wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('acm.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acm_exception_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('acm_exception.settings');
    $redirect_description = $this->t('A redirect to perform in cases where the error thrown is going to break the page. You can enter a path such as %node or %cart_error. Enter %front to link to the front page. If omitted the current page will refresh.',
      [
        '%front' => '<front>',
        '%node' => '/node/123',
        '%cart_error' => '/cart/error',
      ]);
    $form['default'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default catch-all behavior'),
    ];
    $form['default']['default_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#description' => $this->t('The error message to present to the user.'),
      '#default_value' => $config->get('default.message'),
    ];
    $form['default']['default_redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect'),
      '#description' => $redirect_description,
      '#default_value' => $config->get('default.redirect'),
    ];
    $form['default']['default_log'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable logging'),
      '#description' => $this->t('Log all API exception responses.'),
      '#default_value' => $config->get('default.log'),
    ];
    foreach ($this->getExceptionScenarios() as $scenario) {
      $scenario_label = ucwords(preg_replace('/([^A-Z])([A-Z])/', "$1 $2", $scenario));
      $form[$scenario] = [
        '#type' => 'details',
        '#title' => $this->t('Errors during @scenario',
          [
            '@scenario' => $scenario_label,
          ]
        ),
      ];
      $form[$scenario]["{$scenario}_message"] = [
        '#type' => 'textarea',
        '#title' => $this->t('Message'),
        '#description' => $this->t('The error message to present to the user.'),
        '#default_value' => $config->get("routes.{$scenario}.message"),
      ];
      $form[$scenario]["{$scenario}_redirect"] = [
        '#type' => 'textfield',
        '#title' => $this->t('Redirect'),
        '#description' => $redirect_description,
        '#default_value' => $config->get("routes.{$scenario}.redirect"),
      ];
      $form[$scenario]["{$scenario}_log"] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable logging'),
        '#description' => $this->t('Log API exception responses for @scenario operations.', ['@scenario' => $scenario_label]),
        '#default_value' => $config->get("routes.{$scenario}.log"),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('acm_exception.settings');
    $config->set('default.message', $form_state->getValue('default_message'));
    $config->set('default.redirect', $form_state->getValue('default_redirect'));
    $config->set('default.log', $form_state->getValue('default_log'));
    foreach ($this->getExceptionScenarios() as $scenario) {
      $config->set("routes.{$scenario}.message", $form_state->getValue("{$scenario}_message"));
      $config->set("routes.{$scenario}.redirect", $form_state->getValue("{$scenario}_redirect"));
      $config->set("routes.{$scenario}.log", $form_state->getValue("{$scenario}_log"));
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'acm_exception.settings',
    ];
  }

  /**
   * Fetches exception scenarios.
   *
   * Fetches exception scenarios by grabbing the methods required in
   * Drupal\acm\Connector\APIWrapperInterface.
   *
   * @return array
   *   Array of scenarios.
   */
  private function getExceptionScenarios() {
    $scenarios = [];
    $api_wrapper_interface = class_implements($this->apiWrapper);
    if ($api_wrapper_interface) {
      $scenarios = get_class_methods(array_shift($api_wrapper_interface));
    }
    return $scenarios;
  }

}
