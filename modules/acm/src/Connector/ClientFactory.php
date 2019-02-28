<?php

namespace Drupal\acm\Connector;

use Drupal\acm\I18nHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory as DrupalClientFactory;
use Acquia\Hmac\Guzzle\HmacAuthMiddleware;
use Acquia\Hmac\Key;
use GuzzleHttp\HandlerStack;

/**
 * Class ClientFactory.
 *
 * @package Drupal\acm\Connector
 *
 * @ingroup acm
 */
class ClientFactory {

  /**
   * Guzzle HttpClient Factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  private $clientFactory;

  /**
   * Drupal Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * I18nHelper.
   *
   * @var \Drupal\acm\I18nHelper
   */
  private $i18nHelper;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $clientFactory
   *   ClientFactory object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   ConfigFactory object.
   * @param \Drupal\acm\I18nHelper $i18nHelper
   *   I18nHelper object.
   */
  public function __construct(DrupalClientFactory $clientFactory, ConfigFactoryInterface $configFactory, I18nHelper $i18nHelper) {

    $this->clientFactory = $clientFactory;
    $this->configFactory = $configFactory;
    $this->i18nHelper = $i18nHelper;
  }

  /**
   * CreateAgentClient.
   *
   * Create a Guzzle http client configured to connect to the
   * Connector instance from the site configuration.
   *
   * @return \GuzzleHttp\Client
   *   Object of initialized client.
   *
   * @throws \InvalidArgumentException
   */
  public function createAgentClient() {

    $config = $this->configFactory->get('acm.connector');
    if (!strlen($config->get('url_agent'))) {
      throw new \InvalidArgumentException('No Commerce Connector Service URL specified.');
    }

    $clientConfig = [
      'base_uri' => $config->get('url_agent'),
      'timeout'  => (int) $config->get('timeout'),
      'verify'   => (bool) $config->get('verify_ssl'),
    ];

    return $this->clientFactory->fromOptions($clientConfig);
  }

  /**
   * CreateIngestClient.
   *
   * Create a Guzzle http client configured to connect to the
   * Connector Ingest (async) instance from the site configuration.
   *
   * @return \GuzzleHttp\Client
   *   Object of initialized client.
   *
   * @throws \InvalidArgumentException
   */
  public function createIngestClient() {

    $config = $this->configFactory->get('acm.connector');
    if (!strlen($config->get('url_ingest'))) {
      throw new \InvalidArgumentException('No Commerce Connector Service URL specified.');
    }

    $clientConfig = [
      'base_uri' => $config->get('url_ingest'),
      'timeout'  => (int) $config->get('timeout'),
      'verify'   => (bool) $config->get('verify_ssl'),
    ];

    return $this->clientFactory->fromOptions($clientConfig);
  }

  /**
   * CreateClient.
   *
   * Create a Guzzle http client configured to connect to the
   * Connector instance from the site configuration.
   *
   * @return \GuzzleHttp\Client
   *   Object of initialized client.
   *
   * @throws \InvalidArgumentException
   */
  public function createClient($acm_uuid = "") {

    $config = $this->configFactory->get('acm.connector');
    if (!strlen($config->get('url'))) {
      throw new \InvalidArgumentException('No Commerce Connector Service URL specified.');
    }

    // Create key and middleware.
    $key = new Key($config->get('hmac_id'), base64_encode($config->get('hmac_secret')));
    $middleware = new HmacAuthMiddleware($key);
    // Register the middleware.
    $stack = HandlerStack::create();
    $stack->push($middleware);

    if (!$acm_uuid) {
      $acm_uuid = $this->i18nHelper->getStoreIdFromLangcode();
    }

    $clientConfig = [
      'base_uri' => $config->get('url'),
      'timeout'  => (int) $config->get('timeout'),
      'verify'   => (bool) $config->get('verify_ssl'),
      'handler'  => $stack,
      'headers' => [
        'X-ACM-UUID' => $acm_uuid,
      ],
      'http_errors' => FALSE,
    ];

    return $this->clientFactory->fromOptions($clientConfig);
  }

}
