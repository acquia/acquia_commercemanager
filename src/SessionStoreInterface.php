<?php

namespace Drupal\acm;

/**
 * Interface for SessionStores.
 *
 * Defines the interface for storages that can be used for saving session data
 * like carts.
 *
 * @package Drupal\acm
 */
interface SessionStoreInterface {

  /**
   * Retrieves a value from this store for a given key.
   *
   * @param string $key
   *   The key of the data to retrieve.
   * @param mixed $default
   *   The default value to use if the key is not found.
   *
   * @return mixed
   *   The data associated with the key, or the default value if no value
   *   exists.
   */
  public function get($key, $default = NULL);

  /**
   * Stores a particular key/value pair in this store.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   * @param int $expire
   *   The time to live for items, in seconds. If set to NULL, it will use
   *   $this->expire set in the constructor.
   */
  public function set($key, $value, $expire = NULL);

  /**
   * Removes a particular key/value pair in this store.
   *
   * @param string $key
   *   The key of the data to store.
   */
  public function remove($key);

}
