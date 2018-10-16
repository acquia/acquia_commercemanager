<?php

namespace Drupal\acm\User;

use Drupal\Core\Session\AccountInterface;

/**
 * Defines an account interface which represents the current commerce user.
 */
interface CommerceAccountInterface extends AccountInterface {

  /**
   * Returns the customer ID.
   *
   * @return int
   *   The customer ID.
   */
  public function getCustomerId();

  /**
   * Returns the customer first name.
   *
   * @return string
   *   The first name.
   */
  public function getFirstname();

  /**
   * Returns the customer last name.
   *
   * @return string
   *   The last name.
   */
  public function getLastname();

  /**
   * Returns the customer title.
   *
   * @return string
   *   The title.
   */
  public function getTitle();

  /**
   * Returns the customer date of birth.
   *
   * @return string
   *   The dob.
   */
  public function getDateOfBirth();

  /**
   * Returns the customer addresses.
   *
   * @return array
   *   An array of addresses.
   */
  public function getAddresses();

  /**
   * Returns the created time.
   *
   * @return int
   *   The created time.
   */
  public function getCreatedTime();

  /**
   * Returns the updated time.
   *
   * @return int
   *   The last updated time.
   */
  public function getUpdatedTime();

  /**
   * Get a specific field.
   *
   * @return mixed
   *   The field value or NULL.
   */
  public function get($field);

  /**
   * Sets a specific field.
   */
  public function set($field, $value = NULL);

}
