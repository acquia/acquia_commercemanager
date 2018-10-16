<?php

namespace Drupal\acm_checkout\Ajax;

use Drupal\Core\Ajax\BaseCommand;

/**
 * Custom AJAX Command.
 *
 * Command to indicate a checkout pane form was attempted to be saved but
 * failed validation and passes the validation errors through to the Javascript
 * app.
 */
class PaneFormValidationErrorsCommand extends BaseCommand {

  /**
   * Constructs a PaneFormValidationErrorsCommand object.
   *
   * @param string $data
   *   The data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('acm_checkoutPaneFormValidationErrors', $data);
  }

}
