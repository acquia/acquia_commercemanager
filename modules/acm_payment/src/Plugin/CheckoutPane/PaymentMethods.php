<?php

namespace Drupal\acm_payment\Plugin\CheckoutPane;

use Drupal\acm_checkout\Plugin\CheckoutPane\CheckoutPaneBase;
use Drupal\acm_checkout\Plugin\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the contact information pane.
 *
 * @ACMCheckoutPane(
 *   id = "payment_methods",
 *   label = @Translation("Payment Methods"),
 *   defaultStep = "payment",
 *   wrapperElement = "fieldset",
 * )
 */
class PaymentMethods extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * Gets all of the payment method plugins available.
   */
  public function getPlugins() {
    $paymentMethodManager = \Drupal::service('plugin.manager.acm_payment_method');
    return $paymentMethodManager->getDefinitions();
  }

  /**
   * Gets a specific payment method plugin.
   *
   * @param string $plugin_id
   *   The plugin id.
   */
  public function getPlugin($plugin_id) {
    $cart = $this->getCart();
    $paymentMethodManager = \Drupal::service('plugin.manager.acm_payment_method');
    return $paymentMethodManager->createInstance($plugin_id, [], $cart);
  }

  /**
   * Gets the customer selected plugin.
   */
  public function getSelectedPlugin() {
    $cart = $this->getCart();
    $plugin_id = $cart->getPaymentMethod(FALSE);
    return $this->getPlugin($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'weight' => 2,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $plugin = $this->getSelectedPlugin();
    return $plugin->buildPaymentSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    // @TODO: After the payment details are entered, prevent this form from
    // showing again if a user navigates back to this step or present an option
    // for the user to cancel the last payment method and enter a new one.
    $cart = $this->getCart();
    $plugins = $this->getPlugins();

    // Get available payment methods and compare to enabled payment method
    // plugins.
    $apiWrapper = $this->getApiWrapper();
    $payment_methods = $apiWrapper->getPaymentMethods($cart->id());
    $payment_methods = array_intersect($payment_methods, array_keys($plugins));

    // Only one payment method available, load and return that methods plugin.
    if (count($payment_methods) === 1) {
      $plugin_id = reset($payment_methods);
      $cart->setPaymentMethod($plugin_id);
      $plugin = $this->getPlugin($plugin_id);
      $pane_form += $plugin->buildPaneForm($pane_form, $form_state, $complete_form);
      return $pane_form;
    }

    // More than one payment method available, so build a form to let the user
    // chose the option they want. Once they select an option, an ajax callback
    // will rebuild the payment details and show the selected payment method
    // plugin form instead.
    $payment_options = [];
    foreach ($payment_methods as $plugin_id) {
      if (!isset($plugins[$plugin_id])) {
        continue;
      }
      $payment_options[$plugin_id] = $plugins[$plugin_id]['label'];
    }

    $plugin_id = $cart->getPaymentMethod(FALSE);

    $pane_form['payment_options'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment Options'),
      '#options' => $payment_options,
      '#default_value' => $plugin_id,
      '#ajax' => [
        'wrapper' => 'payment_details',
        'callback' => [$this, 'rebuildPaymentDetails'],
      ],
    ];

    if ($plugin_id) {
      $cart->setPaymentMethod($plugin_id);
      $plugin = $this->getPlugin($plugin_id);
      $pane_form += $plugin->buildPaneForm($pane_form, $form_state, $complete_form);
    }
    else {
      $pane_form['payment_details'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => ['payment_details'],
        ],
      ];
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public static function rebuildPaymentDetails(array $pane_form, FormStateInterface $form_state) {
    return $pane_form['payment_methods']['payment_details'];
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $payment_method = isset($values['payment_options']) ? $values['payment_options'] : NULL;
    if ($payment_method) {
      // Setting the payment method in the ajax callback is too late, but
      // validation runs before the ajax method is called, so we can get the
      // value selected by the user and update the cart in here so that when
      // the form rebuilds it shows the correct payment plugin form.
      $cart = $this->getCart();
      $cart->setPaymentMethod($payment_method);
    }
    $plugin = $this->getSelectedPlugin();
    $plugin->validatePaymentForm($pane_form, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $plugin = $this->getSelectedPlugin();
    $plugin->submitPaymentForm($pane_form, $form_state, $complete_form);
  }

}
