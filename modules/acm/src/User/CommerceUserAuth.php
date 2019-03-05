<?php

namespace Drupal\acm\User;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\user\UserAuthInterface;

/**
 * Validates commerce user authentication credentials.
 */
class CommerceUserAuth implements UserAuthInterface {

  /**
   * API Wrapper object.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  private $apiWrapper;

  /**
   * Constructs a CommerceUserAuth object.
   *
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   ApiWrapper object.
   */
  public function __construct(APIWrapperInterface $api_wrapper) {
    $this->apiWrapper = $api_wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate($username, $password) {
    $token = FALSE;

    if (!empty($username) && strlen($password) > 0) {
      $token = $this->apiWrapper->silentRequest('getCustomerToken', [$username, $password]);
    }

    return $token;
  }

}
