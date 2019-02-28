<?php

namespace Drupal\acm\Connector;

use Drupal\acm\RouteExceptionEvent;

/**
 * Class RouteException.
 */
final class RouteException extends \UnexpectedValueException {

  /**
   * The API operation that triggered the exception.
   *
   * @var string
   */
  private $operation;

  /**
   * Constructor.
   *
   * @param string $operation
   *   The API operation that triggered the exception.
   * @param string $message
   *   The Exception message to throw.
   * @param int $code
   *   The Exception code.
   * @param bool $trigger
   *   TRUE to trigger route events, FALSE to keep them off.
   */
  public function __construct($operation, $message = '', $code = 0, $trigger = TRUE) {
    parent::__construct($message, $code);
    $this->operation = $operation;

    if ($trigger) {
      $dispatcher = \Drupal::service('event_dispatcher');
      $event = new RouteExceptionEvent($this);
      $dispatcher->dispatch(RouteExceptionEvent::SUBMIT, $event);
    }
  }

  /**
   * Get the operation that triggered the exception.
   *
   * @return string
   *   The operation that triggered the exception.
   */
  public function getOperation() {
    return $this->operation;
  }

}
