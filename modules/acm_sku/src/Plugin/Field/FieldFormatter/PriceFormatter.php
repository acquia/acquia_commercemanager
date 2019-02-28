<?php

namespace Drupal\acm_sku\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'price_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "price_formatter",
 *   label = @Translation("Price Formatter"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class PriceFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      if (is_numeric($item->value)) {
        $elements[$delta] = [
          '#theme' => 'acm_sku_price',
          '#price' => $item->value,
          '#sku' => $item->getEntity(),
        ];
      }
      else {
        $elements[$delta] = [
          '#markup' => $item->value,
        ];
      }

    }

    return $elements;
  }

}
