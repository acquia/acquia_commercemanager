<?php

namespace Drupal\acm;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SessionStoreFactory.
 */
class SessionStoreFactory {

  /**
   * Creates a Storage object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   *
   * @return \Drupal\acm\SessionStoreInterface
   *   A storage object.
   */
  public static function get(ContainerInterface $container) {
    $storage_type = $container
      ->get('config.factory')
      ->get('acm.commerce_users')
      ->get('storage_type');

    switch ($storage_type) {
      case 'database_store':
        $storage = $container
          ->get('acm.database_session_store')
          ->get('acm');
        break;

      default:
        $storage = $container
          ->get('acm.php_session_store');
        break;
    }

    return $storage;
  }

}
