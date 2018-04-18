<?php

namespace Drupal\acm\User;

/**
 * An account implementation representing an anonymous commerce user.
 */
class AnonymousCommerceUserSession extends CommerceUserSession {

  /**
   * Constructs a new anonymous commerce user session.
   *
   * Intentionally don't allow parameters to be passed in like UserSession.
   */
  public function __construct() {
  }

}
