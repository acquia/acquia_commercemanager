<?php

namespace Drupal\acm_payment\Plugin\PaymentMethod;

/**
 * Provides the Authorize.net payment method.
 *
 * @ACMPaymentMethod(
 *   id = "authorizenet_directpost",
 *   label = @Translation("Credit Card"),
 * )
 */
class AuthorizeNet extends PaymentMethodBase implements PaymentMethodInterface {

  /**
   * {@inheritdoc}
   */
  public function buildPaymentSummary() {
    return 'Auth.net details here.';
  }

}
