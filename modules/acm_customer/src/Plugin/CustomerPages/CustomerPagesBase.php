<?php

namespace Drupal\acm_customer\Plugin\CustomerPages;

use Drupal\acm_cart\CartStorageInterface;
use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\Response\NeedsRedirectException;
use Drupal\acm_customer\CustomerFormManager;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\acm\User\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base CustomerPages class.
 */
abstract class CustomerPagesBase extends PluginBase implements CustomerPagesInterface, ContainerFactoryPluginInterface {

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * The commerce user manager.
   *
   * @var \Drupal\acm\User\AccountProxyInterface
   */
  protected $commerceUserManager;

  /**
   * The customer form manager.
   *
   * @var \Drupal\acm_customer\CustomerFormManager
   */
  protected $formManager;

  /**
   * The current page ID.
   *
   * @var string
   */
  protected $pageId;

  /**
   * Static cache of visible pages.
   *
   * @var array
   */
  protected $visiblePages = [];

  /**
   * The initialized child form plugins.
   *
   * @var \Drupal\acm_customer\Plugin\CustomerForm\CustomerFormInterface[]
   */
  protected $childForms = [];

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\acm_cart\CartStorageInterface $cart_storage
   *   The cart storage.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   The api wrapper.
   * @param \Drupal\acm\User\AccountProxyInterface $commerce_user_manager
   *   The commerce user manager.
   * @param \Drupal\acm_customer\CustomerFormManager $form_manager
   *   The customer form manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, ConfigFactoryInterface $config_factory, CartStorageInterface $cart_storage, APIWrapperInterface $api_wrapper, AccountProxyInterface $commerce_user_manager, CustomerFormManager $form_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
    $this->configFactory = $config_factory;
    $this->cartStorage = $cart_storage;
    $this->apiWrapper = $api_wrapper;
    $this->commerceUserManager = $commerce_user_manager;
    $this->formManager = $form_manager;
    $this->pageId = $this->processPageId($route_match->getParameter('page'));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('config.factory'),
      $container->get('acm_cart.cart_storage'),
      $container->get('acm.api'),
      $container->get('acm.commerce_user_manager'),
      $container->get('plugin.manager.acm_customer_form')
    );
  }

  /**
   * Processes the requested page ID.
   *
   * @param string $page_id
   *   The page ID.
   *
   * @return string
   *   The processed page ID.
   */
  protected function processPageId($page_id) {
    $page_ids = array_keys($this->getVisiblePages());

    // Redirect to default page (first page) if no page or invalid page is
    // requested.
    if (empty($page_id) || !in_array($page_id, $page_ids)) {
      $page_id = reset($page_ids);
    }

    return $page_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigFactory() {
    return $this->configFactory;
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
  public function getCommerceUserManager() {
    return $this->commerceUserManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageId() {
    return $this->pageId;
  }

  /**
   * {@inheritdoc}
   */
  public function redirectToPage($page_id) {
    throw new NeedsRedirectException(Url::fromRoute('acm_customer.view_page', [
      'page' => $page_id,
    ])->toString());
  }

  /**
   * {@inheritdoc}
   */
  public function getVisiblePages() {
    if (!empty($this->visiblePages)) {
      return $this->visiblePages;
    }

    $pages = $this->getPages();

    foreach ($pages as $page_id => $page) {
      // A page is visible if it has at least one visible form.
      $is_visible = FALSE;
      foreach ($this->getChildForms($page_id) as $form) {
        if ($form->isVisible()) {
          $is_visible = TRUE;
          break;
        }
      }

      // If page is not visible, remove it from the static cache.
      if (!$is_visible) {
        unset($pages[$page_id]);
      }
    }

    $this->visiblePages = $pages;

    return $this->visiblePages;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildForms($page_id = NULL) {
    if (empty($this->childForms)) {
      foreach ($this->formManager->getDefinitions() as $form_id => $form_definition) {
        $form_configuration = $this->getFormConfiguration($form_id);
        $form = $this->formManager->createInstance($form_id, $form_configuration, $this);
        $this->childForms[$form_id] = [
          'form' => $form,
          'weight' => $form->getWeight(),
        ];
      }

      // Sort the forms and flatten the array.
      uasort($this->childForms, ['\Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
      $this->childForms = array_map(function ($form_data) {
        return $form_data['form'];
      }, $this->childForms);
    }

    $child_forms = $this->childForms;
    if ($page_id) {
      $child_forms = array_filter($child_forms, function ($child_form) use ($page_id) {
        /** @var \Drupal\acm_customer\Plugin\CustomerForm\CustomerFormInterface $child_form */
        return $child_form->getPageId() == $page_id;
      });
    }

    return $child_forms;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    // Merge-in the form dependencies.
    foreach ($this->getChildForms() as $child_form) {
      foreach ($child_form->calculateDependencies() as $dependency_type => $list) {
        foreach ($list as $name) {
          $dependencies[$dependency_type][] = $name;
        }
      }
    }

    return $dependencies;
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
      'action' => 'view',
      'id' => NULL,
      'child_forms' => [],
    ];
  }

  /**
   * Gets the configuration for the given form.
   *
   * @param string $form_id
   *   The form ID.
   *
   * @return array
   *   The form configuration.
   */
  protected function getFormConfiguration($form_id) {
    $form_configuration = [];

    if (isset($this->configuration['child_forms'][$form_id])) {
      $form_configuration = $this->configuration['child_forms'][$form_id];
    }

    return $form_configuration;
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
    $action = $this->configuration['action'];
    $id = $this->configuration['id'];
    $pages = $this->getVisiblePages();
    $page_config = $pages[$this->pageId];
    $child_forms = $this->getChildForms($this->pageId);

    $form['#tree'] = TRUE;

    $form['#attached'] = [
      'library' => [
        'acm_customer/base',
      ],
    ];

    foreach ($child_forms as $child_form_id => $child_form) {
      $form[$child_form_id] = [
        '#parents' => [$child_form_id],
        '#type' => $child_form->getWrapperElement(),
        '#title' => $child_form->getLabel(),
        '#access' => $child_form->isVisible(),
      ];
      $form[$child_form_id] = $child_form->buildForm($form[$child_form_id], $form_state, $form, $action, $id);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $child_forms = $this->getChildForms($this->pageId);
    foreach ($child_forms as $child_form_id => $child_form) {
      $child_form->validateForm($form[$child_form_id], $form_state, $form);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $child_forms = $this->getChildForms($this->pageId);
    foreach ($child_forms as $child_form_id => $child_form) {
      $child_form->submitForm($form[$child_form_id], $form_state, $form);
    }

    // If child form hasn't redirected anywhere then redirect back to the view
    // page.
    $redirect = $form_state->getRedirect();
    if (!$redirect) {
      $form_state->setRedirect('acm_customer.view_page', [
        'page' => $this->pageId,
      ]);
    }
  }

}
