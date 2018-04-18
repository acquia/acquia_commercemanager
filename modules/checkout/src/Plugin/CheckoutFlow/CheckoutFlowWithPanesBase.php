<?php

namespace Drupal\acm_checkout\Plugin\CheckoutFlow;

use Drupal\acm_cart\CartStorageInterface;
use Drupal\acm_checkout\CheckoutPaneManager;
use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\User\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a base checkout flow that uses checkout panes.
 */
abstract class CheckoutFlowWithPanesBase extends CheckoutFlowBase implements CheckoutFlowWithPanesInterface {

  /**
   * The checkout pane manager.
   *
   * @var \Drupal\acm_checkout\CheckoutPaneManager
   */
  protected $paneManager;

  /**
   * The initialized pane plugins.
   *
   * @var \Drupal\acm_checkout\Plugin\CheckoutPane\CheckoutPaneInterface[]
   */
  protected $panes = [];

  /**
   * Static cache of visible steps.
   *
   * @var array
   */
  protected $visibleSteps = [];

  /**
   * Constructs a new CheckoutFlowWithPanesBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pane_id
   *   The plugin_id for the plugin instance.
   * @param mixed $pane_definition
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
   * @param \Drupal\acm_checkout\CheckoutPaneManager $pane_manager
   *   The checkout pane manager.
   */
  public function __construct(array $configuration, $pane_id, $pane_definition, EventDispatcherInterface $event_dispatcher, RouteMatchInterface $route_match, CartStorageInterface $cart_storage, APIWrapperInterface $api_wrapper, AccountProxyInterface $commerce_user_manager, CheckoutPaneManager $pane_manager) {
    $this->paneManager = $pane_manager;

    parent::__construct($configuration, $pane_id, $pane_definition, $event_dispatcher, $route_match, $cart_storage, $api_wrapper, $commerce_user_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pane_id, $pane_definition) {
    return new static(
      $configuration,
      $pane_id,
      $pane_definition,
      $container->get('event_dispatcher'),
      $container->get('current_route_match'),
      $container->get('acm_cart.cart_storage'),
      $container->get('acm.api'),
      $container->get('acm.commerce_user_manager'),
      $container->get('plugin.manager.acm_checkout_pane')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPanes($step_id = NULL) {
    if (empty($this->panes)) {
      foreach ($this->paneManager->getDefinitions() as $pane_id => $pane_definition) {
        $pane_configuration = $this->getPaneConfiguration($pane_id);
        $pane = $this->paneManager->createInstance($pane_id, $pane_configuration, $this);
        $this->panes[$pane_id] = [
          'pane' => $pane,
          'weight' => $pane->getWeight(),
        ];
      }
      // Sort the panes and flatten the array.
      uasort($this->panes, ['\Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
      $this->panes = array_map(function ($pane_data) {
        return $pane_data['pane'];
      }, $this->panes);
    }

    $panes = $this->panes;
    if ($step_id) {
      $panes = array_filter($panes, function ($pane) use ($step_id) {
        /** @var \Drupal\acm_checkout\Plugin\CheckoutPane\CheckoutPaneInterface $pane */
        return $pane->getStepId() == $step_id;
      });
    }

    return $panes;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibleSteps() {
    if (empty($this->visibleSteps)) {
      $steps = $this->getSteps();
      foreach ($steps as $step_id => $step) {
        // A step is visible if it has at least one visible pane.
        $is_visible = FALSE;
        foreach ($this->getPanes($step_id) as $pane) {
          if ($pane->isVisible()) {
            $is_visible = TRUE;
            break;
          }
        }

        if (!$is_visible) {
          unset($steps[$step_id]);
        }
      }
      $this->visibleSteps = $steps;
    }

    return $this->visibleSteps;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    // Merge-in the pane dependencies.
    foreach ($this->getPanes() as $pane) {
      foreach ($pane->calculateDependencies() as $dependency_type => $list) {
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
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'panes' => [],
    ];
  }

  /**
   * Gets the configuration for the given pane.
   *
   * @param string $pane_id
   *   The pane ID.
   *
   * @return array
   *   The pane configuration.
   */
  protected function getPaneConfiguration($pane_id) {
    $pane_configuration = [];
    if (isset($this->configuration['panes'][$pane_id])) {
      $pane_configuration = $this->configuration['panes'][$pane_id];
    }

    return $pane_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $panes = $this->getPanes($this->stepId);
    foreach ($panes as $pane_id => $pane) {
      $form[$pane_id] = [
        '#parents' => [$pane_id],
        '#type' => $pane->getWrapperElement(),
        '#title' => $pane->getLabel(),
        '#access' => $pane->isVisible(),
      ];
      $form[$pane_id] = $pane->buildPaneForm($form[$pane_id], $form_state, $form);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $panes = $this->getPanes($this->stepId);
    foreach ($panes as $pane_id => $pane) {
      $pane->validatePaneForm($form[$pane_id], $form_state, $form);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $panes = $this->getPanes($this->stepId);
      foreach ($panes as $pane_id => $pane) {
        $pane->submitPaneForm($form[$pane_id], $form_state, $form);
      }

      parent::submitForm($form, $form_state);
    }
    catch (\Exception $e) {
    }

  }

}
