<?php

namespace Drupal\acm_diagnostic;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\acm\I18nHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;

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
   * Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

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
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *  Language Manager service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    I18nHelper $i18nHelper,
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $languageManager
  ) {
    $this->logger = $logger_factory->get('acm');
    $this->i18nHelper = $i18nHelper;
    $this->configFactory = $config_factory;
    $this->languageManager = $languageManager;
    $this->debug = $this->configFactory->get('acm.connector')->get('debug');
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

  /**
   * Verifies a mapping by inspecting various configurations and
   * sending a request out to the Commerce Connector
   *
   * @param string $acmUuid
   *   The ACM_UUID of the configuration to verify
   *
   * @return array
   */
  public function verify($acmUuid = "") {

    $this->debugLogger("Did call 'verify' function");
    $systemAdvice = "";

    $response = [];
    $response['acm_uuid'] = $acmUuid;

    $response['locale'] = $this->i18nHelper->getLangcodeFromStoreId($acmUuid);

    $locale = $this->languageManager->getCurrentLanguage()->getId();
    $systemAdvice .= "The current language of this endpoint is: ";
    $systemAdvice .= $locale.".";
    $acm_uuid = $this->i18nHelper->getStoreIdFromLangcode();

    if ($acm_uuid !== $response['acm_uuid']) {
      $systemAdvice .= "\nThe ACM_UUID associated with the language of the";
      $systemAdvice .= " Drupal URL, '".$acm_uuid."', does not match the";
      $systemAdvice .= " ACM_UUID of the header";
      $systemAdvice .= " sent from Commerce Middleware, ";
      $systemAdvice .= $response['acm_uuid'].".";
    }

    if ($locale !== $response['locale']){
      $systemAdvice .= "\nThe language of the";
      $systemAdvice .= " Drupal URL, '".$locale."', does not match the";
      $systemAdvice .= " language defined by the ACM_UUID";
      $systemAdvice .= " sent from Commerce Middleware, ";
      $systemAdvice .= $response['acm_uuid'].".";
    }

    // This URL.
    // urlGenerator is a service. consider:
    // getContainer()->get('url_generator')
    $response['system_api_url'] = \Drupal::urlGenerator()->generateFromRoute('<front>', [], ['absolute' => TRUE]);

    // Connector URL.
    $configAcmConnector = $this->configFactory->get('acm.connector');
    $response['connector_api_url'] = $configAcmConnector->get('url');

    // Deeper (considered sensitive information. do no send).
    //$configAcmConnector->get('hmac_id');

    // Base currency is meaningless, unfortunately.
    // But we can fetch the symbol from the config...
    $configAcmCurrency = $this->configFactory->get('acm.currency');
    // Get currency with the correct locale.
    $currency = \Drupal::service('repository.currency')->get($configAcmCurrency->get('currency_code'), $locale, 'en');
    $response['base_currency'] = $currency->getCurrencyCode()." (".$currency->getName().")";

    // Description (site description in locale?)
    $configSystemSite = $this->configFactory->get('system.site');

    // Translations? Is the config locale aware?
    $description  = $configSystemSite->get('name');
    $description .= " (".$configSystemSite->get('slogan').").";
    $response['description'] = $description;

    // TODO (malachy): Add in the loop back to test connection to the commerce connector
    // Ping the middleware: can we connect?
    $systemAdvice .= "\nThe connection back to the middle was not tested";
    $response['passed_verification'] = true;

    $response['system_advice'] = $systemAdvice;

    return $response;
  }

}
