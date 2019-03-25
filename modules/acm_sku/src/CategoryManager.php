<?php

namespace Drupal\acm_sku;

use Drupal\acm\Connector\APIWrapper;
use Drupal\acm\I18nHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\acm\Connector\ClientFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides a service for category data to taxonomy synchronization.
 *
 * @ingroup acm_sku
 */
class CategoryManager implements CategoryManagerInterface {

  /**
   * Taxonomy Term Entity Storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  private $termStorage;

  /**
   * Taxonomy Vocabulary Entity Storage.
   *
   * @var \Drupal\taxonomy\VocabularyStorageInterface
   */
  private $vocabStorage;

  /**
   * Taxonomy Vocabulary Entity to Sync.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  private $vocabulary;

  /**
   * Result (create / update / failed) counts.
   *
   * @var array
   */
  private $results;

  /**
   * API Wrapper object.
   *
   * @var \Drupal\acm\Connector\APIWrapper
   */
  private $apiWrapper;

  /**
   * Instance of I18nHelper service.
   *
   * @var \Drupal\acm\I18nHelper
   */
  private $i18nHelper;

  /**
   * Instance of logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Module Handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $modulehandler;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManager object.
   * @param \Drupal\acm\Connector\ClientFactory $client_factory
   *   ClientFactory object.
   * @param \Drupal\acm\Connector\APIWrapper $api_wrapper
   *   API Wrapper object.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   LoggerFactory object.
   * @param \Drupal\acm\I18nHelper $i18nHelper
   *   Instance of I18nHelper service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              ClientFactory $client_factory,
                              APIWrapper $api_wrapper,
                              LoggerChannelFactory $logger_factory,
                              I18nHelper $i18nHelper,
                              ModuleHandlerInterface $moduleHandler,
                              Connection $connection) {
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->vocabStorage = $entity_type_manager->getStorage('taxonomy_vocabulary');
    $this->clientFactory = $client_factory;
    $this->apiWrapper = $api_wrapper;
    $this->logger = $logger_factory->get('acm_sku');
    $this->i18nHelper = $i18nHelper;
    $this->modulehandler = $moduleHandler;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function synchronizeTree($vocabulary, $remoteRoot = NULL) {
    $this->resetResults();
    $this->loadVocabulary($vocabulary);

    $config = \Drupal::config('acm.connector');
    $debug = $config->get('debug');
    $debug_dir = $config->get('debug_dir');

    foreach ($this->i18nHelper->getStoreLanguageMapping() as $langcode => $store_id) {
      if ($store_id) {
        // Load Connector Category data.
        $categories[] = $this->loadCategoryData($store_id);

        if ($debug && !empty($debug_dir)) {
          // Export category data into file.
          $filename = $debug_dir . '/categories_' . $langcode . '.data';
          $fp = fopen($filename, 'w');
          fwrite($fp, var_export($categories, 1));
          fclose($fp);
        }

        // Recurse the category tree and create / update nodes.
        $this->syncCategory($categories, NULL, $store_id);
      }
    }

    return ($this->results);
  }

  /**
   * Synchronize categories in offline mode, i.e. not connected to connector.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param array $categories
   *   Category tree to import.
   * @param string $storeId
   *   Store ID.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   *   If the data is read-only.
   *
   * @return array
   *   Array summarising updates.
   */
  public function synchronizeTreeOffline($vocabulary, array $categories, $storeId = '') {
    $this->resetResults();
    $this->loadVocabulary($vocabulary);

    // Recurse the category tree and create / update nodes.
    $this->syncCategory($categories, NULL, $storeId);

    return ($this->results);
  }

  /**
   * {@inheritdoc}
   */
  public function synchronizeCategory($vocabulary, array $categories, $storeId = '') {
    $this->resetResults();
    $this->loadVocabulary($vocabulary);

    // Load parent of current category.
    $parent = 0;
    $query = $this->termStorage->getQuery()
      ->condition('field_commerce_id', $categories['parent_id'])
      ->condition('vid', $this->vocabulary->id());
    $tids = $query->execute();

    // If any found, load object of Term.
    if (count($tids)) {
      $tid = array_shift($tids);
      $parent = $this->termStorage->load($tid);
      $parent = ($parent && $parent->id()) ? $parent : 0;
    }

    // Recurse the category tree and create / update nodes.
    $this->syncCategory([$categories], $parent, $storeId);

    return ($this->results);
  }

  /**
   * LoadCategoryData.
   *
   * Load the commerce backend category data from Connector.
   *
   * @param int $store_id
   *   Store id for which we should get categories.
   *
   * @return array
   *   Array of categories.
   */
  private function loadCategoryData($store_id) {
    $this->apiWrapper->updateStoreContext($store_id);
    return $this->apiWrapper->getCategories();
  }

  /**
   * LoadVocabulary.
   *
   * Load a taxonomy vocabulary from a vid.
   *
   * @param string $vocabulary
   *   Vocabulary VID.
   */
  private function loadVocabulary($vocabulary) {
    if (!strlen($vocabulary)) {
      throw new \InvalidArgumentException('CategoryManager requires a taxonomy vocabulary machine name.');
    }

    $vocab = $this->vocabStorage->load($vocabulary);

    if (!$vocab || !$vocab->id()) {
      throw new \InvalidArgumentException(sprintf(
        'CategoryManager unable to locate vocabulary %s.',
        $vocabulary
      ));
    }

    $this->vocabulary = $vocab;

  }

  /**
   * ResetResults.
   *
   * Reset the results counters.
   */
  private function resetResults() {
    $this->results = [
      'created' => [],
      'updated' => [],
      'failed' => [],
    ];
  }

  /**
   * SyncCategory.
   *
   * Recursive category synchronization and saving.
   *
   * @param array $categories
   *   Children Categories.
   * @param array|null $parent
   *   Parent Category.
   * @param string $storeId
   *   Store ID.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   *   If the data is read-only.
   */
  private function syncCategory(array $categories, $parent = NULL, $storeId = '') {
    /** @var \Drupal\Core\Lock\PersistentDatabaseLockBackend $lock */
    $lock = \Drupal::service('lock.persistent');

    // Remove top level item (Default Category) from the categories, if its set
    // in configuration and category is with no parent.
    $filter_root_category = \Drupal::config('acm.connector')
      ->get('filter_root_category');
    if ($filter_root_category && $parent === NULL) {
      $categories = $categories[0]['children'];
    }

    // Get langcode for v2 connector.
    $langcode = '';
    if (!empty($storeId)) {
      $langcode = $this->i18nHelper->getLangcodeFromStoreId($storeId);
    }

    foreach ($categories as $category) {
      if (empty($category['category_id']) || empty($category['name'])) {
        $this->logger->error('Invalid / missing category ID or name.');
        $this->results['failed'][] = $category['category_id'] ?? '-1';
        continue;
      }

      // For v1 connector we are going to get store_id from each product,
      // because we are not sending X-ACM-UUID header.
      if (empty($langcode)) {
        $langcode = $this->i18nHelper->getLangcodeFromStoreId($category['store_id']);
      }

      // If langcode is still empty at this point, we probably don't support
      // this store. This is because we are sending all data for all stores.
      if (empty($langcode)) {
        continue;
      }

      $lock_key = 'syncCategory' . $category['category_id'];
      // Acquire lock to ensure parallel processes are executed one by one.
      do {
        $lock_acquired = $lock->acquire($lock_key);
        // Sleep for half a second before trying again.
        if (!$lock_acquired) {
          usleep(500000);
        }
      } while (!$lock_acquired);

      // Always initialise $parent_data (especially after recursion)
      // (Forces only one parent allowed per category branch)
      $parent_data = [];
      $parent_data[] = ($parent) ? $parent->id() : 0;

      $position = (isset($category['position'])) ? (int) $category['position'] : 1;

      // Load existing term (if found).
      $query = $this->termStorage->getQuery();
      $group = $query->andConditionGroup()
        ->condition('field_commerce_id', $category['category_id'])
        ->condition('vid', $this->vocabulary->id());
      $query->condition($group);

      $tids = $query->execute();

      if (count($tids) > 1) {
        $this->logger->error('Multiple terms found for category id @cid', ['@cid' => $category['category_id']]);
      }

      // Always use the first term and continue.
      if (count($tids) > 0) {
        $this->logger->info('Updating category term @name [@id]', [
          '@name' => $category['name'],
          '@id' => $category['category_id'],
        ]);

        // Load and update the term entity.
        /** @var \Drupal\taxonomy\Entity\Term $term */
        $term = $this->termStorage->load(array_shift($tids));

        if (!$term->hasTranslation($langcode)) {
          $term = $term->addTranslation($langcode);

          // We doing this because when the translation of node is created by
          // addTranslation(), pathauto alias is not created for the translated
          // version.
          // @see https://www.drupal.org/project/pathauto/issues/2995829.
          if ($this->modulehandler->moduleExists('pathauto')) {
            $term->path->pathauto = 1;
          }

          $term->get('field_commerce_id')->setValue($category['category_id']);
        }
        else {
          $term = $term->getTranslation($langcode);
        }

        $term->setName($category['name']);
        $term->parent = $parent_data;
        $term->weight->value = $position;

        // Break child relationships.
        $children = $this->termStorage->loadChildren($term->id(), $this->vocabulary->id());
        if (count($children)) {
          $child_ids = array_map(function ($child) use ($category) {
            // If term having commerce id, means its sync from magento and
            // thus we process. Term not having commerce id means its created
            // only on Drupal and thus we skip processing.
            if ($commerce_id = $child->get('field_commerce_id')->first()) {
              // We check if the child exists in the response get from magento.
              foreach ($category['children'] as $sync_cat_child) {
                if ($commerce_id->getString() == $sync_cat_child['category_id']) {
                  return $child->id();
                }
              }
            }
          }, $children);

          $this->termStorage->deleteTermHierarchy($child_ids);
        }

        $this->results['updated'][] = $category['category_id'];

      }
      else {
        // Create the term entity.
        $this->logger->info('Creating category term @name [@id]',
          ['@name' => $category['name'], '@id' => $category['category_id']]
        );

        $term = $this->termStorage->create([
          'vid' => $this->vocabulary->id(),
          'name' => $category['name'],
          'field_commerce_id' => $category['category_id'],
          'parent' => $parent_data,
          'weight' => $position,
          'langcode' => $langcode,
        ]);

        $this->results['created'][] = $category['category_id'];
      }

      // Store status of category.
      $term->get('field_commerce_status')->setValue((int) $category['is_active']);

      $term->get('field_category_include_menu')->setValue($category['in_menu']);
      $term->get('description')->setValue($category['description']);

      // @TODO: Refactor \Drupal::config into DI.
      $config = \Drupal::config('acm.connector');
      $text_format = $config->get('text_format') ?: 'rich_text';
      $term->setFormat($text_format);

      // Invoke the alter hook to allow all modules to update the term.
      \Drupal::moduleHandler()->alter('acq_sku_commerce_category', $term, $category, $parent);

      try {
        $term->save();
        // Release the lock.
        $lock->release($lock_key);
        $lock_key = NULL;
      }
      catch (\Exception $e) {
        $this->logger->warning('Failed saving category term @name (ID @id)',
          ['@name' => $category['name'], '@id' => $category['category_id']]
        );
        // Release the lock.
        $lock->release($lock_key);
        $lock_key = NULL;
        continue;
      }

      // Recurse to children categories.
      $childCats = (isset($category['children'])) ? $category['children'] : [];
      $this->syncCategory($childCats, $term, $storeId);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOrphanCategories(array $sync_categories) {
    // Get all category terms with commerce id.
    $query = $this->connection->select('taxonomy_term_field_data', 'ttd');
    $query->fields('ttd', ['tid', 'name']);
    $query->leftJoin('taxonomy_term__field_commerce_id', 'tcid', 'ttd.tid=tcid.entity_id');
    $query->fields('tcid', ['field_commerce_id_value']);
    $query->condition('ttd.vid', 'acq_product_category');
    $result = $query->execute()->fetchAllAssoc('tid', \PDO::FETCH_ASSOC);

    $affected_terms = array_unique(array_merge($sync_categories['created'], $sync_categories['updated']));
    // Filter terms which are not in sync response.
    return $result = array_filter($result, function ($val) use ($affected_terms) {
      return !in_array($val['field_commerce_id_value'], $affected_terms);
    });
  }

}
