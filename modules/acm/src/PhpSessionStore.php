<?php

namespace Drupal\acm;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Stores and retrieves temporary data using $_SESSION.
 */
class PhpSessionStore implements SessionStoreInterface {

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * Constructs a new object for accessing data from a key/value store.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   */
  public function __construct(SessionInterface $session) {
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $default = NULL) {
    $value = $this->session->get($key);

    // Expired or never set.
    if (empty($value)) {
      return $default;
    }

    // Return the value if it's not an array to catch old values in the sesion
    // before this method was added.
    if (!is_array($value)) {
      return $value;
    }

    // Either no custom expire or hasn't expired yet.
    if ((isset($value['expire'])) && ($value['expire'] === 0 || $value['expire'] >= REQUEST_TIME)) {
      return $value['data'];
    }

    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value, $expire = NULL) {
    $data = [
      'data' => $value,
      'expire' => 0,
    ];

    if ($expire) {
      $data['expire'] = REQUEST_TIME + $expire;
    }

    $this->session->set($key, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function remove($key) {
    $this->session->remove($key);
  }

}
