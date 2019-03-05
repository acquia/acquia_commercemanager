<?php

namespace Drupal\Tests\acm\Unit;

use Drupal\acm\DatabaseSessionStore;

/**
 * Mock DatabaseSessionStore.
 */
class MockDatabaseSessionStore extends DatabaseSessionStore {

  /**
   * Value to store the last generated session id.
   *
   * @var int
   */
  protected $currentId = 0;

  /**
   * Storage for the set cookies.
   *
   * @var array
   */
  protected $cookies = [];

  /**
   * {@inheritdoc}
   */
  protected function generateSessionId() {
    $this->currentId += 1;
    return $this->currentId;
  }

  /**
   * {@inheritdoc}
   */
  public function getCookies() {
    return $this->cookies;
  }

  /**
   * {@inheritdoc}
   */
  public function setCookie($name, $value = '') {
    $this->cookies["Drupal_visitor_{$name}"] = $value;
  }

}
