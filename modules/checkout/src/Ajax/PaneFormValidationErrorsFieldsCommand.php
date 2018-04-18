<?php

namespace Drupal\acm_checkout\Ajax;

use Drupal\Core\Ajax\BaseCommand;

/**
 * Custom AJAX Command.
 *
 * Command to indicate a checkout pane form was attempted to be saved but
 * failed validation and passes the fields that failed through to the
 * Javascript app.
 */
class PaneFormValidationErrorsFieldsCommand extends BaseCommand {

  /**
   * Constructs a PaneFormValidationErrorsFieldsCommand object.
   *
   * @param string $data
   *   The data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('acm_checkoutPaneFormValidationErrorsFields', $data);
  }

}
