<?php

namespace Drupal\Tests\acm_cart\Unit;

use Drupal\acm_cart\CartStorage;

/**
 * Mock CartStorage.
 */
class MockCartStorage extends CartStorage {

  /**
   * Storage for the set cookies.
   *
   * @var array
   */
  protected $cookies = [];

  /**
   * {@inheritdoc}
   */
  public function isUserAnonymous() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerId() {
    return NULL;
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
  public function setCookie($name, $value = '', $expire = 0, $path = '') {
    $this->cookies["Drupal_visitor_{$name}"] = $value;
  }

}
