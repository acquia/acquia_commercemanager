<?php

namespace Drupal\acm\User;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Session\AccountInterface;

/**
 * A base class implementation of a proxied AccountInterface.
 */
abstract class CommerceAccountProxyBase extends AccountProxy implements CommerceAccountInterface, CommerceAccountProxyInterface {

  use \Drupal\acm\User\AccessTokenTrait;

  /**
   * Stores the tempstore factory.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  protected $apiWrapper;

  /**
   * Constructs a new commerce user session.
   *
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   The api wrapper.
   */
  public function __construct(APIWrapperInterface $api_wrapper) {
    $this->apiWrapper = $api_wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccount(AccountInterface $account) {
    // If the passed account is already proxied, use the actual account instead
    // to prevent loops.
    if ($account instanceof static) {
      $account = $account->getAccount();
    }
    $this->account = $account;
    $this->id = $account->id();
    date_default_timezone_set(drupal_get_user_timezone());
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount() {
    if (!isset($this->account) || $this->account->isAnonymous()) {
      // User has already logged in an account fetched from API.
      if ($user = $this->loadCommerceUser()) {
        $account = new CommerceUserSession($user);
        $this->setAccount($account);
      }
      else {
        // Fallback to an anonymous user.
        $this->account = new AnonymousCommerceUserSession();
      }
    }

    return $this->account;
  }

  /**
   * {@inheritdoc}
   */
  public function updateCommerceUser(array $fields = []) {
    $account = $this->getAccount();

    if (!$account->isAuthenticated()) {
      return $account;
    }

    try {
      // Required fields.
      $customer_fields = [
        'customer_id' => $account->id(),
        'firstname' => $account->getFirstname(),
        'lastname' => $account->getLastname(),
        'email' => $account->getEmail(),
      ];

      // Set the options for the updateCustomer request.
      $options = [
        'access_token' => $this->getAccessToken(),
      ];

      foreach ($fields as $field_name => $field_value) {
        switch ($field_name) {
          case 'password':
            $options['password'] = $field_value;
            break;

          case 'password_old':
          case 'old_password':
            $options['password_old'] = $field_value;
            break;

          default:
            $customer_fields[$field_name] = $field_value;
            break;
        }
      }

      $updated_user = $this->apiWrapper->updateCustomer($customer_fields, $options);
      $updated_user = (array) $updated_user;

      // Only update the fields in the current account object that are being
      // updated. It's not guaranteed that the updateCustomer method will
      // return the whole user object after an update action, so this is to
      // prevent losing any data temporarily.
      foreach ($customer_fields as $field => $value) {
        $updated_value = $updated_user[$field];
        $account->set($field, $updated_value);
      }

      // Update the account in memory/storage.
      $this->setAccount($account);
    }
    catch (\Exception $e) {
    }

    return $this->account;
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
