<?php

namespace Drupal\acm\User;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommerceAccountProxyFactory.
 */
class CommerceAccountProxyFactory {

  /**
   * Creates an AccountProxyInterface object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   An account proxy object.
   */
  public static function get(ContainerInterface $container) {
    $use_ecomm_sessions = $container
      ->get('config.factory')
      ->get('acm.commerce_users')
      ->get('use_ecomm_sessions');

    if ($use_ecomm_sessions) {
      return $container->get('acm.external_commerce_account_proxy');
    }

    return $container->get('acm.commerce_account_proxy');
  }

}
