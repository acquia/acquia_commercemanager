<?php

namespace Drupal\acm\Access;

use Drupal\acm\User\AccountProxyInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\Routing\Route;

/**
 * Determines access to routes based on login status of current commerce user.
 */
class LoginStatusCheck implements AccessInterface {

  /**
   * The commerce user manager.
   *
   * @var \Drupal\acm\User\AccountProxyInterface
   */
  protected $commerceUserManager;

  /**
   * Constructor.
   *
   * @param \Drupal\acm\User\AccountProxyInterface $commerce_user_manager
   *   The current commerce user.
   */
  public function __construct(AccountProxyInterface $commerce_user_manager) {
    $this->commerceUserManager = $commerce_user_manager;
  }

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route) {
    $account = $this->commerceUserManager->getAccount();
    $required_status = filter_var($route->getRequirement('_commerce_user_is_logged_in'), FILTER_VALIDATE_BOOLEAN);
    $actual_status = $account->isAuthenticated();
    return AccessResult::allowedIf($required_status === $actual_status);
  }

}
