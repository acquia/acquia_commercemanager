<?php

namespace Drupal\acm\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'CommerceUser' form block.
 *
 * @Block(
 *   id = "commerce_user_form_block",
 *   admin_label = @Translation("Commerce User Form block"),
 *   deriver = "\Drupal\acm\Plugin\Derivative\CommerceUserFormBlock"
 * )
 */
class CommerceUserFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block_id = $this->getDerivativeId();
    $config = $this->getPluginDefinition()['config'];

    $build = [];
    $build[$block_id] = \Drupal::formBuilder()
      ->getForm($config['class']);

    return $build;
  }

}
