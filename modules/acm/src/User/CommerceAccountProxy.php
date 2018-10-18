<?php

namespace Drupal\acm\User;

/**
 * A proxied implementation of AccountInterface for normal drupal user accounts.
 */
class CommerceAccountProxy extends CommerceAccountProxyBase {

  /**
   * {@inheritdoc}
   */
  public function loadCommerceUser() {
    $account = NULL;

    try {
      $user = \Drupal::currentUser();
      if ($email = $user->getEmail()) {
        $account = $this->apiWrapper->getCustomer($email);
      }
    }
    catch (\Exception $e) {
    }

    return $account;
  }

}
