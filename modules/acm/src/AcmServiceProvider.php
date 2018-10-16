<?php

namespace Drupal\acm;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Class AcmServiceProvider.
 */
class AcmServiceProvider extends ServiceProviderBase implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    try {
      // Add cart id cookie context to auto_placeholder_conditions.
      $renderer = $container->getParameter('renderer.config');
      $renderer['auto_placeholder_conditions']['contexts'][] = 'cookies:Drupal_visitor_acm_cart_id';
      $container->setParameter('renderer.config', $renderer);
    }
    catch (\Exception $e) {
      // Do nothing, system might still be installing.
    }
  }

}
