<?php

namespace Drupal\acm_customer\Ajax;

use Drupal\Core\Ajax\BaseCommand;

/**
 * Custom AJAX Command.
 *
 * Command to indicate a customer form was attempted to be saved but failed
 * validation and passes the fields that failed through to the Javascript app.
 */
class CustomerFormValidationErrorsFieldsCommand extends BaseCommand {

  /**
   * Constructs a CustomerFormValidationErrorsFieldsCommand object.
   *
   * @param string $data
   *   The data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('acmCustomerFormValidationErrorsFields', $data);
  }

}
