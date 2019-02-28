<?php

namespace Drupal\acm;

use Symfony\Component\EventDispatcher\Event;
use Drupal\acm\Connector\RouteException;

/**
 * Class UpdateCartErrorEvent.
 *
 * @package Drupal\acm
 */
class RouteExceptionEvent extends Event {

  const SUBMIT = 'acm.connector.route_exception';

  /**
   * The PHP exception we throw from the API wrapper.
   *
   * @var \Exception
   */
  protected $exception;

  /**
   * {@inheritdoc}
   */
  public function __construct(RouteException $exception) {
    $this->exception = $exception;
  }

  /**
   * Get The exception.
   *
   * @return \Exception
   *   Exception object which contains code and message.
   */
  public function getException() {
    return $this->exception;
  }

}
