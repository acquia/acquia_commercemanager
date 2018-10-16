<?php

namespace Drupal\acm_sku\Form;

use Drupal\acm\I18nHelper;
use Drupal\acm_sku\CategoryManagerInterface;
use Drupal\acm_sku\ProductManagerInterface;
use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\Connector\IngestAPIWrapper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentEntityExampleSettingsForm.
 *
 * @package Drupal\acm_sku\Form
 *
 * @ingroup acm_sku
 */
class ProductSyncForm extends FormBase {

  /**
   * Connector Category Manager.
   *
   * @var \Drupal\acm_sku\CategoryManagerInterface
   */
  private $catManager;

  /**
   * Connector Product Manager.
   *
   * @var \Drupal\acm_sku\ProductManagerInterface
   */
  private $productManager;

  /**
   * Connector Agent API Helper.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  private $api;

  /**
   * Connector Ingest API Helper.
   *
   * @var \Drupal\acm\Connector\IngestAPIWrapper
   */
  private $ingestApi;

  /**
   * The connector config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $connectorConfig;

  /**
   * Instance of I18nHelper service.
   *
   * @var \Drupal\acm\I18nHelper
   */
  protected $i18nHelper;

  /**
   * ProductSyncForm constructor.
   *
   * @param \Drupal\acm_sku\CategoryManagerInterface $cat_manager
   *   CategoryManagerInterface instance.
   * @param \Drupal\acm_sku\ProductManagerInterface $product_manager
   *   ProductManagerInterface instance.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api
   *   APIWrapper object.
   * @param \Drupal\acm\Connector\IngestAPIWrapper $ingest_api
   *   IngestAPIWrapper object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\acm\I18nHelper $i18nHelper
   *   Used to loop over all languages and fetch store id (aka acm_uuid)
   */
  public function __construct(CategoryManagerInterface $cat_manager, ProductManagerInterface $product_manager, APIWrapperInterface $api, IngestAPIWrapper $ingest_api, ConfigFactoryInterface $config_factory, I18nHelper $i18nHelper) {
    $this->catManager = $cat_manager;
    $this->productManager = $product_manager;
    $this->api = $api;
    $this->ingestApi = $ingest_api;
    $this->connectorConfig = $config_factory->get('acm.connector');
    $this->i18nHelper = $i18nHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acm_sku.category_manager'),
      $container->get('acm_sku.product_manager'),
      $container->get('acm.api'),
      $container->get('acm.ingest_api'),
      $container->get('config.factory'),
      $container->get('acm.i18n_helper')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'acm_sku_sync';
  }

  /**
   * Define the form used for settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions']['#type'] = 'actions';

    if ($category_vid = $this->connectorConfig->get('category_vid')) {
      $form['actions']['cats'] = [
        '#type' => 'submit',
        '#value' => t('Synchronize Categories'),
      ];
    }

    $form['actions']['products_async'] = [
      '#type' => 'submit',
      '#value' => t('Synchronize Products (async)'),
    ];

    $form['actions']['products_sync'] = [
      '#type' => 'submit',
      '#value' => t('Synchronize Products (sync)'),
    ];

    return ($form);
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getUserInput()['op'];

    switch ($action) {
      case 'Synchronize Categories':
        $category_vid = $this->connectorConfig->get('category_vid');
        $this->catManager->synchronizeTree($category_vid);
        drupal_set_message('Category Synchronization Complete.', 'status');
        break;

      case 'Synchronize Products (async)':
        $this->ingestApi->productFullSync();
        drupal_set_message('Product Synchronization Processing...', 'status');
        break;

      case 'Synchronize Products (sync)':

        $drupalMessage = "";
        foreach ($this->i18nHelper->getStoreLanguageMapping() as $langcode => $store_id) {
          if (empty($store_id)) {
            continue;
          }

          // At 20180228 store_id *is* acm_uuid is enforced
          // $store_id is *NOT* sent in the query string
          // $acm_uuid is sent in the X-ACM-UUID header
          // It must only be this way:
          $acm_uuid = $store_id;
          $products = $this->api->productFullSync('', 0, $acm_uuid);
          $this->productManager->synchronizeProducts($products, $acm_uuid);
          $drupalMessage .= "Product Synchronization Complete (" . $acm_uuid . "). \n";
        }

        drupal_set_message($drupalMessage, 'status');
        break;
    }
  }

}
