<?php

namespace Drupal\acm_exception\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\acm_exception\RouteExceptionHandler;
use Drupal\acm\RouteExceptionEvent;

/**
 * Exception event subscriber for \Drupal\acm\RouteExceptionEvent.
 */
class RouteExceptionEventSubscriber implements EventSubscriberInterface {

  /**
   * Route exception handler.
   *
   * @var \Drupal\acm_exception\RouteExceptionHandler
   */
  protected $routeExceptionHandler;

  /**
   * RouteExceptionEventSubscriber constructor.
   *
   * @param \Drupal\acm_exception\RouteExceptionHandler $route_exception_handler
   *   Route exception handler.
   */
  public function __construct(RouteExceptionHandler $route_exception_handler) {
    $this->routeExceptionHandler = $route_exception_handler;
  }

  /**
   * User messaging for RouteExceptions.
   *
   * @param \Drupal\acm\RouteExceptionEvent $event
   *   The event to process.
   */
  public function onException(RouteExceptionEvent $event) {
    $exception = $event->getException();
    $this->routeExceptionHandler->message($exception);
    $this->routeExceptionHandler->log($exception);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RouteExceptionEvent::SUBMIT][] = ['onException', 50];
    return $events;
  }

}
