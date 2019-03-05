<?php

namespace Drupal\acm_exception\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\acm\RouteExceptionEvent;

/**
 * Exception event subscriber for \Drupal\acm\RouteExceptionEvent.
 */
class RouteExceptionEventSubscriber implements EventSubscriberInterface {

  /**
   * User messaging for RouteExceptions.
   *
   * @param \Drupal\acm\RouteExceptionEvent $event
   *   The event to process.
   */
  public function onException(RouteExceptionEvent $event) {
    $exception = $event->getException();
    $handler = \Drupal::service('acm_exception.route_exception_handler');
    $handler->message($exception);
    $handler->log($exception);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RouteExceptionEvent::SUBMIT][] = ['onException', 50];
    return $events;
  }

}
