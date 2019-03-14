<?php

namespace Drupal\acm_sku;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\I18nHelper;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Provides a service for product options data to taxonomy synchronization.
 *
 * @ingroup acm_sku
 */
class ProductOptionsManager implements ProductOptionsManagerInterface {

  /**
   * Connector Agent Category Data API Endpoint.
   *
   * @const CONDUCTOR_API_CATEGORY
   */
  const PRODUCT_OPTIONS_VOCABULARY = 'sku_product_option';

  /**
   * Taxonomy Term Entity Storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  private $termStorage;

  /**
   * API Wrapper object.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  private $apiWrapper;

  /**
   * Instance of I18nHelper service.
   *
   * @var \Drupal\acm\I18nHelper
   */
  private $i18nHelper;

  /**
   * Instance of LoggerChannelInterface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManager object.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   ApiWrapper object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   LoggerFactory object.
   * @param \Drupal\acm\I18nHelper $i18nHelper
   *   Instance of I18nHelper service.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              APIWrapperInterface $api_wrapper,
                              LoggerChannelFactoryInterface $logger_factory,
                              I18nHelper $i18nHelper,
                              Connection $connection) {
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->apiWrapper = $api_wrapper;
    $this->logger = $logger_factory->get('acm_sku');
    $this->i18nHelper = $i18nHelper;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function loadProductOptionByOptionId($attribute_code, $option_id, $langcode, $log_error = TRUE) {
    $query = $this->termStorage->getQuery();
    $query->condition('field_sku_option_id', $option_id);
    $query->condition('field_sku_attribute_code', $attribute_code);
    $query->condition('vid', self::PRODUCT_OPTIONS_VOCABULARY);
    $tids = $query->execute();

    // We won't log no term found error during sync.
    if (count($tids) === 0) {
      if ($log_error) {
        $this->logger->error('No term found for option_id: @option_id having attribute_code @attribute_code.', [
          '@option_id' => $option_id,
          '@attribute_code' => $attribute_code,
        ]);
      }
      return NULL;
    }
    elseif (count($tids) > 1) {
      $this->logger->critical('Multiple terms found for option_id: @option_id having attribute_code @attribute_code.', [
        '@option_id' => $option_id,
        '@attribute_code' => $attribute_code,
      ]);
    }

    // We use the first term and continue even if we have multiple terms.
    $tid = array_shift($tids);

    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = $this->termStorage->load($tid);

    if ($langcode && $term->hasTranslation($langcode)) {
      $term = $term->getTranslation($langcode);
    }

    return $term;
  }

  /**
   * {@inheritdoc}
   */
  public function createProductOptionWrapper($langcode, $option_id, $option_value, $attribute_id, $attribute_code, $weight) {
    return $this->createProductOption();
  }

  /**
   * Create product option if not available or update the name.
   *
   * @param string $langcode
   *   Lang code.
   * @param int $option_id
   *   Option id.
   * @param string $option_value
   *   Value (term name).
   * @param int $attribute_id
   *   Attribute id.
   * @param string $attribute_code
   *   Attribute code.
   * @param int $weight
   *   Taxonomy term weight == attribute option sort order.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   Term object or null.
   */
  protected function createProductOption($langcode, $option_id, $option_value, $attribute_id, $attribute_code, $weight) {
    if (strlen($option_value) == 0) {
      $this->logger->warning('Got empty value while syncing production options: @data', [
        '@data' => json_encode([
          'langcode' => $langcode,
          'option_id' => $option_id,
          'attribute_id' => $attribute_id,
          'attribute_code' => $attribute_code,
        ]),
      ]);

      return NULL;
    }

    // Update the term if already available.
    if ($term = $this->loadProductOptionByOptionId($attribute_code, $option_id, NULL, FALSE)) {
      $save_term = FALSE;

      // Save term even if weight changes.
      if ($term->getWeight() != $weight) {
        $save_term = TRUE;
      }

      if ($term->hasTranslation($langcode)) {
        $term = $term->getTranslation($langcode);

        // We won't allow editing name here, if required it must be done from
        // Magento.
        if ($term->getName() != $option_value) {
          $term->setName($option_value);
          $save_term = TRUE;
        }
      }
      else {
        $term = $term->addTranslation($langcode, []);
        $term->setName($option_value);
        $save_term = TRUE;
      }

      if ($save_term) {
        $term->setWeight($weight);
        $term->save();
      }
    }
    else {
      $term = $this->termStorage->create([
        'vid' => self::PRODUCT_OPTIONS_VOCABULARY,
        'langcode' => $langcode,
        'name' => $option_value,
        'weight' => $weight,
        'field_sku_option_id' => $option_id,
        'field_sku_attribute_id' => $attribute_id,
        'field_sku_attribute_code' => $attribute_code,
      ]);
      try {
        $term->save();
      }
      catch (EntityStorageException $exception) {
        $this->logger->critical('Product option "@option" wasn\'t saved. Try again later please.', ['@option' => $option_value]);
      }
    }

    return $term;
  }

  /**
   * {@inheritdoc}
   */
  public function synchronizeProductOptions() {
    $options_available = [];

    foreach ($this->i18nHelper->getStoreLanguageMapping() as $langcode => $store_id) {
      $this->apiWrapper->updateStoreContext($store_id);
      $option_sets = $this->apiWrapper->getProductOptions();

      $weight = 0;
      foreach ($option_sets as $options) {
        foreach ($options['options'] as $key => $value) {
          $this->createProductOption($langcode, $key, $value, $options['attribute_id'], $options['attribute_code'], $weight++);
          $options_available[$options['attribute_code']][$options['attribute_id']] = $options['attribute_id'];
        }
      }
    }

    if ($options_available) {
      $this->deleteUnavailableOptions($options_available);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUnavailableOptions(array $synced_options) {
    // Cleanup queries can be done only for one language.
    $query = $this->connection->select('taxonomy_term__field_sku_attribute_code', 'ttfsac');
    $query->addExpression('count(entity_id)', 'cnt');
    $query->condition('field_sku_attribute_code_value', array_keys($synced_options), 'IN');
    $result = $query->execute()->fetchAllKeyed(0, 0);
    $options_in_db = reset($result);

    $synced_option_ids = [];
    foreach ($synced_options as $attribute_code => $options) {
      $synced_option_ids = array_merge($synced_option_ids, $options);
    }

    // Do nothing if count of option ids synced and in DB match.
    if (count($synced_option_ids) === $options_in_db) {
      return;
    }

    $query = $this->termStorage->getQuery();
    $query->condition('field_sku_option_id', $synced_option_ids, 'NOT IN');
    $query->condition('field_sku_attribute_code', array_keys($synced_options), 'IN');
    $query->condition('vid', self::PRODUCT_OPTIONS_VOCABULARY);
    $tids = $query->execute();

    if ($tids) {
      foreach (array_chunk($tids, 50) as $ids) {
        $this->termStorage->resetCache();

        try {
          $entities = $this->termStorage->loadMultiple($ids);
          $this->termStorage->delete($entities);
        }
        catch (\Exception $e) {
          $this->logger->error(t('Error occurred while deleting options that are no longer available. Error: @message', [
            '@message' => $e->getMessage(),
          ]));
        }
      }
    }
  }

}
