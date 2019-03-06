<?php

namespace Drupal\acm_sku\Commands;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\Connector\IngestAPIWrapper;
use Drupal\acm\I18nHelper;
use Drupal\acm_sku\CategoryManager;
use Drupal\acm_sku\Entity\SKU;
use Drupal\acm_sku\Plugin\rest\resource\ProductSyncResource;
use Drupal\acm_sku\ProductOptionsManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taxonomy\TermInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

/**
 * Class AcmSkuDrushCommands.
 *
 * @package Drupal\acm_sku\Commands
 */
class AcmSkuDrushCommands extends DrushCommands {

  const DELETE_BATCH_COUNT = 200;

  /**
   * Api Wrapper.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  private $apiWrapper;

  /**
   * I18n Helper.
   *
   * @var \Drupal\acm\I18nHelper
   */
  private $i18nhelper;

  /**
   * Ingest Api Wrapper.
   *
   * @var \Drupal\acm\Connector\IngestAPIWrapper
   */
  private $ingestApiWrapper;

  /**
   * ACM Category manager service.
   *
   * @var \Drupal\acm_sku\CategoryManager
   */
  private $acmCategoryManager;

  /**
   * Product options manager.
   *
   * @var \Drupal\acm_sku\ProductOptionsManager
   */
  private $productOptionsManager;

  /**
   * Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Query Factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  private $queryFactory;

  /**
   * Entity Manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  private $entityManager;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * Linked SKU cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private $linkedSkuCache;

  /**
   * Cache Tags Invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private $cacheTagsInvalidator;

  /**
   * Product Node Type - default: acm_product.
   *
   * @var string
   */
  private $productNodeType;

  /**
   * SKU Field Name - default: field_skus.
   *
   * @var string
   */
  private $skuFieldName;

  /**
   * Category Vocabulary name - default: acm_product_category.
   *
   * @var string
   */
  private $categoryVid;

  /**
   * AcmSkuDrushCommands constructor.
   *
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   API Wrapper.
   * @param \Drupal\acm\I18nHelper $i18n_helper
   *   I18n Helper.
   * @param \Drupal\acm\Connector\IngestAPIWrapper $ingest_api_wrapper
   *   Ingest API Wrapper.
   * @param \Drupal\acm_sku\CategoryManager $acm_category_manager
   *   ACM Category Manager.
   * @param \Drupal\acm_sku\ProductOptionsManager $product_options_manager
   *   Product Options Manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger Factory.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database Connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   Query Factory.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   Entity Manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language Manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module Handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $linked_sku_cache
   *   Linked SKU cache service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   Cache Tags Invalidator.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   */
  public function __construct(APIWrapperInterface $api_wrapper,
                              I18nHelper $i18n_helper,
                              IngestAPIWrapper $ingest_api_wrapper,
                              CategoryManager $acm_category_manager,
                              ProductOptionsManager $product_options_manager,
                              LoggerChannelFactoryInterface $logger_factory,
                              Connection $connection,
                              EntityTypeManagerInterface $entity_type_manager,
                              QueryFactory $query_factory,
                              EntityManagerInterface $entity_manager,
                              LanguageManagerInterface $language_manager,
                              ModuleHandlerInterface $module_handler,
                              CacheBackendInterface $linked_sku_cache,
                              CacheTagsInvalidatorInterface $cache_tags_invalidator,
                              ConfigFactoryInterface $config_factory) {
    parent::__construct();

    $this->apiWrapper = $api_wrapper;
    $this->i18nhelper = $i18n_helper;
    $this->ingestApiWrapper = $ingest_api_wrapper;
    $this->acmCategoryManager = $acm_category_manager;
    $this->productOptionsManager = $product_options_manager;
    $this->logger = $logger_factory->get('AcmSkuDrushCommands');
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->queryFactory = $query_factory;
    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->linkedSkuCache = $linked_sku_cache;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;

    $config = $config_factory->get('acm.connector');
    $this->productNodeType = $config->get('product_node_type');
    $this->skuFieldName = $config->get('sku_field_name');
    $this->categoryVid = $config->get('category_vid');
  }

  /**
   * Run a full synchronization of all commerce product records.
   *
   * @param string $langcode
   *   Sync products available in this langcode.
   * @param string $page_size
   *   Number of items to be synced in one batch.
   * @param array $options
   *   Command options.
   *
   * @command acm_sku:sync-products
   *
   * @option skus SKUs to import (like query).
   * @option category_id Magento category id to sync the products for.
   *
   * @validate-module-enabled acm_sku
   *
   * @aliases acsp,sync-commerce-products
   *
   * @usage drush acsp en 50
   *   Run a full product synchronization of all available products in store
   *   linked to en and page size 50.
   * @usage drush acsp en 50 --skus=\'M-H3495 130 2  FW\',\'M-H3496 130 004FW\',\'M-H3496 130 005FW\''
   *   Synchronize sku data for the skus M-H3495 130 2  FW, M-H3496 130 004FW
   *   & M-H3496 130 005FW only in store linked to en and page size 50.
   * @usage drush acsp en 50 --category_id=1234
   *   Synchronize sku data for the skus in category with id 1234 only in store
   *   linked to en and page size 50.
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  public function syncProducts($langcode, $page_size, array $options = ['skus' => NULL, 'category_id' => NULL]) {
    $langcode = strtolower($langcode);

    $store_id = $this->i18nhelper->getStoreIdFromLangcode($langcode);

    if (empty($store_id)) {
      $this->output->writeln(dt("Store id not found for provided language code."));
      return;
    }

    $page_size = (int) $page_size;

    if ($page_size <= 0) {
      $this->output->writeln(dt("Page size must be a positive integer."));
      return;
    }

    $skus = $options['skus'];

    $category_id = $options['category_id'];

    // Apply only one filer at a time.
    if ($category_id) {
      $skus = '';
    }

    // Ask for confirmation from user if attempt is to run full sync.
    if (empty($skus) && empty($category_id)) {
      $confirmation_text = dt('I CONFIRM');
      $input = $this->io()->ask(dt('Are you sure you want to import all products for @language language? If yes, type: "@confirmation"', [
        '@language' => $langcode,
        '@confirmation' => $confirmation_text,
      ]));

      if ($input != $confirmation_text) {
        throw new UserAbortException(dt('Please be more attentive in using this command and prove you are not sleep working...'));
      }
    }

    $this->output->writeln(dt('Requesting all commerce products for selected language code...'));
    $this->ingestApiWrapper->productFullSync($store_id, $langcode, $skus, $category_id, $page_size);
    $this->output->writeln(dt('Done.'));
  }

  /**
   * Run a full synchronization of all commerce product category records.
   *
   * @command acm_sku:sync-categories
   *
   * @validate-module-enabled acm_sku
   *
   * @aliases acsc,sync-commerce-cats
   *
   * @usage drush acsc
   *   Run a full category synchronization of all available categories.
   */
  public function syncCategories() {
    $this->output->writeln(dt('Synchronizing all commerce categories, please wait...'));
    $response = $this->acmCategoryManager->synchronizeTree($this->categoryVid);

    // We trigger delete only if there is any term update/create.
    // So if API does not return anything, we don't delete all the categories.
    if (!empty($response['created']) || !empty($response['updated'])) {
      // Get all category terms with commerce id.
      $orphan_categories = $this->acmCategoryManager->getOrphanCategories($response);

      // If there are categories to delete.
      if (!empty($orphan_categories)) {
        // Show `tid + cat name + commerce id` for review.
        $this->io()->table([
          dt('Category Id'),
          dt('Category Name'),
          dt('Category Commerce Id'),
        ], $orphan_categories);

        // Confirmation to delete old categories.
        if ($this->io()->confirm(dt('Are you sure you want to clean these old categories'), FALSE)) {

          // Allow other modules to skipping the deleting of terms.
          $this->moduleHandler->alter('acm_sku_sync_categories_delete', $orphan_categories);

          foreach ($orphan_categories as $tid => $rs) {
            $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
            if ($term instanceof TermInterface) {
              // Delete the term.
              $term->delete();
            }
          }
        }
      }
    }
    else {
      $this->logger->notice(dt('Not cleaning(deleting) old terms as there is no term update/create.'));
    }

    $this->output->writeln(dt('Done.'));
  }

  /**
   * Run a full synchronization of all commerce product options.
   *
   * @command acm_sku:sync-product-options
   *
   * @validate-module-enabled acm_sku
   *
   * @aliases acspo,sync-commerce-product-options
   */
  public function syncProductOptions() {
    $this->logger->notice(dt('Synchronizing all commerce product options, please wait...'));
    $this->productOptionsManager->synchronizeProductOptions();
    $this->logger->notice(dt('Product attribute sync completed.'));
  }

  /**
   * Run a partial synchronization of commerce product records synchronously.
   *
   * This is used for testing / dev environments.
   *
   * @param int $count
   *   Number of product records to sync.
   *
   * @command acm_sku:sync-products-test
   *
   * @validate-module-enabled acm_sku
   *
   * @aliases acdsp,sync-commerce-products-test
   *
   * @usage drush acdsp
   *   Run a partial synchronization of commerce product records synchronously
   *   for testing / dev.
   */
  public function syncProductsTest($count) {
    $this->output->writeln(dt('Synchronizing @count commerce products for testing / dev...', ['@count' => $count]));

    $container = \Drupal::getContainer();
    foreach ($this->i18nhelper->getStoreLanguageMapping() as $langcode => $store_id) {
      $this->apiWrapper->updateStoreContext($store_id);

      $products = $this->apiWrapper->getProducts($count);
      $product_sync_resource = ProductSyncResource::create($container, [], NULL, NULL);
      $product_sync_resource->post($products);
    }
  }

  /**
   * Remove all duplicate categories available in system.
   *
   * @command acm_sku:remove-category-duplicates
   *
   * @validate-module-enabled acm_sku
   *
   * @aliases acccrd,commerce-cats-remove-duplicates
   *
   * @usage drush acccrd
   *   Remove all duplicate categories available in system.
   */
  public function removeCategoryDuplicates() {
    $this->output->writeln(dt('Cleaning all commerce categories, please wait...'));

    $db = $this->connection;

    /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $query = $db->select('taxonomy_term__field_commerce_id', 'ttfci');
    $query->addField('ttfci', 'field_commerce_id_value', 'commerce_id');
    $query->groupBy('commerce_id');
    $query->having('count(*) > 1');
    $result = $query->execute()->fetchAllKeyed(0, 0);

    if (empty($result)) {
      $this->output->writeln(dt('No duplicate categories found.'));
      return;
    }

    foreach ($result as $commerce_id) {
      $this->output->writeln(dt('Duplicate categories found for commerce id: @commerce_id.', [
        '@commerce_id' => $commerce_id,
      ]));

      $query = $db->select('taxonomy_term__field_commerce_id', 'ttfci');
      $query->addField('ttfci', 'entity_id', 'tid');
      $query->condition('ttfci.field_commerce_id_value', $commerce_id);
      $query->orderBy('tid', 'DESC');
      $tids = $query->execute()->fetchAllKeyed(0, 0);

      foreach ($tids as $tid) {
        $query = $nodeStorage->getQuery();
        $query->condition('field_category', $tid);
        $nodes = $query->execute();

        if (empty($nodes)) {
          $this->output->writeln(dt('No nodes found for tid: @tid for commerce id: @commerce_id. Deleting', [
            '@commerce_id' => $commerce_id,
            '@tid' => $tid,
          ]));

          $term = $termStorage->load($tid);
          $term->delete();

          unset($tids[$tid]);

          // Break the loop if only one left now, we might not have any products
          // added yet and categories are synced which means there will be no
          // nodes for any term.
          if (count($tids) == 1) {
            break;
          }
        }
        else {
          $this->output->writeln(dt('@count nodes found for tid: @tid for commerce id: @commerce_id. Not Deleting', [
            '@commerce_id' => $commerce_id,
            '@tid' => $tid,
            '@count' => count($nodes),
          ]));
        }
      }
    }

    $this->output->writeln(dt('Done.'));
  }

  /**
   * Remove all duplicate products available in system.
   *
   * @command acm_sku:remove-product-duplicates
   *
   * @validate-module-enabled acm_sku
   *
   * @aliases accprd,commerce-products-remove-duplicates
   *
   * @usage drush accprd
   *   Remove all duplicate products available in system.
   */
  public function removeProductDuplicates() {
    $this->output->writeln(dt('Removing duplicates in commerce products, please wait...'));

    $skus_to_sync = [];

    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    $query = $this->connection->select('acm_sku_field_data', 't1');
    $query->addField('t1', 'id', 'id');
    $query->addField('t1', 'sku', 'sku');
    $query->leftJoin('acm_sku_field_data', 't2', 't1.sku = t2.sku');
    $query->where('t1.id != t2.id');
    $result = $query->execute()->fetchAllKeyed(0, 1);

    if (empty($result)) {
      $this->output->writeln(dt('No duplicate skus found.'));
    }
    else {
      $skus = [];

      foreach ($result as $id => $sku) {
        $skus[$sku][$id] = $id;
        $skus_to_sync[$sku] = $sku;
      }

      foreach ($skus as $sku => $ids) {
        $this->output->writeln(dt('Duplicate skus found for sku: @sku with ids: @ids.', [
          '@sku' => $sku,
          '@ids' => implode(', ', $ids),
        ]));

        // Always delete the one with higher id, first one will have more
        // translations.
        sort($ids);

        // Remove the first id which we don't want to delete.
        array_shift($ids);

        foreach ($ids as $id) {
          $this->output->writeln(dt('Deleting sku with id @id for sku @sku.', [
            '@sku' => $sku,
            '@id' => $id,
          ]));

          $sku_entity = SKU::load($id);
          $sku_entity->delete();
        }
      }
    }

    $query = $this->connection->select('node__' . $this->skuFieldName, 't1');
    $query->addField('t1', 'entity_id', 'id');
    $query->addField('t1', $this->skuFieldName . '_value', 'sku');
    $query->leftJoin('node__' . $this->skuFieldName, 't2', 't1.' . $this->skuFieldName . '_value = t2.' . $this->skuFieldName . '_value');
    $query->where('t1.entity_id != t2.entity_id');
    $result = $query->execute()->fetchAllKeyed(0, 1);

    if (empty($result)) {
      $this->output->writeln(dt('No duplicate product nodes found.'));
    }
    else {
      $nids_to_delete = [];
      $skus = [];

      foreach ($result as $id => $sku) {
        $skus[$sku][$id] = $id;
        $skus_to_sync[$sku] = $sku;
      }

      foreach ($skus as $sku => $ids) {
        $this->output->writeln(dt('Duplicate nodes found for sku: @sku with ids: @ids.', [
          '@sku' => $sku,
          '@ids' => implode(', ', $ids),
        ]));

        // Always delete the one with higher nid, first one will have proper
        // url alias.
        sort($ids);

        // Remove the first id which we don't want to delete.
        array_shift($ids);

        foreach ($ids as $id) {
          $this->output->writeln(dt('Deleting node with id @id for sku @sku.', [
            '@sku' => $sku,
            '@id' => $id,
          ]));

          $nids_to_delete[$id] = $id;
        }
      }

      if ($nids_to_delete) {
        $nodeStorage->delete($nodeStorage->loadMultiple($nids_to_delete));
      }
    }

    if ($skus_to_sync) {
      $sku_texts = implode(',', $skus_to_sync);

      $this->output->writeln(dt('Requesting resync for skus @skus.', [
        '@skus' => $sku_texts,
      ]));

      foreach ($this->i18nhelper->getStoreLanguageMapping() as $langcode => $store_id) {
        // Using very small page size to avoid any issues for skus which already
        // had corrupt data.
        $this->ingestApiWrapper->productFullSync($store_id, $langcode, $sku_texts, NULL, 5);
      }
    }

    $this->output->writeln(dt('Done.'));
  }

  /**
   * Flush all commerce data from the site.
   *
   * Handles Products, SKUs, Product Categories and Product Options and allows
   * more data to be added for cleanup via alter hook.
   *
   * @command acm_sku:flush-synced-data
   *
   * @validate-module-enabled acm_sku
   *
   * @aliases accd,clean-synced-data
   *
   * @usage drush accd
   *   Flush all commerce data from the site (Products, SKUs, Product Categories
   *   and Product Options).
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  public function flushSyncedData() {
    if (!$this->io()->confirm(dt("Are you sure you want to clean commerce data?"))) {
      throw new UserAbortException();
    }
    $this->output->writeln(dt('Cleaning synced commerce data, please wait...'));

    // Set batch operation.
    $batch = [
      'title' => t('Clean synced data'),
      'init_message' => t('Cleaning synced commerce data starting...'),
      'operations' => [
        ['\Drupal\acm_sku\Commands\AcmSkuDrushCommands::skuCleanProcess', []],
      ],
      'progress_message' => t('Processed @current out of @total.'),
      'error_message' => t('Synced data could not be cleaned because an error occurred.'),
      'finished' => '_acm_sku_clean_finished',
    ];

    batch_set($batch);
    drush_backend_batch_process();
    $this->output->writeln(dt('Synced commerce data cleaned.'));
  }

  /**
   * Function to process entity delete operation.
   *
   * @param mixed|array $context
   *   The batch current context.
   */
  public static function skuCleanProcess(&$context) {
    // Use the $context['sandbox'] at your convenience to store the
    // information needed to track progression between successive calls.
    if (empty($context['sandbox'])) {
      $config = \Drupal::config('acm.connector');

      // Get all the entities that need to be deleted.
      $context['sandbox']['results'] = [];

      // Get all product entities.
      $query = \Drupal::entityQuery('node');
      $query->condition('type', $config->get('product_node_type'));
      $product_entities = $query->execute();
      foreach ($product_entities as $entity_id) {
        $context['sandbox']['results'][] = [
          'type' => 'node',
          'entity_id' => $entity_id,
        ];
      }

      // Get all acm_sku entities.
      $query = \Drupal::entityQuery('acm_sku');
      $sku_entities = $query->execute();
      foreach ($sku_entities as $entity_id) {
        $context['sandbox']['results'][] = [
          'type' => 'acm_sku',
          'entity_id' => $entity_id,
        ];
      }

      // Get all taxonomy_term entities.
      $categories = [$config->get('category_vid'), 'sku_product_option'];
      $query = \Drupal::entityQuery('taxonomy_term');
      $query->condition('vid', $categories, 'IN');
      $cat_entities = $query->execute();
      foreach ($cat_entities as $entity_id) {
        $context['sandbox']['results'][] = [
          'type' => 'taxonomy_term',
          'entity_id' => $entity_id,
        ];
      }

      // Allow other modules to add data to be deleted when cleaning up.
      \Drupal::moduleHandler()->alter('acm_sku_clean_synced_data', $context);

      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = 0;
      $context['sandbox']['max'] = count($context['sandbox']['results']);
    }

    $results = [];
    if (isset($context['sandbox']['results']) && !empty($context['sandbox']['results'])) {
      $results = $context['sandbox']['results'];
    }

    $results = array_slice($results, isset($context['sandbox']['current']) ? $context['sandbox']['current'] : 0, self::DELETE_BATCH_COUNT);

    $delete = [];

    foreach ($results as $key => $result) {
      $context['results'][] = $results['type'] . ' : ' . $result['entity_id'];
      $context['sandbox']['progress']++;
      $context['sandbox']['current_id'] = $result['entity_id'];

      $delete[$result['type']][] = $result['entity_id'];

      // Update our progress information.
      $context['sandbox']['current']++;
    }

    foreach ($delete as $type => $entity_ids) {
      try {
        $storage = \Drupal::entityTypeManager()->getStorage($type);
        $entities = $storage->loadMultiple($entity_ids);
        $storage->delete($entities);
      }
      catch (\Exception $e) {
        \Drupal::logger('AcmSkuDrushCommands')->error($e->getMessage());
      }
    }

    $context['message'] = 'Processed ' . $context['sandbox']['progress'] . ' out of ' . $context['sandbox']['max'] . '.';

    if ($context['sandbox']['progress'] !== $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Clear linked SKUs cache.
   *
   * @param array $options
   *   Command Options.
   *
   * @command acm_sku:clear-linked-skus-cache
   *
   * @option sku SKU to clean linked skus cache of.
   *
   * @validate-module-enabled acm_sku
   *
   * @aliases acclsc,clear-linked-skus-cache
   *
   * @usage drush acclsc
   *   Clear linked SKUs cache for all SKUs.
   * @usage drush acclsc --skus=SKU
   *   Clear linked SKUs cache for particular SKU.
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  public function flushLinkedSkuCache(array $options = ['sku' => NULL]) {
    // Check if we are asked to clear cache of specific SKU.
    if (!empty($options['sku'])) {
      if ($sku_entity = SKU::loadFromSku($options['sku'])) {
        $this->cacheTagsInvalidator->invalidateTags([
          'acm_sku:linked_skus:' . $sku_entity->id(),
          'acm_sku:' . $sku_entity->id(),
        ]);

        $this->output->writeln(dt('Invalidated linked SKUs cache for @sku.', [
          '@sku' => $options['sku'],
        ]));
      }

      return;
    }

    if (!$this->io()->confirm(dt('Are you sure you want to clear linked SKUs cache for all SKUs?'))) {
      throw new UserAbortException();
    }

    $this->linkedSkuCache->deleteAll();

    $this->output->writeln(dt('Cleared all linked SKUs cache.'));
  }

}
