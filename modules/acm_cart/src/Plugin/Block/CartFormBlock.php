<?php

namespace Drupal\acm_cart\Plugin\Block;

use Drupal\acm_cart\Form\CustomerCartForm;
use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'CartFormBlock' block.
 *
 * @Block(
 *   id = "cart_form_block",
 *   admin_label = @Translation("Cart Form block"),
 * )
 */
class CartFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $build['cart_form_block'] = \Drupal::formBuilder()
      ->getForm(CustomerCartForm::class);

    return $build;
  }

}
