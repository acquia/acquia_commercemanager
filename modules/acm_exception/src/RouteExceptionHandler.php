<?php

namespace Drupal\acm_exception;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Url;
use Drupal\acm\Connector\RouteException;

/**
 * RouteExceptionResponse controller.
 */
class RouteExceptionHandler {

  /**
   * The Config wrapper for the Acquia Commerce API Exception module.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * The Logging wrapper for the Acquia Commerce API Exception module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   ConfigFactoryInterface object.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   LoggerChannelFactory object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactory $logger_factory) {
    $this->config = $config_factory->get('acm_exception.settings');
    $this->logger = $logger_factory->get('acm_exception');
  }

  /**
   * Present the error message for this RouteException to the user.
   *
   * @param \Drupal\acm\Connector\RouteException $e
   *   The RouteException that was thrown.
   */
  public function message(RouteException $e) {
    $message = $this->getConfig('message', $e);
    if (!empty($message) && PHP_SAPI !== 'cli') {
      drupal_set_message($message, 'error');
    }
  }

  /**
   * Generate a log message.
   *
   * @param \Drupal\acm\Connector\RouteException $e
   *   The RouteException that was thrown.
   */
  public function log(RouteException $e) {
    if (empty($this->getConfig('log', $e))) {
      return;
    }

    $this->logger->critical("Exception thrown during the {$e->getOperation()} operation.<br /><strong>Response code:</strong> {$e->getCode()}<br /><strong>Response body:</strong><pre>{$e->getMessage()}</pre>");
  }

  /**
   * Get the exception redirect from storage.
   *
   * @param \Drupal\acm\Connector\RouteException $e
   *   The RouteException that was thrown.
   *
   * @return string
   *   The redirect to perform.
   */
  public function getRedirect(RouteException $e) {
    $redirect = $this->getConfig('redirect', $e);
    if (empty($redirect)) {
      $redirect = '<current>';
    }
    return Url::fromUri("internal:/{$redirect}")->toString();
  }

  /**
   * Fetch config for a given RouteException.
   *
   * Looks for scenario specific config and then falls back on defaults.
   *
   * @param string $key
   *   The exception config key to fetch.
   * @param \Drupal\acm\Connector\RouteException $e
   *   The RouteException that was thrown.
   *
   * @return mixed
   *   The config on success.
   */
  private function getConfig($key, RouteException $e) {
    $scenario = $e->getOperation();
    $default = $this->config->get("default.{$key}");
    $scenario = $this->config->get("routes.{$scenario}.{$key}");
    foreach ([$scenario, $default] as $candidate) {
      if (!empty(trim($candidate))) {
        return $candidate;
      }
    }
    return NULL;
  }

}
