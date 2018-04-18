<?php

namespace Drupal\acm_customer\Ajax;

use Drupal\Core\Ajax\BaseCommand;

/**
 * Custom AJAX Command.
 *
 * Command for passing a rendered customer form to the JavaScript app.
 */
class CustomerFormCommand extends BaseCommand {

  /**
   * Constructs a CustomerFormCommand object.
   *
   * @param string $data
   *   The data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('acmCustomerForm', $data);
  }

}
