<?php

namespace Drupal\acm\Connector;

/**
 * Class CustomerNotFoundException.
 *
 * @package Drupal\acm\Connector
 *
 * @ingroup acm
 */
class CustomerNotFoundException extends ConnectorException {

  const CUSTOMER_NOT_FOUND_CODE = 32;
  const CUSTOMER_NOT_FOUND_MESSAGE = "Customer not found";

}
