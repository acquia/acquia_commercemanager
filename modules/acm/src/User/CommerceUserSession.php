<?php

namespace Drupal\acm\User;

/**
 * Defines an account interface which represents the commerce current user.
 */
class CommerceUserSession implements CommerceAccountInterface {

  use \Drupal\acm\User\AccessTokenTrait;

  /**
   * The customer created time.
   *
   * @var int
   */
  protected $created = 0;

  /**
   * The customer updated time.
   *
   * @var int
   */
  protected $updated = 0;

  /**
   * Customer ID.
   *
   * @var int
   */
  // @codingStandardsIgnoreStart
  protected $customer_id = 0;
  // @codingStandardsIgnoreEnd

  /**
   * Customer email.
   *
   * @var string
   */
  protected $email = '';

  /**
   * Customer title.
   *
   * @var string
   */
  protected $title = '';

  /**
   * Customer first name.
   *
   * @var string
   */
  protected $firstname = '';

  /**
   * Customer last name.
   *
   * @var string
   */
  protected $lastname = '';

  /**
   * Customer date of birth.
   *
   * @var string
   */
  protected $dob = '';

  /**
   * Customer addresses.
   *
   * @var array
   */
  protected $addresses = [];

  /**
   * Constructs a new commerce user session.
   *
   * @param array $values
   *   Array of initial values for the user session.
   */
  public function __construct(array $values = []) {
    foreach ($values as $key => $value) {
      $this->$key = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    if (isset($this->customer_id)) {
      return $this->customer_id;
    }
    elseif (isset($this->id)) {
      return $this->id;
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles($exclude_locked_roles = FALSE) {
    // These users are always anonymous since they're logged in to the ecom
    // backend instead.
    return AccountInterface::ANONYMOUS_ROLE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission($permission) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthenticated() {
    $id = $this->id();
    $email = $this->getEmail();

    // Hybris uses the email as the customer ID, so if that matches the email
    // then the user is authenticated.
    if ($id === $email) {
      return TRUE;
    }

    return $id > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isAnonymous() {
    $id = $this->id();
    $email = $this->getEmail();

    // Hybris uses the email as the customer ID, so if that matches the email
    // then the user is authenticated.
    if ($id === $email) {
      return FALSE;
    }

    return $id == 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredLangcode($fallback_to_default = TRUE) {
    return 'en';
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredAdminLangcode($fallback_to_default = TRUE) {
    return 'en';
  }

  /**
   * {@inheritdoc}
   */
  public function getUsername() {
    return $this->getAccountName();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountName() {
    return $this->email;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    return $this->firstname . ' ' . $this->lastname;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeZone() {
    // Will return the value for system.date.timezone.default since this user
    // is always anonymous in drupal.
    return drupal_get_user_timezone();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessedTime() {
    return $this->access;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    return $this->email;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerId() {
    return $this->customer_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstname() {
    return $this->firstname;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastname() {
    return $this->lastname;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function getDateOfBirth() {
    return $this->dob;
  }

  /**
   * {@inheritdoc}
   */
  public function getAddresses() {
    return $this->addresses;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->created;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedTime() {
    return $this->updated;
  }

  /**
   * {@inheritdoc}
   */
  public function get($field) {
    if (isset($this->{$field})) {
      return $this->{$field};
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function set($field, $value = NULL) {
    $this->{$field} = $value;
  }

}
