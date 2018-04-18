<?php

namespace Drupal\acm_customer\Plugin\CustomerPages;

/**
 * Provides the default customer/account pages.
 *
 * @ACMCustomerPages(
 *   id = "acm_account_pages",
 *   label = "Acquia Commerce Account Pages",
 * )
 */
class AccountPages extends CustomerPagesBase {

  /**
   * {@inheritdoc}
   */
  public function getPages() {
    return [
      'profile' => [
        'title' => 'Profile',
        'edit_title' => 'Profile',
      ],
      'orders' => [
        'title' => 'Recent Orders',
        'edit_title' => 'Order Details',
      ],
      'addresses' => [
        'title' => 'Addresses',
        'edit_title' => 'Addresses',
      ],
    ];
  }

}
