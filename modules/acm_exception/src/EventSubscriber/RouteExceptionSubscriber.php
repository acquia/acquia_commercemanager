<?php

namespace Drupal\acm_exception\EventSubscriber;

use Drupal\acm_exception\RouteExceptionHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\acm\Connector\RouteException;

/**
 * Exception event subscriber for Drupal\acm\Connector\RouteException.
 */
class RouteExceptionSubscriber implements EventSubscriberInterface {

  /**
   * Route exception handler.
   *
   * @var \Drupal\acm_exception\RouteExceptionHandler
   */
  protected $routeExceptionHandler;

  /**
   * RouteExceptionSubscriber constructor.
   *
   * @param \Drupal\acm_exception\RouteExceptionHandler $route_exception_handler
   *   Route exception handler.
   */
  public function __construct(RouteExceptionHandler $route_exception_handler) {
    $this->routeExceptionHandler = $route_exception_handler;
  }

  /**
   * Catch all uncaught RouteException exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The event to process.
   */
  public function onException(GetResponseForExceptionEvent $event) {
    $exception = $event->getException();
    if (!($exception instanceof RouteException) || PHP_SAPI === 'cli') {
      return;
    }

    $redirect = $this->routeExceptionHandler->getRedirect($exception);
    $event->setResponse(RedirectResponse::create($redirect, 302));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['onException', 50];
    return $events;
  }

}
