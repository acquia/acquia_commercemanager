<?php

namespace Drupal\acm_sku\Plugin\Field\FieldFormatter;

use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'product_link' formatter.
 *
 * @FieldFormatter(
 *   id = "product_link",
 *   label = @Translation("Product Link"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ProductImageLinkFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    // Load associated product display node.
    $config = \Drupal::configFactory()->get('acm.connector');
    $product_node_type = $config->get('product_node_type') ?: 'acm_product';
    $sku_field_name = $config->get('sku_field_name') ?: 'field_skus';
    $entity = $items->getEntity();

    $query = \Drupal::entityQuery('node')
      ->condition('type', $product_node_type)
      ->condition($sku_field_name, $entity->getSku());
    $nids = $query->execute();
    $nid = reset($nids);

    $node_entity = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $url = $node_entity->urlInfo();

    foreach ($elements as &$element) {
      if (!is_null($element['#url'])) {
        $element['#url'] = $url;
      }
    }

    return $elements;
  }

}
