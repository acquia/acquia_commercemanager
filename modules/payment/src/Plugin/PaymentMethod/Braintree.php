<?php

namespace Drupal\acm_payment\Plugin\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Braintree payment method.
 *
 * @ACMPaymentMethod(
 *   id = "braintree",
 *   label = @Translation("Braintree Credit Cart"),
 * )
 */
class Braintree extends PaymentMethodBase implements PaymentMethodInterface {

  /**
   * {@inheritdoc}
   */
  public function buildPaymentSummary() {
    return 'Braintree details here.';
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $cart = $this->getCart();
    $payment_method = $cart->getPaymentMethod();
    $payment_method_name = $payment_method['method'];
    $payment_data = isset($payment_method['additional_data']) ? $payment_method['additional_data'] : [];
    $nonce = isset($payment_data['payment_method_nonce']) ? $payment_data['payment_method_nonce'] : NULL;

    // If payment details have already been filled out, don't show the form.
    if ($payment_method_name == $this->getId() && isset($nonce)) {
      $pane_form['payload_nonce'] = [
        '#type' => 'hidden',
        '#default_value' => $nonce,
      ];
      $pane_form['complete_message'] = [
        '#markup' => $this->t('Braintree information already entered.'),
      ];
      return $pane_form;
    }

    $pane_form['payload_nonce'] = [
      '#type' => 'hidden',
      '#default_value' => '',
    ];

    $pane_form['#theme'] = ['acm_payment__braintree'];

    $pane_form['#attached'] = [
      'library' => ['acm_payment/braintree'],
      'drupalSettings' => [
        'braintree' => [
          'authorizationToken' => $this->getToken(),
        ],
      ],
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaymentForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $nonce = $values['payment_details']['payload_nonce'];
    if (empty($nonce)) {
      $form_state->setError($pane_form, $this->t('There was an issue with the credit card details.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaymentForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $nonce = $values['payment_details']['payload_nonce'];
    $cart = $this->getCart();
    $cart->setPaymentMethodData([
      'payment_method_nonce' => $nonce,
      'cc_type' => '',
    ]);
  }

  /**
   * Get and cache the token used for a transaction.
   */
  public function getToken() {
    $cid = 'acm_payment:braintree_token';
    $token = NULL;

    if ($cache = \Drupal::cache()->get($cid)) {
      $token = $cache->data;
    }
    else {
      $token = \Drupal::service('acm.api')->getPaymentToken('braintree');
      \Drupal::cache()->set($cid, $token);
    }

    return $token;
  }

}
