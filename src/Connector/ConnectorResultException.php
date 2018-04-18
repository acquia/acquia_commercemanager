<?php

namespace Drupal\acm\Connector;

/**
 * Class ConnectorResultException.
 *
 * @package Drupal\acm\Connector
 *
 * @ingroup acm
 */
class ConnectorResultException extends ConnectorException {

  /**
   * Connector Reported Success.
   *
   * @var bool
   */
  protected $success;

  /**
   * Connector Results / Messages.
   *
   * @var array
   */
  protected $failures = [];

  /**
   * Constructor.
   *
   * @param array $result
   *   Connector Result Data.
   */
  public function __construct(array $result) {

    $this->success = (isset($result['success'])) ? (bool) $result['success'] : FALSE;

    foreach ($result as $key => $mesg) {
      if ($key === 'success' || is_array($mesg)) {
        continue;
      }

      // Look for the initial json response from commerce backend to reuse its
      // failure messages.
      $prefix = 'response:';
      if ($position = strpos($mesg, $prefix)) {
        $responseString = substr($mesg, strpos($mesg, $prefix) + strlen($prefix));
        $response = json_decode($responseString, TRUE);
        if (is_array($response) && isset($response['message'])) {
          $mesg = $response['message'];

          // Case of failure messages with variables.
          if (isset($response['parameters'])) {
            foreach ($response['parameters'] as $name => $value) {
              $mesg = str_replace("%$name", $value, $mesg);
            }
          }
        }
      }

      $this->failures[$key] = $mesg;
    }

    if ($this->success) {
      $mesg = 'Connector request successful but did not contain requested data.';
    }
    else {
      // Generic exception message.
      $mesg = 'Connector request unsuccessful.';

      // Check if we have a better exception message in failures.
      if ($this->failures) {
        // We return the first failure message in getMessage(), rest can be
        // accessed via getFailureMessage().
        $mesg = array_shift($this->failures);
      }
    }

    return parent::__construct($mesg);
  }

  /**
   * IsConnectorSuccessful.
   *
   * If Connector reported success on this request (this may happen if
   * success if reported but the result does not contain an expected key).
   *
   * @return bool
   *   If Connector reported success on this request.
   */
  public function isConnectorSuccessful() {
    return $this->success;
  }

  /**
   * GetFailureMessages.
   *
   * Get any failure messages returned with the Connector request, generally
   * keyed by the name of the delegator that generated them.
   *
   * @return string[]
   *   Array with failures.
   */
  public function getFailureMessages() {
    return $this->failures;
  }

  /**
   * GetFailureMessage.
   *
   * Get a specific failure message (or a generic) returned with the
   * Connector request. Key will generally be a specific Connector
   * delegator that generated the error.
   *
   * @param string $key
   *   Connector delegator identifier.
   *
   * @return string
   *   Specific message for $key.
   */
  public function getFailureMessage($key) {
    if (isset($this->failures[$key])) {
      return $this->failures[$key];
    }
    else {
      return sprintf('No message returned from %s.', $key);
    }
  }

}
