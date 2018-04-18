<?php

namespace Drupal\acm\User;

use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface for a service which has the current account stored.
 */
interface AccountProxyInterface extends AccountInterface {

  /**
   * Sets the account.
   *
   * @param null|array $account
   *   The current account.
   */
  public function setAccount($account = NULL);

  /**
   * Gets the currently wrapped account.
   *
   * @return \Drupal\acm\User\CommerceAccountInterface
   *   The current account.
   */
  public function getAccount();

  /**
   * Updates the current account's field values.
   *
   * @param array $fields
   *   An array of fields to update along with the new value.
   *
   * @return array|null
   *   An array of user field values, or NULL if not updated.
   */
  public function updateAccount(array $fields = []);

}
