<?php

namespace Drupal\acm_cart;

use Drupal\acm\I18nHelper;

/**
 * Class MiniCartManager.
 *
 * @package Drupal\acm_cart
 */
class MiniCartManager {

  /**
   * Drupal\acm_cart\CartStorageInterface definition.
   *
   * @var \Drupal\acm_cart\CartStorageInterface
   */
  protected $cartStorage;

  /**
   * Internationalization helper object.
   *
   * @var \Drupal\acm\I18nHelper
   */
  protected $i18nHelper;

  /**
   * MiniCartManager constructor.
   *
   * @param \Drupal\acm_cart\CartStorageInterface $cartStorage
   *   Cart storage service.
   * @param \Drupal\acm\I18nHelper $i18n_helper
   *   Internationalization helper object.
   */
  public function __construct(CartStorageInterface $cartStorage, I18nHelper $i18n_helper) {
    $this->cartStorage = $cartStorage;
    $this->i18nHelper = $i18n_helper;
  }

  /**
   * Helper function to get Mini cart.
   */
  public function getMiniCart() {
    $cart = $this->cartStorage->getCart();

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
        '#markup' => $this->i18nHelper->formatPrice($grand_total),
      ];

      // Use the template to render the HTML.
      $output['#quantity'] = $cart->getCartItemsCount();
      $output['#total'] = $total;
    }

    return $output;
  }

}
