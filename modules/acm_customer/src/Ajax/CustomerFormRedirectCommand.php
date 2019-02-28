<?php

namespace Drupal\acm_customer\Ajax;

use Drupal\Core\Ajax\BaseCommand;

/**
 * Custom AJAX Command.
 *
 * Command for handling customer form redirects.
 */
class CustomerFormRedirectCommand extends BaseCommand {

  /**
   * Constructs a CustomerFormRedirectCommand object.
   *
   * @param string $data
   *   The data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('acmCustomerFormRedirect', $data);
  }

}
