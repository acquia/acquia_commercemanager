<?php

namespace Drupal\acm\Plugin\CommerceDashboardItem;

use Drupal\acm\CommerceDashboardItemBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StoreConfigurationStatus.
 *
 * @CommerceDashboardItem(
 *   id = "store_configuration_status",
 *   title = @Translation("Store configuration"),
 *   weight = -200,
 *   group = "tile",
 * )
 */
class StoreConfigurationStatus extends CommerceDashboardItemBase {

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];
    $header = [
      'language' => $this->t('Language'),
      'store_id' => $this->t('ACM UUID'),
      'currency' => $this->t('Currency code'),
    ];

    $languages = $this->languageManager->getLanguages();

    // Prepare the alternate locale data.
    foreach ($languages as $lang => $language) {
      // For default language, we access the config directly.
      if ($lang == $this->languageManager->getDefaultLanguage()->getId()) {
        $storeConfig = $this->configFactory->get('acm.store');
        $currencyConfig = $this->configFactory->get('acm.currency');
      }
      // We get store id from translated config for other languages.
      else {
        $storeConfig = $this->languageManager->getLanguageConfigOverride($lang, 'acm.store');
        $currencyConfig = $this->languageManager->getLanguageConfigOverride($lang, 'acm.currency');
      }

      $rows[] = [
        'language' => $language->getName(),
        'store_id' => $storeConfig->get('store_id') ?? 'N/A',
        'currency' => $currencyConfig->get('currency_code') ?? 'N/A',
      ];
    }
    return [
      '#theme' => "dashboard_item__" . $this->pluginDefinition['group'],
      '#title' => $this->title(),
      '#value' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ],
    ];
  }

}
