<?php

namespace Drupal\acm_cart;

/**
 * Defines the interface for storing carts.
 *
 * @package Drupal\acm_cart
 */
interface CartStorageInterface {

  // The cart storage key.
  const STORAGE_KEY = 'acm_cart';

  /**
   * Restores the cart to what is available in the ecomm backend.
   *
   * @param int $cart_id
   *   Cart Id to restore. We don't rely on other functions as cart is already
   *   corrupt when we call this function.
   */
  public function restoreCart($cart_id);

  /**
   * Converts a cart to either a restored "current" cart or an associated cart.
   *
   * @param int|string $customer_id
   *   The customer we're trying to restore a cart for.
   */
  public function convertGuestCart($customer_id);

  /**
   * Clears the cart details in session and cookies.
   */
  public function clearCart();

  /**
   * Gets the current card ID.
   *
   * @param bool $create_new
   *   Create new cart if no cart exists for the current session.
   *
   * @return int
   *   Current Cart Id.
   */
  public function getCartId($create_new);

  /**
   * Saves the given cart to storage.
   *
   * @param \Drupal\acm_cart\CartInterface $cart
   *   The cart object.
   */
  public function storeCart(CartInterface $cart);

  /**
   * Loads the cart from storage.
   *
   * @param bool $create_new
   *   Create new cart if no cart exists for the current session.
   *
   * @return \Drupal\acm_cart\CartInterface
   *   The current cart.
   */
  public function loadCart($create_new);

  /**
   * Updates the current cart in storage.
   *
   * @param bool $create_new
   *   Create new cart if no cart exists for the current session.
   *
   * @return \Drupal\acm_cart\Cart
   *   Updated cart.
   */
  public function updateCart($create_new = TRUE);

  /**
   * Creates a cart for storage.
   */
  public function createCart();

  /**
   * Associate the current cart in storage with a given customer.
   *
   * @param string $customer_id
   *   The id of the customer to associate the cart to.
   * @param string $customer_email
   *   Optional customer email. Gets added to the cart.
   */
  public function associateCart($customer_id, $customer_email = "");

  /**
   * Checks if a cart exists.
   *
   * @return bool
   *   TRUE for yes, FALSE for no.
   */
  public function cartExists();

  /**
   * Checks if the current cart is empty.
   *
   * @return bool
   *   TRUE for yes, FALSE for no.
   */
  public function isEmpty();

  /**
   * Gets the raw cart object.
   *
   * @return object
   *   The raw cart object.
   */
  public function getCartContents();

}
