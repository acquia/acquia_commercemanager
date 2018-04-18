<?php

namespace Drupal\acm\User;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Class CommerceUserManager.
 */
class CommerceUserManager implements AccountProxyInterface, CommerceAccountInterface {

  use \Drupal\acm\User\AccessTokenTrait;

  /**
   * The key that stores the current user session.
   *
   * @const USER_STORAGE_KEY
   */
  const USER_STORAGE_KEY = 'acm_user';

  /**
   * The cache backend to use for temp storing user info.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The current commerce user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentCommerceUser;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend interface to use for temp storing user info.
   * @param \Drupal\acm\CommerceAccountProxyInterface $current_commerce_user
   *   The current commerce user.
   */
  public function __construct(CacheBackendInterface $cache, CommerceAccountProxyInterface $current_commerce_user) {
    $this->cache = $cache;
    $this->currentCommerceUser = $current_commerce_user;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccount($account = NULL) {
    // Wrap in a user session before setting the current account.
    if (!$account) {
      // No account, so use anonymous session and unset anything stored from
      // the previous logged in session.
      $account = new AnonymousCommerceUserSession();
      if ($access_token = $this->getAccessToken()) {
        $this->cache->delete(self::USER_STORAGE_KEY . ':' . $access_token);
      }
      $this->setAccessToken();
    }
    else {
      // Check if we need to wrap the account in a user session.
      if (!($account instanceof CommerceAccountInterface)) {
        $account = new CommerceUserSession((array) $account);
      }

      // Store the account for 5 minutes to prevent unnecessary API calls.
      $access_token = $this->getAccessToken();
      $expire = 600 + \Drupal::time()->getRequestTime();
      $this->cache->set(self::USER_STORAGE_KEY . ':' . $access_token, $account, $expire);
    }

    $this->currentCommerceUser->setAccount($account);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount() {
    // Check if we stored the user in the session and that they're
    // authenticated, otherwise an API call is going to be made.
    $access_token = $this->getAccessToken();
    if ($access_token && $cached_user = $this->cache->get(self::USER_STORAGE_KEY . ':' . $access_token)) {
      $account = $cached_user->data;
      if ($account->isAuthenticated()) {
        return $account;
      }
    }

    // Account is anonymous, so see if we can load the commerce use via API
    // again, either based on a newly set access token, or a logged in drupal
    // user's email address.
    $account = $this->currentCommerceUser->getAccount();

    // If we have an authenticated account, set it as the current.
    if ($account->isAuthenticated()) {
      $this->setAccount($account);
    }

    return $account;
  }

  /**
   * {@inheritdoc}
   */
  public function updateAccount(array $fields = []) {
    // If the account was updated, set the account again to store the updated
    // values.
    if ($updated_account = $this->currentCommerceUser->updateCommerceUser($fields)) {
      $this->setAccount($updated_account);
      return $updated_account;
    }

    return $this->getAccount();
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->getAccount()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles($exclude_locked_roles = FALSE) {
    return $this->getAccount()->getRoles($exclude_locked_roles);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission($permission) {
    return $this->getAccount()->hasPermission($permission);
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthenticated() {
    return $this->getAccount()->isAuthenticated();
  }

  /**
   * {@inheritdoc}
   */
  public function isAnonymous() {
    return $this->getAccount()->isAnonymous();
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredLangcode($fallback_to_default = TRUE) {
    return $this->getAccount()->getPreferredLangcode($fallback_to_default);
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredAdminLangcode($fallback_to_default = TRUE) {
    return $this->getAccount()->getPreferredAdminLangcode($fallback_to_default);
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
    return $this->getAccount()->getAccountName();
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    return $this->getAccount()->getDisplayName();
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    return $this->getAccount()->getEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeZone() {
    return $this->getAccount()->getTimeZone();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessedTime() {
    return $this->getAccount()->getLastAccessedTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerId() {
    return $this->getAccount()->getCustomerId();
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstname() {
    return $this->getAccount()->getFirstname();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastname() {
    return $this->getAccount()->getLastname();
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->getAccount()->getTitle();
  }

  /**
   * {@inheritdoc}
   */
  public function getCountry() {
    return $this->getAccount()->getCountry();
  }

  /**
   * {@inheritdoc}
   */
  public function getDateOfBirth() {
    return $this->getAccount()->getDateOfBirth();
  }

  /**
   * {@inheritdoc}
   */
  public function getAddresses() {
    return $this->getAccount()->getAddresses();
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->getAccount()->getCreatedTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedTime() {
    return $this->getAccount()->getUpdatedTime();
  }

  /**
   * {@inheritdoc}
   */
  public function get($field) {
    return $this->getAccount()->get($field);
  }

  /**
   * {@inheritdoc}
   */
  public function set($field, $value = NULL) {
    $this->getAccount()->set($field, $value);
  }

}
