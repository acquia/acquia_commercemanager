<?php

namespace Drupal\acm_customer\Ajax;

use Drupal\Core\Ajax\BaseCommand;

/**
 * Custom AJAX Command.
 *
 * Command to indicate a customer form was attempted to be saved but failed
 * validation and passes the validation errors through to the Javascript app.
 */
class CustomerFormValidationErrorsCommand extends BaseCommand {

  /**
   * Constructs a CustomerFormValidationErrorsCommand object.
   *
   * @param string $data
   *   The data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('acmCustomerFormValidationErrors', $data);
  }

}
