<?php

namespace Drupal\acm_sku\Plugin\AcquiaCommerce\SKUType;

use Drupal\acm_sku\AcquiaCommerce\SKUPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\acm_sku\Entity\SKU;

/**
 * Defines the simple SKU type.
 *
 * @SKUType(
 *   id = "virtual",
 *   label = @Translation("Virtual SKU"),
 *   description = @Translation("Virtual SKU for buying a variation of Configurable SKU"),
 * )
 */
class Virtual extends SKUPluginBase {

  /**
   * {@inheritdoc}
   */
  public function addToCartForm(array $form, FormStateInterface $form_state, SKU $sku = NULL) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function addToCartSubmit(array &$form, FormStateInterface $form_state) {

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

}
