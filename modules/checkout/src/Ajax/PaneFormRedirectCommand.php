<?php

namespace Drupal\acm_checkout\Ajax;

use Drupal\Core\Ajax\BaseCommand;

/**
 * Custom AJAX Command.
 *
 * Command for handling checkout step redirects.
 */
class PaneFormRedirectCommand extends BaseCommand {

  /**
   * Constructs a PaneFormRedirectCommand object.
   *
   * @param string $data
   *   The data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('acm_checkoutPaneFormRedirect', $data);
  }

}
