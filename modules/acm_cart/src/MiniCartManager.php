<?php

namespace Drupal\acm_cart;

/**
 * Class MiniCartManager.
 *
 * @package Drupal\acm_cart
 */
class MiniCartManager {

  /**
   * MiniCartManager constructor.
   *
   * @param \Drupal\acm_cart\CartStorageInterface $cartStorage
   *   Cart storage service.
   */
  public function __construct(CartStorageInterface $cartStorage) {
    $this->cartStorage = $cartStorage;
  }

  /**
   * Helper function to get Mini cart.
   */
  public function getMiniCart() {
    $cart = $this->cartStorage->getCart(FALSE);

    // Return empty cart in case no cart available in current session.
    $output = [
      '#theme' => 'acm_cart_mini_cart',
      '#attached' => [
        'library' => ['acm_cart/minicart'],
      ],
      '#prefix' => '<div id="mini-cart-wrapper">',
      '#suffix' => '</div><div id="cart_notification"></div>',
    ];

    if (!$this->cartStorage->isEmpty()) {
      $totals = $cart->totals();

      // The grand total including discounts and taxes.
      $grand_total = $totals['grand'] < 0 || $totals['grand'] == NULL ? 0 : $totals['grand'];

      // Deduct shipping.
      if (isset($totals['shipping']) && $grand_total) {
        $grand_total -= $totals['shipping'];
      }

      $total = [
        '#markup' => \Drupal::service('acm.i18n_helper')->formatPrice($grand_total),
      ];

      // Use the template to render the HTML.
      $output['#quantity'] = $cart->getCartItemsCount();
      $output['#total'] = $total;
    }

    return $output;
  }

}
