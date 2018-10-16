<?php

namespace Drupal\acm_payment\Plugin\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Checkmo payment method.
 *
 * @ACMPaymentMethod(
 *   id = "checkmo",
 *   label = @Translation("Check/Money Order"),
 * )
 */
class Checkmo extends PaymentMethodBase implements PaymentMethodInterface {

  /**
   * {@inheritdoc}
   */
  public function buildPaymentSummary() {
    return 'Check or money order.';
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Checkmo doesn't need any payment details.
    // So we simply inform the user (via acm-payment--checkmo.html.twig)
    $pane_form['#theme'] = 'acm_payment';
    $pane_form['#payment_method'] = $this->getId();
    $pane_form['#payment_method_name'] = $this->getLabel();
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaymentForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $cart = $this->getCart();
    $cart->setPaymentMethodData([
      'cc_type' => '',
    ]);
  }

}
