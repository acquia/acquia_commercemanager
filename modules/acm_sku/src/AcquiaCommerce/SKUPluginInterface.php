<?php

namespace Drupal\acm_sku\AcquiaCommerce;

use Drupal\acm_sku\Entity\SKU;
use Drupal\acm_sku\Entity\SKUInterface;
use Drupal\acm_sku\Entity\SKUTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the required interface to create a SKU Type plugin.
 */
interface SKUPluginInterface {

  /**
   * Decorates the bundle settings form with additional fields.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\acm_sku\Entity\SKUTypeInterface $sku_type
   *   The SKU Type entity.
   *
   * @return array
   *   The decorated form.
   */
  public function decorateSettingsForm(array $form, FormStateInterface $form_state, SKUTypeInterface $sku_type);

  /**
   * Saves callback when the bundle settings form is submitted.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\acm_sku\Entity\SKUTypeInterface $sku_type
   *   The SKU Type entity.
   */
  public function saveSettingsForm(array $form, FormStateInterface $form_state, SKUTypeInterface $sku_type);

  /**
   * Builds and returns the renderable array for this SKU Type plugin.
   *
   * @param array $build
   *   Drupal's initial render array for this array.
   *
   * @return array
   *   A renderable array representing the content of the SKU.
   */
  public function build(array $build);

  /**
   * Returns the form elements for adding this SKU Type to the cart.
   *
   * @param array $form
   *   The form definition array for the add to cart form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   The object of product we want to add to cart.
   *
   * @return array
   *   The renderable form array representing the entire add to cart form.
   */
  public function addToCartForm(array $form, FormStateInterface $form_state, SKU $sku = NULL);

  /**
   * Adds validation for the add to cart form.
   *
   * @param array $form
   *   The form definition array for the full add to cart form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\acm_sku\AcquiaCommerce\SKUPluginInterface::addToCartForm()
   * @see \Drupal\acm_sku\AcquiaCommerce\SKUPluginInterface::addToCartSubmit()
   */
  public function addToCartValidate(array &$form, FormStateInterface $form_state);

  /**
   * Adds submission handling for the add to cart form.
   *
   * @param array $form
   *   The form definition array for the full add to cart form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\acm_sku\AcquiaCommerce\SKUPluginInterface::addToCartForm()
   * @see \Drupal\acm_sku\AcquiaCommerce\SKUPluginInterface::addToCartValidate()
   */
  public function addToCartSubmit(array &$form, FormStateInterface $form_state);

  /**
   * Process import function.
   *
   * @param \Drupal\acm_sku\Entity\SKUInterface $sku
   *   SKU to update.
   * @param array $product
   *   Product array from the API.
   *
   * @return bool
   *   True if processing is complete,
   *   false if more processing is required later.
   */
  public function processImport(SKUInterface $sku, array $product);

  /**
   * Returns the SKUs cart name.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   SKU to get Cart Name.
   * @param array $cart
   *   Item array from cart.
   * @param bool $asString
   *   Indicates if function should return a string or a renderable array.
   *
   * @return mixed
   *   Name as string or a renderable object
   */
  public function cartName(SKU $sku, array $cart, $asString = FALSE);

  /**
   * Returns the SKU's display node.
   *
   * @param \Drupal\acm_sku\Entity\SKUInterface $sku
   *   The object of product.
   * @param bool $check_parent
   *   Flag to check for parent sku or not (for configurable products).
   * @param bool $create_translation
   *   Flag to create translation if node available and translation not
   *   available. Used during sync.
   *
   * @return \Drupal\node\Entity\Node|null
   *   Return object of Node or null if not found.
   */
  public function getDisplayNode(SKUInterface $sku, $check_parent = TRUE, $create_translation = FALSE);

  /**
   * Returns the locale-aware formatted price like this '$1,234.56'.
   *
   * Calls the commerceguys currency formatting functions which
   * use the CLDR locales dataset to fetch currency and number formats.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   The object of product (sku entity).
   * @param bool $fetchOriginalPrice
   *   Indicates if the original price should be returned
   *   (normally the final price is returned).
   *
   * @return string|array
   *   Get locale-aware formatted prices with currency, if there are any
   *   related products, min and max price is returned in an array.
   */
  public function getNumberFormattedPrice(SKU $sku, $fetchOriginalPrice = FALSE);

  /**
   * Get the display formatted price string.
   *
   * For use in the admin grid of SKU and other places the product price might
   * appear on the admin pages. It is different to the price displays
   * on the front of the website which, instead, use the
   * Twig HTML templates for layout.
   *
   * Returns the fully formatted price display without any HTML. Examples are:
   * "$1,234.56 excluding tax"
   * "$1,234.56 including $234.56 tax"
   * "From $234.56 to $1,234.56"
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   The object of product.
   * @param bool $fetchOriginalPrice
   *   Indicates if the original price should be returned
   *   (normally the final price is returned).
   *
   * @return string
   *   Formatted price string.
   */
  public function getAdminGridDisplayFormattedPrice(SKU $sku, $fetchOriginalPrice = FALSE);

  /**
   * Check if product is in stock.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   SKU Entity.
   *
   * @return bool
   *   TRUE if product is in stock.
   */
  public function isProductInStock(SKU $sku);

  /**
   * Returns the stock for the given sku.
   *
   * @param string|\Drupal\acm_sku\Entity\SKU $sku
   *   SKU code of the product.
   *
   * @return int
   *   Available stock quantity.
   */
  public function getStock($sku);

  /**
   * Refresh stock for particular SKU.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   SKU Entity.
   */
  public function refreshStock(SKU $sku);

}
