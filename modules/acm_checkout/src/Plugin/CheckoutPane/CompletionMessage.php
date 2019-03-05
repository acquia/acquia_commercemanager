<?php

namespace Drupal\acm_checkout\Plugin\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the completion message pane.
 *
 * @ACMCheckoutPane(
 *   id = "completion_message",
 *   label = @Translation("Completion Message"),
 *   defaultStep = "complete",
 * )
 */
class CompletionMessage extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_message = 'Your order has been submitted';

    return [
      'message' => $default_message,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $message = $this->configuration['message'];

    $pane_form['message'] = [
      '#markup' => $message,
    ];

    // Create a new empty cart after the message has been viewed.
    \Drupal::service('acm_cart.cart_storage')->createCart();

    return $pane_form;
  }

}
