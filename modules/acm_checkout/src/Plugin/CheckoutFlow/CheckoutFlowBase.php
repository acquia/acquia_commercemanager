<?php

namespace Drupal\acm_checkout\Plugin\CheckoutFlow;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\Response\NeedsRedirectException;
use Drupal\acm\User\AccountProxyInterface;
use Drupal\acm_cart\CartStorageInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the base checkout flow class.
 *
 * Checkout flows should extend this class only if they don't want to use
 * checkout panes. Otherwise they should extend CheckoutFlowWithPanesBase.
 */
abstract class CheckoutFlowBase extends PluginBase implements CheckoutFlowInterface, ContainerFactoryPluginInterface {

  /**
   * The shopping cart.
   *
   * @var \Drupal\acm_cart\CartStorageInterface
   */
  protected $cartStorage;

  /**
   * The api wrapper.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  protected $apiWrapper;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The commerce user manager.
   *
   * @var \Drupal\acm\User\AccountProxyInterface
   */
  private $commerceUserManager;

  /**
   * The current step ID.
   *
   * @var string
   */
  protected $stepId;

  /**
   * Constructs a new CheckoutFlowBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\acm_cart\CartStorageInterface $cart_storage
   *   The cart storage.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   The api wrapper.
   * @param \Drupal\acm\User\AccountProxyInterface $commerce_user_manager
   *   The commerce user manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $event_dispatcher, RouteMatchInterface $route_match, CartStorageInterface $cart_storage, APIWrapperInterface $api_wrapper, AccountProxyInterface $commerce_user_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
    $this->eventDispatcher = $event_dispatcher;
    $this->cartStorage = $cart_storage;
    $this->apiWrapper = $api_wrapper;
    $this->commerceUserManager = $commerce_user_manager;
    $this->stepId = $this->processStepId($route_match->getParameter('step'));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('current_route_match'),
      $container->get('acm_cart.cart_storage'),
      $container->get('acm.api'),
      $container->get('acm.commerce_user_manager')
    );
  }

  /**
   * Processes the requested step ID.
   *
   * @param string $requested_step_id
   *   The step ID.
   *
   * @return string
   *   The processed step ID.
   *
   * @throws NeedsRedirectException
   *   Throws exception when cart is empty.
   */
  protected function processStepId($requested_step_id) {
    $cart = $this->cartStorage;

    // Redirect back to cart page if cart is empty.
    if ($cart->isEmpty()) {
      throw new NeedsRedirectException(Url::fromRoute('acm_cart.cart')->toString());
    }

    $cart_step_id = $cart->getCheckoutStep();
    $step_ids = array_keys($this->getVisibleSteps());
    $step_id = $requested_step_id;
    if (empty($step_id) || !in_array($step_id, $step_ids)) {
      // Take the step ID from the cart, or default to the first one.
      $step_id = $cart_step_id;
      if (empty($step_id)) {
        $step_id = reset($step_ids);
      }
    }

    $config = $this->getConfiguration();
    $validate_current_step = $config['validate_current_step'];
    if (empty($validate_current_step)) {
      return $step_id;
    }

    // If user is on a certain step in their cart, check that the step being
    // processed is not further along in the checkout process then their last
    // completed step. If they haven't started the checkout yet, make sure they
    // can't get past the first step.
    $step_index = array_search($step_id, $step_ids);
    if (empty($cart_step_id)) {
      $first_step = reset($step_ids);
      if ($step_index > $first_step) {
        return $this->redirectToStep($first_step);
      }
    }
    else {
      $cart_step_index = array_search($cart_step_id, $step_ids);
      // Step being processed is further along than they should be, redirect
      // back to step they still need to complete.
      if ($step_index > $cart_step_index) {
        return $this->redirectToStep($cart_step_id);
      }
    }

    return $step_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentUser() {
    $use_ecomm_sessions = \Drupal::config('acm.commerce_users')
      ->get('use_ecomm_sessions');

    if ($use_ecomm_sessions) {
      $current_user = \Drupal::service('acm.commerce_user_manager');
      return $current_user->getAccount();
    }
    else {
      return \Drupal::currentUser();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentCommerceUser() {
    $current_user = \Drupal::service('acm.commerce_user_manager');
    return $current_user->getAccount();
  }

  /**
   * {@inheritdoc}
   */
  public function getCart() {
    return $this->cartStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiWrapper() {
    return $this->apiWrapper;
  }

  /**
   * {@inheritdoc}
   */
  public function getStepId() {
    return $this->stepId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousStepId() {
    $step_ids = array_keys($this->getVisibleSteps());
    $current_index = array_search($this->stepId, $step_ids);
    return isset($step_ids[$current_index - 1]) ? $step_ids[$current_index - 1] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextStepId() {
    $step_ids = array_keys($this->getVisibleSteps());
    $current_index = array_search($this->stepId, $step_ids);
    return isset($step_ids[$current_index + 1]) ? $step_ids[$current_index + 1] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function redirectToStep($step_id) {
    $cart = $this->cartStorage;
    $cart->setCheckoutStep($step_id);
    throw new NeedsRedirectException(Url::fromRoute('acm_checkout.form', [
      'step' => $step_id,
    ])->toString());
  }

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    // Each checkout flow plugin defines its own steps.
    // These two steps are always expected to be present.
    return [
      'payment' => [
        'label' => $this->t('Payment'),
        'next_label' => $this->t('Pay and complete purchase'),
      ],
      'complete' => [
        'label' => $this->t('Complete'),
        'next_label' => $this->t('Pay and complete purchase'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibleSteps() {
    // All steps are visible by default.
    return $this->getSteps();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
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
      'validate_current_step' => FALSE,
      'display_checkout_progress' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $steps = $this->getVisibleSteps();

    $form['#tree'] = TRUE;
    $form['#theme'] = ['acm_checkout_form'];
    $form['#attached']['library'][] = 'acm_checkout/form';
    $form['#title'] = $steps[$this->stepId]['label'];
    $form['actions'] = $this->actions($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($next_step_id = $this->getNextStepId()) {
      /** @var \Drupal\acm_cart\CartStorage $cart */
      $cart = $this->cartStorage;
      $cart->setCheckoutStep($next_step_id);
      $form_state->setRedirect('acm_checkout.form', [
        'step' => $next_step_id,
      ]);

      if ($next_step_id == 'complete') {
        $cart->updateCart();
        $cart_id = $cart->getCartId();
        // Place an order.
        $response = $this->apiWrapper->placeOrder($cart_id, $this->getCurrentUser()->id());

        $order_id = NULL;
        // V1 Connector returns ['order']['id'] V2 returns ['order_id'].
        if (array_key_exists('order_id', $response)) {
          $order_id = $response['order_id'];
        }
        elseif (array_key_exists('order', $response)) {
          $order_id = $response['order']['id'];
        }

        if (!$order_id) {
          // @TODO: Do we need to throw any exception?
          return;
        }

        $timestamp = \Drupal::time()->getRequestTime();
        $request = \Drupal::request();

        // Store the order time and order id to a cookie so that any checkout
        // panes after this have access to it. This would be useful in the case
        // of a "Completion" pane wanting to show order details.
        if (isset($request->cookies)) {
          $request->cookies->set('Drupal_visitor_acm_order_id', $order_id);
          $request->cookies->set('Drupal_visitor_acm_order_timestamp', $timestamp);
        }

        user_cookie_save([
          'acm_order_id' => $order_id,
          'acm_order_timestamp' => $timestamp,
        ]);
      }
    }
  }

  /**
   * Builds the actions element for the current form.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The actions element.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $steps = $this->getVisibleSteps();
    $next_step_id = $this->getNextStepId();
    $previous_step_id = $this->getPreviousStepId();
    $has_next_step = $next_step_id && isset($steps[$next_step_id]['next_label']);
    $has_previous_step = $previous_step_id && isset($steps[$previous_step_id]['previous_label']);

    $actions = [
      '#type' => 'actions',
      '#access' => $has_next_step,
    ];

    if ($has_next_step) {
      $actions['next'] = [
        '#type' => 'submit',
        '#value' => $steps[$next_step_id]['next_label'],
        '#button_type' => 'primary',
        '#submit' => ['::submitForm'],
      ];

      if ($has_previous_step) {
        $label = $steps[$previous_step_id]['previous_label'];
        $actions['next']['#suffix'] = Link::createFromRoute($label, 'acm_checkout.form', [
          'step' => $previous_step_id,
        ])->toString();
      }
    }

    return $actions;
  }

}
