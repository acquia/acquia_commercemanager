<?php

namespace Drupal\acm\User;

use Drupal\Core\Session\AccountInterface;

/**
 * Defines an account interface which represents the current user.
 *
 * Defines an object that has a user id, roles and can have session data. The
 * interface is implemented both by the global session and the user entity.
 */
interface CommerceAccountProxyInterface {

  /**
   * Sets the account.
   *
   * @param Drupal\Core\Session\AccountInterface $account
   *   The current account.
   */
  public function setAccount(AccountInterface $account);

  /**
   * Gets the currently wrapped account.
   *
   * @return \Drupal\acm\User\CommerceAccountInterface
   *   The current account.
   */
  public function getAccount();

  /**
   * Load a commerce user.
   *
   * @return array
   *   An array of user information or NULL if not found.
   */
  public function loadCommerceUser();

  /**
   * Updates a commerce user.
   *
   * @param array $fields
   *   An array of fields to update along with the new value.
   *
   * @return \Drupal\acm\User\CommerceAccountInterface
   *   The current account.
   */
  public function updateCommerceUser(array $fields = []);

}
