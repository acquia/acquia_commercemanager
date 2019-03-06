<?php

namespace Drupal\acm_sku\Plugin\AcquiaCommerce\SKUType;

use Drupal\acm\Connector\APIWrapper;
use Drupal\acm_sku\AcquiaCommerce\SKUPluginBase;
use Drupal\acm_sku\AddToCartErrorEvent;
use Drupal\acm_sku\Entity\SKU;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the simple SKU type.
 *
 * @SKUType(
 *   id = "simple",
 *   label = @Translation("Simple SKU"),
 *   description = @Translation("Simple SKU for buying a single SKU"),
 * )
 */
class Simple extends SKUPluginBase {

  /**
   * {@inheritdoc}
   */
  public function addToCartForm(array $form, FormStateInterface $form_state, SKU $sku = NULL) {
    if (empty($sku)) {
      return $form;
    }

    /** @var \Drupal\acm_sku\CartFormHelper $helper */
    $helper = \Drupal::service('acm_sku.cart_form_helper');

    $form['sku_id'] = [
      '#type' => 'hidden',
      '#value' => $sku->id(),
    ];

    $form['quantity'] = [
      '#title' => t('Quantity'),
      '#type' => 'number',
      '#default_value' => 1,
      '#required' => TRUE,
      '#access' => $helper->showQuantity(),
      '#size' => 2,
      '#attributes' => [
        'min' => '0',
      ],
    ];

    $form['add_to_cart'] = [
      '#type' => 'submit',
      '#value' => t('Add to cart'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberFormattedPrice(SKU $sku, $fetchOriginalPrice = FALSE) {
    $priceField = "price";
    if ($fetchOriginalPrice) {
      $priceField = "price_original";
    }
    $price = $sku->get($priceField)->value;

    return \Drupal::service('acm.i18n_helper')->formatPrice($price);
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminGridDisplayFormattedPrice(SKU $sku, $fetchOriginalPrice = FALSE) {
    return $this->getNumberFormattedPrice($sku, $fetchOriginalPrice);
  }

  /**
   * {@inheritdoc}
   */
  public function addToCartSubmit(array &$form, FormStateInterface $form_state) {

    /* @var \Drupal\acm_cart\CartStorageInterface */
    $cartStorage = \Drupal::service('acm_cart.cart_storage');

    /* @var \Drupal\acm_cart\CartInterface */
    $cart = $cartStorage->loadCart(TRUE);

    // Cart here can be empty only if APIs aren't working.
    // Call above is to create cart if empty, we except a new or old cart here
    // and it can be empty if server is not working or in maintenance mode.
    if (empty($cart)) {
      drupal_set_message(t('An error occured, please try again.'), 'error');
      $e = new \Exception(acm_api_down_global_error_message(), APIWrapper::API_DOWN_ERROR_CODE);
      // Dispatch event so action can be taken.
      $dispatcher = \Drupal::service('event_dispatcher');
      $event = new AddToCartErrorEvent($e);
      $dispatcher->dispatch(AddToCartErrorEvent::SUBMIT, $event);
      return;
    }

    $sku_entity = SKU::load($form_state->getValue('sku_id'));
    $sku = $sku_entity->getSku();
    $quantity = $form_state->getValue('quantity');

    drupal_set_message(
      t('Added @quantity of @name to the cart.',
        [
          '@quantity' => $quantity,
          '@name' => $sku_entity->name->value,
        ]
      ));

    $cartStorage->addItemToCart($sku, $quantity);

    try {
      $cartStorage->updateCart();
    }
    catch (\Exception $e) {
      if (acm_is_exception_api_down_exception($e)) {
        // Remove item from cart (because we can't restore the cart
        // if the Commerce Connector is unavailable)
        $cartStorage->removeItemFromCart($sku);
      }
      // Clear stock cache.
      $this->refreshStock($sku_entity);

      // Dispatch event so action can be taken.
      $dispatcher = \Drupal::service('event_dispatcher');
      $event = new AddToCartErrorEvent($e);
      $dispatcher->dispatch(AddToCartErrorEvent::SUBMIT, $event);
    }
  }

}
