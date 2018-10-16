<?php

namespace Drupal\acm\Connector;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class APIWrapperFactory.
 */
class APIWrapperFactory {

  /**
   * Creates an APIWrapperInterface object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   *
   * @return \Drupal\acm\Connector\APIWrapperInterface
   *   An api wrapper object.
   */
  public static function get(ContainerInterface $container) {
    $test_mode = $container
      ->get('config.factory')
      ->get('acm.connector')
      ->get('test_mode');

    if ($test_mode) {
      return $container->get('acm.test_agent_api');
    }

    return $container->get('acm.agent_api');
  }

}
