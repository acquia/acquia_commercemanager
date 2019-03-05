<?php

namespace Drupal\acm_checkout\Ajax;

use Drupal\Core\Ajax\BaseCommand;

/**
 * Custom AJAX Command.
 *
 * Command for passing a rendered checkout pane form to the JavaScript
 * app.
 */
class PaneFormSavedCommand extends BaseCommand {

  /**
   * Constructs a PaneFormSavedCommand object.
   *
   * @param string $data
   *   The data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('acm_checkoutPaneFormSaved', $data);
  }

}
