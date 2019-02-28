<?php

namespace Drupal\acm_customer\Ajax;

use Drupal\Core\Ajax\BaseCommand;

/**
 * Custom AJAX command.
 *
 * Command for sending messages to the customer app.
 */
class CustomerFormMessageCommand extends BaseCommand {

  /**
   * Constructs a CustomerFormMessageCommand object.
   *
   * @param string $data
   *   THe data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('acmCustomerFormMessage', $data);
  }

}
