<?php

namespace Drupal\acm\User;

/**
 * A proxied implementation of AccountInterface for external users.
 */
class ExternalCommerceAccountProxy extends CommerceAccountProxyBase {

  /**
   * {@inheritdoc}
   */
  public function loadCommerceUser() {
    $account = NULL;

    if ($token = $this->getAccessToken()) {
      $account = $this->apiWrapper->silentRequest('getCurrentCustomer', [$token]);
      // If it failed, unset the current access token so we don't keep trying
      // to load the user over and over again with an expired access token.
      if (!$account) {
        $this->setAccessToken();
      }
    }

    return $account;
  }

}
