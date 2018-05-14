<?php

namespace Drupal\acm_diagnostic;

/**
 * Class VerifyMapping.
 *
 * @ingroup acm_diagnostic
 */
class VerifyMapping implements VerifyMappingInterface {


  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Instance of I18nHelper service.
   *
   * @var \Drupal\acm\I18nHelper
   */
  private $i18nHelper;


  /**
   * Drupal Config Factory Instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * True if you want extra logging for debugging.
   *
   * @var bool
   */
  private $debug;

  /**
   * Construct.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\acm\I18nHelper $i18nHelper
   *   Instance of I18nHelper service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory,
    \Drupal\acm\I18nHelper $i18nHelper,
    \Drupal\Core\Config\ConfigFactoryInterface $config_factory
  ) {
    $this->logger = $logger_factory->get('acm');
    $this->i18nHelper = $i18nHelper;
    $this->debug = true;//$this->configFactory->get('acm.connector')->get('debug');
  }

  /**
   * Write to the log only if the debug flag is set true.
   *
   * @param string $message
   *   The message to write to the log.
   * @param array $context
   *   Optional array to write to the log, nominally to convey the context.
   */
  protected function debugLogger(string $message, array $context = []) {
    if ($this->debug) {
      $this->logger->debug($message, $context);
    }

  }

  public function verify($acmUuid = "") {

    $this->debugLogger("Did call 'verify' function");

    // TEST DATA FOR NOW
    $response = [
      "acm_uuid" => "anything",
      "system_api_url" => "https://example.com",
      "connector_api_url" => "https://example.com",
      "store_id" => 3,
      "store_code" => "some_store_code",
      "locale" => "us_EN",
      "base_currency" => "USD",
      "description" => "Any description at all.",
      "system_advice" => "Leave it.",
      "passed_verification" => true
    ];

    return $response;
  }

}
