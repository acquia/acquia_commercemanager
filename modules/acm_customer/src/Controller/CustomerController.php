<?php

namespace Drupal\acm_customer\Controller;

use Drupal\acm_customer\Ajax\CustomerFormCommand;
use Drupal\acm_customer\Ajax\CustomerFormRedirectCommand;
use Drupal\acm_customer\Ajax\CustomerFormSavedCommand;
use Drupal\acm_customer\Ajax\CustomerFormValidationErrorsCommand;
use Drupal\acm_customer\Ajax\CustomerFormValidationErrorsFieldsCommand;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class CustomerController.
 */
class CustomerController extends ControllerBase {

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
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The customer pages plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $customerPagesManager;

  /**
   * The customer pages plugin.
   *
   * @var string
   */
  private $customerPagesPlugin;

  /**
   * Constructs a new CustomerController.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $acm_customer_pages_manager
   *   The customer pages plugin manager.
   */
  public function __construct(FormBuilderInterface $form_builder, RendererInterface $renderer, ConfigFactoryInterface $config_factory, PluginManagerInterface $acm_customer_pages_manager) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    $this->configFactory = $config_factory;
    $this->customerPagesManager = $acm_customer_pages_manager;
    $this->customerPagesPlugin = $config_factory
      ->get('acm.commerce_users')
      ->get('customer_pages_plugin');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('plugin.manager.acm_customer_pages')
    );
  }

  /**
   * Gets the page title.
   *
   * @param string $page
   *   The page route parameter.
   * @param string $action
   *   The id action parameter.
   *
   * @return string
   *   The page title.
   */
  public function getTitle($page = NULL, $action = NULL) {
    $plugin = $this->customerPagesManager->createInstance($this->customerPagesPlugin);
    $page_id = $plugin->getPageId();
    $pages = $plugin->getPages();
    $page_config = $pages[$page_id];
    $title = isset($page_config['title']) ? $page_config['title'] : $page_id;

    if ($action == 'edit' && isset($page_config['edit_title'])) {
      $title = $page_config['edit_title'];
    }

    if (!$page) {
      $title = $this->t('Account');
    }

    return $title;
  }

  /**
   * Renders a customer page.
   *
   * @param string $action
   *   The action route parameter.
   * @param string $id
   *   The id route parameter.
   */
  public function formPage($action = NULL, $id = NULL) {
    $plugin = $this->customerPagesManager->createInstance($this->customerPagesPlugin, ['action' => $action, 'id' => $id]);
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($plugin, $form_state);
    $form['#attached']['library'][] = 'core/jquery.form';
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'acm_customer/single_page_app';
    $form['#attached']['drupalSettings']['acm_customer'] = [
      'customerPagesPath' => Url::fromRoute('acm_customer.view_page')->toString(),
      'ajaxCustomerPagesPath' => Url::fromRoute('acm_customer.ajax_view_page')->toString(),
    ];
    return $form;
  }

  /**
   * Builds and processes the form provided by the order's checkout flow.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param string $action
   *   The action route parameter.
   * @param string $id
   *   The id route parameter.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   The form.
   */
  public function ajaxFormPage(Request $request, $action = NULL, $id = NULL) {
    $response = new AjaxResponse();

    try {
      $plugin = $this->customerPagesManager->createInstance($this->customerPagesPlugin, ['action' => $action, 'id' => $id]);
    }
    catch (NeedsRedirectException $e) {
      // Check if the page is redirecting somewhere.
      $response->addCommand(new CustomerFormRedirectCommand($e->getRedirectUrl()));
      return $response;
    }

    $form_state = (new FormState())
      ->disableRedirect();
    $form = $this->formBuilder->buildForm($plugin, $form_state);

    if ($form_state->isExecuted()) {
      $next_page = Url::fromRoute('acm_customer.view_page', [
        'page' => $plugin->getPageId(),
      ])->toString();
      $response->addCommand(new CustomerFormSavedCommand($next_page));
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

      $response->addCommand(new CustomerFormCommand($output));

      $errors = $form_state->getErrors();
      if (count($errors)) {
        $status_messages = [
          '#type' => 'status_messages',
        ];
        $response->addCommand(new CustomerFormValidationErrorsCommand($this->renderer->renderRoot($status_messages)));
        $response->addCommand(new CustomerFormValidationErrorsFieldsCommand(array_keys($errors)));
      }
    }

    // Render any uses of `drupal_set_message` that have are displaying status
    // messages. Error statuses should be caught with the form errors above.
    $messages = ['#type' => 'status_messages', '#display' => 'status'];
    $messages = $this->renderer->renderRoot($messages);
    if ($messages) {
      $response->addCommand(new CustomerFormMessageCommand($messages));
    }

    return $response;
  }

  /**
   * Checks access for the form page.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess() {
    return AccessResult::allowedIf($this->customerPagesPlugin);
  }

}
