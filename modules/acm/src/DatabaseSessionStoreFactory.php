<?php

namespace Drupal\acm;

use Drupal\acm\User\AccountProxyInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Creates a DatabaseSessionStore object for a given collection.
 */
class DatabaseSessionStoreFactory {

  /**
   * Constructs a Drupal\acm\DatabaseSessionStore object.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $storage_factory
   *   The key/value store factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock_backend
   *   The lock object used for this data.
   * @param \Drupal\acm\User\AccountProxyInterface $current_user
   *   The current commerce account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function __construct(KeyValueExpirableFactoryInterface $storage_factory, LockBackendInterface $lock_backend, AccountProxyInterface $current_user, RequestStack $request_stack, $expire = 604800) {
    $this->storageFactory = $storage_factory;
    $this->lockBackend = $lock_backend;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->expire = $expire;
  }

  /**
   * Creates a DatabaseSessionStore.
   *
   * @param string $collection
   *   The collection name to use for this key/value store. This is typically
   *   a shared namespace or module name, e.g. 'views', 'entity', etc.
   *
   * @return \Drupal\acm\DatabaseSessionStore
   *   An instance of the key/value store.
   */
  public function get($collection) {
    // Store the data for this collection in the database.
    $storage = $this->storageFactory->get("acm.database_store.$collection");
    return new DatabaseSessionStore($storage, $this->lockBackend, $this->currentUser, $this->requestStack, $this->expire);
  }

}
