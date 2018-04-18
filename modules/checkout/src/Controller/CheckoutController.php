<?php

namespace Drupal\acm_checkout\Controller;

use Drupal\acm\Response\NeedsRedirectException;
use Drupal\acm_cart\CartStorageInterface;
use Drupal\acm_checkout\Ajax\PaneFormCommand;
use Drupal\acm_checkout\Ajax\PaneFormRedirectCommand;
use Drupal\acm_checkout\Ajax\PaneFormSavedCommand;
use Drupal\acm_checkout\Ajax\PaneFormValidationErrorsCommand;
use Drupal\acm_checkout\Ajax\PaneFormValidationErrorsFieldsCommand;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the checkout form page.
 *
 * @todo: Redirect to cart if cart is empty.
 */
class CheckoutController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The checkout flow plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $acmCheckoutFlowManager;

  /**
   * The cart session.
   *
   * @var \Drupal\acm_cart\CartStorageInterface
   */
  protected $cartStorage;

  /**
   * Constructs a new CheckoutController object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $acm_checkout_flow_manager
   *   The checkout flow manager.
   * @param \Drupal\acm_cart\CartStorageInterface $cart_storage
   *   The cart session.
   */
  public function __construct(FormBuilderInterface $form_builder, RendererInterface $renderer, ConfigFactoryInterface $config_factory, PluginManagerInterface $acm_checkout_flow_manager, CartStorageInterface $cart_storage) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    $this->config = $config_factory->get('acm_checkout.settings');
    $this->acmCheckoutFlowManager = $acm_checkout_flow_manager;
    $this->cartStorage = $cart_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('plugin.manager.acm_checkout_flow'),
      $container->get('acm_cart.cart_storage')
    );
  }

  /**
   * Builds and processes the form provided by the order's checkout flow.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return array
   *   The render form.
   */
  public function formPage(Request $request) {
    if (!$this->config->get('use_ajax')) {
      $form_state = new FormState();
      $plugin = $this->loadCheckoutFlowPlugin();
      $form = $this->formBuilder->buildForm($plugin, $form_state);
      return $form;
    }

    return [
      '#attributes' => ['id' => 'acm_checkout_wrapper'],
      '#attached' => [
        'library' => ['acm_checkout/single_page_checkout'],
        'drupalSettings' => [
          'acm_checkout' => [
            'cartPath' => Url::fromRoute('acm_checkout.form')->toString(),
            'ajaxCartPath' => Url::fromRoute('acm_checkout.ajax_form')->toString(),
          ],
        ],
      ],
    ];
  }

  /**
   * Builds and processes the form provided by the order's checkout flow.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   The form.
   */
  public function ajaxFormPage(Request $request) {
    $response = new AjaxResponse();

    try {
      $plugin = $this->loadCheckoutFlowPlugin();
    }
    catch (NeedsRedirectException $e) {
      // If someone tries access a step that isn't available yet, they get
      // redirected to the last available step. So catch the exception and tell
      // the Javascript app where to go.
      $response->addCommand(new PaneFormRedirectCommand($e->getRedirectUrl()));
      return $response;
    }

    $form_state = (new FormState())
      ->disableRedirect();
    $form = $this->formBuilder->buildForm($plugin, $form_state);

    if ($form_state->isExecuted()) {
      // Form is saved without errors, so return the url of the next checkout
      // step.
      $next_page = Url::fromRoute('acm_checkout.form', [
        'step' => $plugin->getNextStepId(),
      ])->toString();
      $response->addCommand(new PaneFormSavedCommand($next_page));
    }
    else {
      $output = $this->renderer->renderRoot($form);

      if ($request->request->get('nocssjs') !== 'true') {
        $response->setAttachments($form['#attached']);
      }
      else {
        // Libraries we always want attached.
        $attached = [];
        $attached['library'] = [
          'core/drupal.states',
          'core/drupal.ajax',
        ];
        $attached['drupalSettings'] = $form['#attached']['drupalSettings'];
        $response->setAttachments($attached);
      }

      $response->addCommand(new PaneFormCommand($output));

      $errors = $form_state->getErrors();
      if (count($errors)) {
        $status_messages = [
          '#type' => 'status_messages',
        ];
        $response->addCommand(new PaneFormValidationErrorsCommand($this->renderer->renderRoot($status_messages)));
        $response->addCommand(new PaneFormValidationErrorsFieldsCommand(array_keys($errors)));
      }
    }

    return $response;
  }

  /**
   * Checks access for the form page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(AccountInterface $account) {
    $cart = $this->cartStorage;

    if (empty($cart) || $cart->isEmpty()) {
      return AccessResult::forbidden('Invalid cart');
    }

    return AccessResult::allowedIfHasPermission($account, 'access checkout');
  }

  /**
   * Loads the configured CheckoutFlow plugin.
   *
   * @return object
   *   An instance of the CheckoutFlow plugin.
   */
  protected function loadCheckoutFlowPlugin() {
    $checkoutFlowPlugin = $this->config->get('checkout_flow_plugin') ?: 'multistep_default';
    return $this->acmCheckoutFlowManager->createInstance($checkoutFlowPlugin, ['validate_current_step' => TRUE]);
  }

}
