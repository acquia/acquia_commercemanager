<?php

namespace Drupal\acm;

use Drupal\acm\User\AccountProxyInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\TempStoreException;

/**
 * Stores and retrieves temporary data for a given owner.
 *
 * This storage should only be used if you're using e-commerce sessions.
 *
 * A DatabaseSessionStore can be used to make temporary, non-cache data
 * available across requests. The data for the DatabaseSessionStore is stored
 * in one key/value collection. DatabaseSessionStore data expires
 * automatically after a given timeframe.
 */
class DatabaseSessionStore extends PrivateTempStore implements SessionStoreInterface {

  /**
   * The key that stores the owner reference.
   *
   * @const OWNER_REFERENCE_NAMESPACE
   */
  const OWNER_REFERENCE_NAMESPACE = 'acm_owner_reference';

  /**
   * The name of the cookie that stores the session id.
   *
   * @const SESSION_ID_COOKIE
   */
  const SESSION_ID_COOKIE = 'acm_sid';

  /**
   * Constructs a new object for accessing data from a key/value store.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $storage
   *   The key/value storage object used for this data. Each storage object
   *   represents a particular collection of data and will contain any number
   *   of key/value pairs.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock_backend
   *   The lock object used for this data.
   * @param \Drupal\acm\User\AccountProxyInterface $current_user
   *   The current commerce user account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function __construct(KeyValueStoreExpirableInterface $storage, LockBackendInterface $lock_backend, AccountProxyInterface $current_user, RequestStack $request_stack, $expire = 604800) {
    $this->storage = $storage;
    $this->lockBackend = $lock_backend;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->expire = $expire;
  }

  /**
   * {@inheritdoc}
   */
  public function remove($key) {
    $this->delete($key);
  }

  /**
   * {@inheritdoc}
   *
   * Overrides \Drupal\Core\TempStore\PrivateTempStore::get().
   */
  public function get($key, $default = NULL) {
    $value = parent::get($key);

    if (isset($value)) {
      return $value;
    }

    return $default;
  }

  /**
   * {@inheritdoc}
   *
   * Overrides \Drupal\Core\TempStore\PrivateTempStore::set().
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when a lock for the backend storage could not be acquired.
   */
  public function set($key, $value, $expire = NULL) {
    $key = $this->createkey($key);
    if (!$this->lockBackend->acquire($key)) {
      $this->lockBackend->wait($key);
      if (!$this->lockBackend->acquire($key)) {
        throw new TempStoreException("Couldn't acquire lock to update item '$key' in '{$this->storage->getCollectionName()}' temporary storage.");
      }
    }

    $value = (object) [
      'owner' => $this->getOwner(),
      'data' => $value,
      'updated' => (int) $this->requestStack->getMasterRequest()->server->get('REQUEST_TIME'),
    ];

    // Fallback to the default expire.
    if (empty($expire)) {
      $expire = $this->expire;
    }

    $this->storage->setWithExpire($key, $value, $expire);
    $this->lockBackend->release($key);
  }

  /**
   * Stores a reference to the current store owner for a particular user.
   *
   * @param string $uid
   *   The user id to relate to the current store.
   * @param string $owner_id
   *   The store owner to associate this user to.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when a lock for the backend storage could not be acquired.
   */
  protected function setOwnerReference($uid, $owner_id) {
    $key = $uid . ':' . self::OWNER_REFERENCE_NAMESPACE;
    if (!$this->lockBackend->acquire($key)) {
      $this->lockBackend->wait($key);
      if (!$this->lockBackend->acquire($key)) {
        throw new TempStoreException("Couldn't acquire lock to update item '$key' in '{$this->storage->getCollectionName()}' temporary storage.");
      }
    }

    $value = (object) [
      'owner' => $uid,
      'data' => $owner_id,
      'updated' => (int) $this->requestStack->getMasterRequest()->server->get('REQUEST_TIME'),
    ];
    $this->storage->setWithExpire($key, $value, $this->expire);
    $this->lockBackend->release($key);
  }

  /**
   * Gets the reference to the current store owner for a particular user.
   *
   * @param string $uid
   *   The user id to relate to the current store.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when a lock for the backend storage could not be acquired.
   */
  protected function getOwnerReference($uid) {
    $key = $uid . ':' . self::OWNER_REFERENCE_NAMESPACE;
    if (($object = $this->storage->get($key)) && ($object->owner == $uid)) {
      return $object->data;
    }
  }

  /**
   * {@inheritdoc}
   *
   * Overrides \Drupal\Core\TempStore\PrivateTempStore::getOwner().
   */
  protected function getOwner() {
    $uid = NULL;

    if ($account = $this->currentUser->getAccount()) {
      $uid = $account->id();

      // Check if the user had stored "session" data before loggin in, and use
      // that "session" id as the owner instead of user id.
      if ($owner = $this->getOwnerReference($uid)) {
        return $owner;
      }
    }

    $cookies = $this->getCookies();
    if (!empty($cookies['Drupal_visitor_' . self::SESSION_ID_COOKIE])) {
      $sid = $cookies['Drupal_visitor_' . self::SESSION_ID_COOKIE];
      // If there's a logged in user and a stored "session" id cookie we want
      // to create a reference to it so if they log in on a different computer
      // they can retrieve their "session" data.
      if ($uid) {
        $this->setOwnerReference($uid, $sid);
      }

      return $sid;
    }

    // If user is logged in use their id as the owner.
    if ($uid) {
      return $uid;
    }

    // If we got this far it's an anonymous user (drupal and commerce), so
    // generate a "session" id and save it for later use.
    $sid = $this->generateSessionId();
    $this->setCookie(self::SESSION_ID_COOKIE, $sid);

    return $sid;
  }

  /**
   * Generates a uuid to use as a session id.
   *
   * @return string
   *   The custom session ID.
   */
  protected function generateSessionId() {
    $uuid = \Drupal::service('uuid')->generate();
    return $uuid;
  }

  /**
   * Gets cookies.
   *
   * @return array
   *   The cookies.
   */
  protected function getCookies() {
    $current_request = $this->requestStack->getCurrentRequest();
    if (isset($current_request->cookies)) {
      return $current_request->cookies->all();
    }
    return $_COOKIE;
  }

  /**
   * Sets a cookie.
   *
   * @param string $name
   *   The cookie name.
   * @param string $value
   *   The cookie value.
   */
  protected function setCookie($name, $value = '') {
    if (PHP_SAPI === 'cli') {
      return;
    }

    $current_request = $this->requestStack->getCurrentRequest();
    if (isset($current_request->cookies)) {
      $current_request->cookies->set("Drupal_visitor_{$name}", $value);
    }
    user_cookie_save([
      $name => $value,
    ]);
  }

}
