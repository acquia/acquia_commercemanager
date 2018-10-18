<?php

namespace Drupal\acm_sku\Plugin\AcquiaCommerce\SKUType;

use Drupal\acm_sku\AcquiaCommerce\SKUPluginBase;
use Drupal\acm_sku\Entity\SKUInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\acm_sku\Entity\SKU;
use Drupal\acm_sku\AddToCartErrorEvent;

/**
 * Defines the grouped SKU type.
 *
 * @SKUType(
 *   id = "grouped",
 *   label = @Translation("Grouped SKU"),
 *   description = @Translation("Grouped SKU for picking out a grouped product."),
 * )
 */
class Grouped extends SKUPluginBase {

  /**
   * {@inheritdoc}
   */
  public function addToCartForm(array $form, FormStateInterface $form_state, SKU $sku = NULL) {
    if (empty($sku)) {
      return $form;
    }

    $form['grouped_items'] = [
      '#type' => 'table',
      '#header' => [
        t('Product'),
        t('Quantity'),
        t('Price'),
      ],
      '#empty' => t('This grouped product has no items.'),
    ];

    foreach ($sku->field_grouped_skus as $grouped_sku) {
      $grouped_sku = SKU::loadFromSku($grouped_sku->getString());
      $id = $grouped_sku->getSku();

      $form['grouped_items'][$id]['name'] = [
        '#plain_text' => $grouped_sku->label(),
      ];

      $form['grouped_items'][$id]['quantity'] = [
        '#type' => 'number',
        '#default_value' => 0,
        '#attributes' => [
          'min' => '0',
        ],
      ];

      $form['grouped_items'][$id]['price'] = [
        '#plain_text' => $grouped_sku->price->first()->value,
      ];
    }

    $form['add_to_cart'] = [
      '#type' => 'submit',
      '#value' => t('Add to cart'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function addToCartSubmit(array &$form, FormStateInterface $form_state) {
    $cartStorage = \Drupal::service('acm_cart.cart_storage');

    $skus = $form_state->getValue('grouped_items');

    $added = 0;

    foreach ($skus as $sku => $quantity) {
      $quantity = (int) $quantity['quantity'];
      if ($quantity > 0) {
        $cartStorage->addItemToCart($sku, $quantity);

        drupal_set_message(
          t('Added @quantity of @name to the cart.',
            [
              '@quantity' => $quantity,
              '@name' => SKU::loadFromSku($sku)->label(),
            ]
        ));

        $added++;
      }
    }

    if ($added == 0) {
      drupal_set_message(t('Please select a quantity greater than 0.'), 'error');
    }

    try {
      $cartStorage->updateCart();
    }
    catch (\Exception $e) {
      if (acm_is_exception_api_down_exception($e)) {
        // Remove item from cart (because we can't restore the cart
        // if the Commerce Connector is unavailable)
        foreach ($skus as $sku => $quantity) {
          $cartStorage->removeItemFromCart($sku);
        }
      }

      // @TODO: Handle clearing stock cache for grouped products.
      // Dispatch event so action can be taken.
      $dispatcher = \Drupal::service('event_dispatcher');
      $event = new AddToCartErrorEvent($e);
      $dispatcher->dispatch(AddToCartErrorEvent::SUBMIT, $event);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberFormattedPrice(SKU $sku, $fetchOriginalPrice = FALSE) {
    // Fetch the config.
    $config = $this->configFactory->get('acm.currency');

    $configuredSkus = $sku->get('field_configured_skus')->getValue();

    $priceField = "price";
    if ($fetchOriginalPrice) {
      $priceField = "price_original";
    }

    $price = NULL;
    $max_price = 0;
    $min_price = NULL;
    foreach ($configuredSkus as $configuredSkuCode) {
      // Load configured SKU entity.
      $configuredSku = SKU::loadFromSku($configuredSkuCode['value']);

      $price = $configuredSku->get($priceField)->value;
      if ($price < $min_price || $min_price === NULL) {
        $min_price = $price;
      }
      if ($price > $max_price) {
        $max_price = $price;
      }
    }

    if ($max_price != $min_price) {
      $formattedMinPrice = \Drupal::service('acm.i18n_helper')->formatPrice($min_price);
      $formattedMaxPrice = \Drupal::service('acm.i18n_helper')->formatPrice($max_price);
      $formattedPrice = [
        'min_price' => $formattedMinPrice,
        'max_price' => $formattedMaxPrice,
      ];
    }
    else {
      // It isn't a price range.
      $formattedPrice = \Drupal::service('acm.i18n_helper')->formatPrice($max_price);
    }

    return $formattedPrice;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminGridDisplayFormattedPrice(SKU $sku, $returnOriginal = FALSE) {
    $prices = $this->getNumberFormattedPrice($sku);
    if (is_array($prices)) {
      $config = $this->configFactory->get('acm.currency');
      $formatString = $config->get('price_range_format_string');
      $tokenizedPrices = [
        '@min' => $prices['min_price'],
        '@max' => $prices['max_price'],
      ];
      // @TODO(mirom): Rebuild using tokens.
      $formattedPrice = str_replace(array_keys($tokenizedPrices), $tokenizedPrices, $formatString);
    }
    else {
      $formattedPrice = $prices;
    }
    return $formattedPrice;
  }

  /**
   * {@inheritdoc}
   */
  public function processImport(SKUInterface $sku, array $product) {
    $sku->field_grouped_skus->setValue([]);

    foreach ($product['linked'] as $linked_sku) {
      // Linked may contain associated, upsell, crosssell and related.
      // We want only the associated ones for grouped.
      if ($linked_sku['type'] == 'associated') {
        $sku->field_grouped_skus->set(
          $linked_sku['position'],
          $linked_sku['linked_sku']
        );
      }
    }
  }

}
