<?php

namespace Drupal\acm_payment\Plugin\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Purchaseorder payment method.
 *
 * @ACMPaymentMethod(
 *   id = "purchaseorder",
 *   label = @Translation("Purchaseorder"),
 * )
 */
class Purchaseorder extends PaymentMethodBase implements PaymentMethodInterface {

  /**
   * {@inheritdoc}
   */
  public function buildPaymentSummary() {
    return 'Purchase order.';
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Purchaseorder doesn't need any payment details.
    // So we simply inform the user (via acm-payment--purchaseorder.html.twig)
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
