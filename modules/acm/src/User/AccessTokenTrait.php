<?php

namespace Drupal\acm\User;

/**
 * Trait AccessTokenTrait.
 */
trait AccessTokenTrait {

  /**
   * The name of the cookie that stores the customer access token.
   *
   * @var string
   */
  protected $accessTokenCookie = 'acm_customer_access_token';

  /**
   * Gets a user access token.
   */
  protected function getAccessToken() {
    $cookies = $_COOKIE;

    if (isset(\Drupal::request()->cookies)) {
      $cookies = \Drupal::request()->cookies->all();
    }

    $cookie_name = 'Drupal_visitor_' . $this->accessTokenCookie;

    if (isset($cookies[$cookie_name])) {
      return $cookies[$cookie_name];
    }

    return NULL;
  }

  /**
   * Sets the customer access token.
   *
   * @param string $token
   *   The access token.
   * @param null|int $expire
   *   How long from now until the cookie expires, in ms. NULL for expire with
   *   session, int for custom expiry.
   */
  public function setAccessToken($token = NULL, $expire = 0) {
    if (!empty($expire)) {
      $expire = REQUEST_TIME + $expire;
    }

    if (isset(\Drupal::request()->cookies)) {
      \Drupal::request()->cookies->set('Drupal_visitor_' . $this->accessTokenCookie, $token);
    }

    setrawcookie('Drupal.visitor.' . $this->accessTokenCookie, rawurlencode($token), $expire, '/');
  }

}
