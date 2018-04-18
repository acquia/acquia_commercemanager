<?php

namespace Drupal\acm_checkout\Plugin\CheckoutFlow;

/**
 * Provides the default multistep checkout flow.
 *
 * @ACMCheckoutFlow(
 *   id = "multistep_default",
 *   label = "Multistep - Default",
 * )
 */
class MultistepDefault extends CheckoutFlowWithPanesBase {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    return [
      'billing' => [
        'label' => $this->t('Billing'),
        'previous_label' => $this->t('Return to billing'),
      ],
      'shipping' => [
        'label' => $this->t('Shipping'),
        'next_label' => $this->t('Continue to shipping'),
        'previous_label' => $this->t('Return to shipping'),
      ],
      'payment' => [
        'label' => $this->t('Payment'),
        'next_label' => $this->t('Continue to payment'),
        'previous_label' => $this->t('Return to payment'),
      ],
      'review' => [
        'label' => $this->t('Review'),
        'next_label' => $this->t('Continue to review'),
      ],
      'complete' => [
        'label' => $this->t('Complete'),
        'next_label' => $this->t('Pay and complete purchase'),
        'hide_from_progress' => TRUE,
      ],
    ] + parent::getSteps();
  }

}
