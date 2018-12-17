<?php

namespace Drupal\acm_sku;

use Drupal\acm\I18nHelper;
use Drupal\acm_sku\Entity\SKU;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\acm_sku\Event\AcmSkuValidateEvent;

/**
 * Class ProductManager.
 *
 * @ingroup acm_sku
 */
class ProductManager implements ProductManagerInterface {

  /**
   * Drupal Entity Type Manager Instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityManager;

  /**
   * Drupal Config Factory Instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Category Repository.
   *
   * @var \Drupal\acm_sku\CategoryRepositoryInterface
   */
  private $categoryRepo;

  /**
   * Product Options Manager service instance.
   *
   * @var \Drupal\acm_sku\ProductOptionsManager
   */
  private $productOptionsManager;

  /**
   * Instance of I18nHelper service.
   *
   * @var \Drupal\acm\I18nHelper
   */
  private $i18nHelper;

  /**
   * SKU Fields Manager.
   *
   * @var \Drupal\acm_sku\SKUFieldsManager
   */
  private $skuFieldsManager;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * Event Dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * True if you want extra logging for debugging.
   *
   * @var bool
   */
  private $debug;

  private $failed;
  private $ignored;
  private $deleted;
  private $created;
  private $updated;
  private $failedSkus;
  private $createdSkus;
  private $ignoredSkus;
  private $deletedSkus;
  private $updatedSkus;
  private $debugDir;

  /**
   * Construct.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\acm_sku\CategoryRepositoryInterface $cat_repo
   *   Category Repository instance.
   * @param \Drupal\acm_sku\ProductOptionsManager $product_options_manager
   *   Product Options Manager service instance.
   * @param \Drupal\acm\I18nHelper $i18nHelper
   *   Instance of I18nHelper service.
   * @param \Drupal\acm_sku\SKUFieldsManager $sku_fields_manager
   *   SKU Fields Manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              ConfigFactoryInterface $config_factory,
                              LoggerChannelFactoryInterface $logger_factory,
                              CategoryRepositoryInterface $cat_repo,
                              ProductOptionsManager $product_options_manager,
                              I18nHelper $i18nHelper,
                              SKUFieldsManager $sku_fields_manager,
                              ModuleHandlerInterface $moduleHandler,
                              EventDispatcherInterface $event_dispatcher) {
    $this->entityManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('acm');
    $this->categoryRepo = $cat_repo;
    $this->productOptionsManager = $product_options_manager;
    $this->i18nHelper = $i18nHelper;
    $this->skuFieldsManager = $sku_fields_manager;
    $this->moduleHandler = $moduleHandler;
    $this->eventDispatcher = $event_dispatcher;

    $this->debug = $this->configFactory->get('acm.connector')->get('debug');
    $this->debugDir = $this->configFactory->get('acm.connector')->get('debug_dir');
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
   * CreateDisplayNode.
   *
   * Create a product display node for a set of SKU entities.
   *
   * @param array $product
   *   Product data.
   * @param string $langcode
   *   Language code.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Node object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function createDisplayNode(array $product, $langcode = '') {

    $description = (isset($product['attributes']['description'])) ? $product['attributes']['description'] : '';

    $categories = (isset($product['categories'])) ? $product['categories'] : [];

    $config = $this->configFactory->get('acm.connector');
    $product_node_type = $config->get('product_node_type') ?: 'acm_product';
    $sku_field_name = $config->get('sku_field_name') ?: 'field_skus';
    $category_field_name = $config->get('category_field_name');
    $text_format = $config->get('text_format') ?: 'rich_text';

    $node_title = html_entity_decode($product['name']);
    if ($config->get('product_title_use_sku')) {
      $node_title = html_entity_decode($product['sku']);
    }

    $node_values = [
      'type' => $product_node_type,
      'title' => $node_title,
      'body' => [
        'value' => $description,
        'format' => $text_format,
      ],
      $sku_field_name => [$product['sku']],
    ];

    if ($langcode) {
      $node_values['langcode'] = $langcode;
    }

    // Add categories if they're configured to be synced.
    if ($category_field_name) {
      $categories = $this->formatCategories($categories);
      $node_values[$category_field_name] = $categories;
    }

    $node = $this->entityManager->getStorage('node')->create($node_values);

    if ($config->get('product_publish')) {
      $node->setPublished();
    }
    else {
      $node->setUnpublished();
    }

    // Invoke the alter hook to allow all modules to update the node.
    $this->moduleHandler->alter('acm_sku_product_node', $node, $product);

    return $node;
  }

  /**
   * Update node translation.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Object of node we want to update with precreated translation.
   * @param array $product
   *   Array with product values.
   * @param string $langcode
   *   Langcode string.
   */
  public function updateNodeTranslation(NodeInterface &$node, array $product, string $langcode) {
    $description = (isset($product['attributes']['description'])) ? $product['attributes']['description'] : '';

    $categories = (isset($product['categories'])) ? $product['categories'] : [];

    $config = $this->configFactory->get('acm.connector');
    $sku_field_name = $config->get('sku_field_name') ?: 'field_skus';
    $category_field_name = $config->get('category_field_name');
    $text_format = $config->get('text_format') ?: 'rich_text';

    $node_title = html_entity_decode($product['name']);
    if ($config->get('product_title_use_sku')) {
      $node_title = html_entity_decode($product['sku']);
    }
    $node->setTitle($node_title);
    $body = [
      'value' => $description,
      'format' => $text_format,
    ];
    $node->set('body', $body);

    // Add categories if they're configured to be synced.
    if ($category_field_name) {
      $categories = $this->formatCategories($categories);
      $node->{$category_field_name} = $categories;
    }
    $node->{$sku_field_name} = [$product['sku']];

    if ($config->get('product_publish')) {
      $node->setPublished();
    }
    else {
      $node->setUnpublished();
    }

    // Invoke the alter hook to allow all modules to update the node.
    $this->moduleHandler->alter('acm_sku_product_node_translation_update', $node, $product, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function synchronizeProducts(array $products = [], $storeId = '') {
    /** @var \Drupal\Core\Lock\PersistentDatabaseLockBackend $lock */
    $lock = \Drupal::service('lock.persistent');

    $this->created = 0;
    $this->updated = 0;
    $this->failed = 0;
    $this->ignored = 0;
    $this->deleted = 0;
    $processLaterList = [];

    // Get langcode for v2 connector.
    $langcode = '';
    if (!empty($storeId)) {
      $langcode = $this->i18nHelper->getLangcodeFromStoreId($storeId);
    }

    $this->debugLogger('Number of products: @count', ['@count' => count($products)]);
    foreach ($products as $product) {
      try {
        // Allow other modules to subscribe to pre-validation of the SKU being
        // imported.
        $event = new AcmSkuValidateEvent($product);
        $this->eventDispatcher->dispatch(AcmSkuValidateEvent::ACM_SKU_VALIDATE, $event);
        $product = $event->getProduct();

        // If skip attribute is set via any event subscriber, skip importing the
        // product.
        if (!empty($product['skip'])) {
          // We mark the status to disabled so product is deleted if available.
          $product['status'] = 0;

          $this->logger->warning('Updated status of sku @sku to 0 as it is marked as skipped.', [
            '@sku' => $product['sku'],
          ]);
        }

        $lock_key = 'synchronizeProduct' . $product['sku'];
        // Acquire lock to ensure parallel processes are executed one by one.
        do {
          $lock_acquired = $lock->acquire($lock_key);
          // Sleep for half a second before trying again.
          if (!$lock_acquired) {
            usleep(500000);
          }
        } while (!$lock_acquired);

        // For v1 connector we are going to get store_id from each product,
        // because we are not sending X-ACM-UUID header.
        // Noting product sync hits the standard Magento API and
        // so $product['store_id'] is not set on product sync.
        // However, it is set on product async because
        // that hits ACM Magento module.
        if (empty($langcode)) {
          $langcode = $this->i18nHelper->getLangcodeFromStoreId($product['store_id']);
        }

        // If langcode is still empty at this point, we probably don't support
        // this store. This is because we are sending all data for all stores.
        if (empty($langcode)) {
          $this->debugLogger("Lang code is empty. Otherwise would have synchronize product SKU @sku for store_id @store in language @langcode", [
            '@sku' => $product['sku'],
            '@store' => $product['store_id'],
            '@langcode' => $langcode,
          ]);
          $this->ignoredSkus[] = $product['sku'] . '(Langcode is empty for store_id:' . $product['store_id'] . '.)';
          $this->ignored++;
          // Release the lock on this sku.
          $lock->release($lock_key);
          $lock_key = NULL;
          continue;
        }

        $this->debugLogger("Synchronize product SKU @sku for store_id @store in language @langcode", [
          '@sku' => $product['sku'],
          '@store' => $product['store_id'],
          '@langcode' => $langcode,
        ]);
        $this->debugLogger('Product data: @data', ['@data' => print_r($product, TRUE)]);
        $display = NULL;

        if ($this->debug && !empty($this->debugDir)) {
          // Export product data into file.
          if (!isset($fps) || !isset($fps[$langcode])) {
            $filename = $this->debugDir . '/products_' . $langcode . '.data';
            $fps[$langcode] = fopen($filename, 'a');
          }
          fwrite($fps[$langcode], var_export($product, 1));
          fwrite($fps[$langcode], '\n');
        }

        if (!isset($product['type'])) {
          $message = "Product type must be defined. " . $product['sku'] . " was not synchronized.";
          $this->failedSkus[] = $product['sku'] . '(Missing Product Type)';
          $this->failed++;
          // Release the lock on this sku.
          $lock->release($lock_key);
          $lock_key = NULL;
          continue;
        }

        $query = $this->entityManager->getStorage('acm_sku_type')->getQuery()
          ->condition('id', $product['type'])
          ->count();

        $has_bundle = $query->execute();

        if (!$has_bundle) {
          $message = "Product type " . $product['type'] . " is not supported yet. " . $product['sku'] . " was not synchronized.";
          $this->ignoredSkus[] = $product['sku'] . '(Product type not supported yet.' . $product['type'] . ')';
          $this->ignored++;
          // Release the lock on this sku.
          $lock->release($lock_key);
          $lock_key = NULL;
          continue;
        }

        if (!isset($product['sku']) || !strlen($product['sku'])) {
          $this->ignoredSkus[] = $product['sku'] . '(Invalid or empty product SKU.)';
          $this->ignored++;
          // Release the lock on this sku.
          $lock->release($lock_key);
          $lock_key = NULL;
          continue;
        }

        // Don't import configurable SKU if it has no configurable options.
        // @TODO(mirom): Call validation function by $product['type'].
        if ($product['type'] == 'configurable' && empty($product['extension']['configurable_product_options'])) {
          $productToString = print_r($product, TRUE);
          $this->debugLogger('Empty configurable options for SKU: @sku, Details: @deets', [
            '@sku' => $product['sku'],
            '@deets' => $productToString,
          ]);
          $this->ignoredSkus[] = $product['sku'] . '(Empty configurable options for SKU.)';
          $this->ignored++;
          // Release the lock on this sku.
          $lock->release($lock_key);
          $lock_key = NULL;
          continue;
        }

        $query = $this->entityManager->getStorage('acm_sku')->getQuery()
          ->condition('sku', $product['sku']);
        $sku_ids = $query->execute();

        if (count($sku_ids) > 1) {
          $this->failedSkus[] = $product['sku'] . '(Duplicate product SKU found.)';
          $this->failed++;
          // Release the lock on this sku.
          $lock->release($lock_key);
          $lock_key = NULL;
          continue;
        }

        $sku = $this->processSku($product, $langcode);

        if (is_null($sku)) {
          continue;
        }

        /** @var \Drupal\acm_sku\AcquiaCommerce\SKUPluginBase $plugin */
        $plugin = $sku->getPluginInstance();
        $plugin->processImport($sku, $product);

        if ($product['status'] == 1 && $product['visibility'] == 1) {
          $node = $plugin->getDisplayNode($sku, FALSE, TRUE);
          if (empty($node)) {
            $node = $this->createDisplayNode($product, $langcode);
            $this->createdSkus[] = $product['sku'];
            $this->created++;
          }
          elseif ($node->hasTranslationChanges()) {
            $this->updateNodeTranslation($node, $product, $langcode);
          }

          // We doing this because when the translation of node is created by
          // addTranslation(), pathauto alias is not created for the translated
          // version.
          // @see https://www.drupal.org/project/pathauto/issues/2995829.
          if ($this->moduleHandler->moduleExists('pathauto')) {
            $node->path->pathauto = 1;
          }

          // Invoke the alter hook to allow all modules to update the node.
          $this->moduleHandler->alter('acm_sku_product_node', $node, $product, $langcode);
          $node->save();
        }
        else {
          try {
            // Un-publish if node available.
            if ($node = $plugin->getDisplayNode($sku, FALSE, FALSE)) {
              $node->setUnpublished();
              $node->save();
            }
          }
          catch (\Exception $e) {
            // Do nothing, we may not have the node available in system.
          }
        }

        $plugin_manager = \Drupal::service('plugin.manager.sku');
        $plugin_definition = $plugin_manager->pluginFromSKU($sku);
        if (!empty($plugin_definition)) {
          $plugin = $plugin_manager->createInstance($plugin_definition['id']);
          $processedImport = $plugin->processImport($sku, $product);
          if (!$processedImport) {
            $this->debugLogger("@sku will be processed later", ['@sku' => $product['sku']]);
            $processLaterList[] = [
              'plugin' => $plugin,
              'sku' => $sku,
              'product' => $product,
            ];
          }
        }
      }
      catch (\Exception $e) {
        // We consider this as failure as it failed for an unknown reason.
        // (not taken care of above).
        $this->failedSkus[] = $product['sku'] . '(' . $e->getMessage() . ')';
        $this->failed++;
      }
      catch (\Throwable $e) {
        // We consider this as failure as it failed for an unknown reason.
        // (not taken care of above).
        $this->failedSkus[] = $product['sku'] . '(' . $e->getMessage() . ')';
        $this->failed++;
      }
      finally {
        // Release the lock if acquired.
        if (!empty($lock_key) && !empty($lock_acquired)) {
          $lock->release($lock_key);

          // We will come here again for next loop item and we might face
          // exception before we reach the code that sets $lock_key.
          // To ensure we don't keep releasing the lock again and again
          // we set it to NULL here.
          $lock_key = NULL;
        }
      }
    }

    if (isset($fps)) {
      foreach ($fps as $fp) {
        fclose($fp);
      }
    }

    // @TODO(mirom): Review usage of processImport(), it's called 5 times here.
    // ProcessImport again if necessary (eg configured products).
    foreach ($processLaterList as $item) {
      $this->debugLogger("@sku is being processed later", ['@sku' => $item['product']['sku']]);

      $lock_key = 'synchronizeProduct' . $item['product']['sku'];
      // Acquire lock to ensure parallel processes are executed one by one.
      do {
        $lock_acquired = $lock->acquire($lock_key);
      } while (!$lock_acquired);

      $processedImport = $item['plugin']->processImport($item['sku'], $item['product']);
      if (!$processedImport) {
        $this->logger->error("Product @name(@sku) failed to process completely. Please check it before use. This normally happens when a configured product does not have access to one or more of its underlying simple products.",
          [
            '@name' => $item['product']['name'],
            '@sku' => $item['product']['sku'],
          ]);
      }
      // Release the lock on this sku.
      $lock->release($lock_key);
      $lock_key = NULL;
    }

    // Log product import summary.
    if (!empty($this->createdSkus)) {
      $this->logger->info('SKU import, created: @created_skus', ['@created_skus' => implode(',', $this->createdSkus)]);
    }

    if (!empty($this->deletedSkus)) {
      $this->logger->info('SKU import, deleted: @deleted_skus', ['@deleted_skus' => implode(',', $this->deletedSkus)]);
    }

    if (!empty($this->updatedSkus)) {
      $this->logger->info('SKU import, updated: @updated_skus', ['@updated_skus' => implode(',', $this->updatedSkus)]);
    }

    if (!empty($this->failedSkus)) {
      $this->logger->error('SKU import, failed: @failed_skus', ['@failed_skus' => implode(',', $this->failedSkus)]);
    }

    if (!empty($this->ignoredSkus)) {
      $this->logger->error('SKU import, ignored: @ignored_skus', ['@ignored_skus' => implode(',', $this->ignoredSkus)]);
    }

    // Return success true always, we reached here which means we successfully
    // processed the sync request.
    return [
      'success' => TRUE,
      'created' => $this->created,
      'updated' => $this->updated,
      'failed' => $this->failed,
      'ignored' => $this->ignored,
      'deleted' => $this->deleted,
    ];
  }

  /**
   * FormatCategories.
   *
   * @return array
   *   Array of terms.
   */
  private function formatCategories(array $categories) {

    $terms = [];

    foreach ($categories as $cid) {
      $term = $this->categoryRepo->loadCategoryTerm($cid);
      if ($term) {
        $terms[] = $term->id();
      }
    }

    return ($terms);
  }

  /**
   * FormatProductAttributes.
   *
   * Format the product attributes data as an array for saving in a
   * key value field.
   *
   * @param array $attributes
   *   Array of product attributes.
   *
   * @return array
   *   Array of formatted product attributes.
   */
  private function formatProductAttributes(array $attributes) {

    $formatted = [];

    foreach ($attributes as $name => $value) {
      if (is_string($value)) {
        $valueAsString = $value;
      }
      else {
        // Does the key=>value store module expect serialize?
        // (I would prefer JSON)
        // >> Yes. (But are we using the key=>value store for this?)
        // Can we pass it the object and get 'auto serialize/unserialize'?
        // >> Maybe.
        if (is_array($value) || is_object($value)) {
          $valueAsString = serialize($value);
        }
        else {
          // 'Trust' PHP's type-casting for now.
          $valueAsString = (string) $value;
        }
      }

      $formatted[] = [
        'key' => $name,
        'value' => $valueAsString,
      ];
    }

    return $formatted;
  }

  /**
   * Process SKU creation.
   *
   * @param array $product
   *   Array with product data fetched from eComm.
   * @param string $langcode
   *   String representation of langcode.
   *
   * @return \Drupal\acm_sku\Entity\SKUInterface|null
   *   SKU object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   *   If the data is read-only.
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *   If the complex data structure is unset and no item can be set.
   * @throws \Exception
   *   Just in case.
   */
  public function processSku(array $product, $langcode) {
    $em = $this->entityManager->getStorage('acm_sku');
    if ($sku = SKU::loadFromSku($product['sku'], $langcode, FALSE, TRUE)) {
      if ($product['status'] != 1 && $this->configFactory->get('acm.connector')->get('delete_disabled_skus')) {
        $this->logger->info('Removing disabled SKU from system: @sku.', ['@sku' => $product['sku']]);
        try {
          /** @var \Drupal\acm_sku\AcquiaCommerce\SKUPluginBase $plugin */
          $plugin = $sku->getPluginInstance();
          if ($node = $plugin->getDisplayNode($sku, FALSE, FALSE)) {
            // Delete the node if it is linked to this SKU only.
            $node->delete();
            $this->logger->info('Deleted node for SKU @sku for @langcode.', [
              '@sku' => $sku->getSku(),
              '@langcode' => $langcode,
            ]);
          }
          else {
            $this->logger->info('Node for SKU @sku for @langcode not found for deletion.', [
              '@sku' => $sku->getSku(),
              '@langcode' => $langcode,
            ]);
          }
        }
        catch (\Exception $e) {
          // Not doing anything, we might not have node for the sku.
          $this->logger->info('Error while deleting node for the SKU @sku for @langcode. Message:@message', [
            '@sku' => $sku->getSku(),
            '@langcode' => $langcode,
            '@message' => $e->getMessage(),
          ]);
        }

        // Delete the SKU.
        $sku->delete();
        $this->deletedSkus[] = $product['sku'];
        $this->deleted++;
        return NULL;
      }

      $this->logger->info('Updating product SKU @sku.', ['@sku' => $product['sku']]);
      $this->updatedSkus[] = $product['sku'];
      $this->updated++;
    }
    else {
      if ($product['status'] != 1 && $this->configFactory->get('acm.connector')->get('delete_disabled_skus')) {
        $this->ignoredSkus[] = $product['sku'] . '(Disabled SKU).';
        $this->ignored++;
        return NULL;
      }
      /** @var \Drupal\acm_sku\Entity\SKU $sku */
      $sku = $em->create([
        'type' => $product['type'],
        'sku' => $product['sku'],
        'langcode' => $langcode,
      ]);

      $this->createdSkus[] = $product['sku'];
      $this->created++;
    }

    $sku->name->value = html_entity_decode($product['name']);
    $sku->price->value = $product['price'];
    $sku->special_price->value = $product['special_price'];
    $sku->final_price->value = $product['final_price'];
    $sku->attributes = $this->formatProductAttributes($product['attributes']);

    $hasSerializableMedia = (
      array_key_exists('extension', $product) &&
      array_key_exists('media', $product['extension']) &&
      $product['extension']['media']
    );
    if ($hasSerializableMedia) {
      $sku->media = serialize($product['extension']['media']);
    }
    $sku->attribute_set = $product['attribute_set_label'];
    $sku->product_id = $product['product_id'];

    // Update the fields based on the values from attributes.
    $this->updateFields('attributes', $sku, $product['attributes']);

    // Update the fields based on the values from extension.
    $this->updateFields('extension', $sku, $product['extension']);

    // Update upsell linked SKUs.
    $this->updateLinkedSkus('upsell', $sku, $product['linked']);

    // Update crosssell linked SKUs.
    $this->updateLinkedSkus('crosssell', $sku, $product['linked']);

    // Update related linked SKUs.
    $this->updateLinkedSkus('related', $sku, $product['linked']);

    /** @var \Drupal\acm_sku\AcquiaCommerce\SKUPluginBase $plugin */
    $plugin = $sku->getPluginInstance();
    $plugin->processImport($sku, $product);

    // Invoke the alter hook to allow all modules to update the node.
    $this->moduleHandler->alter('acm_sku_product_sku', $sku, $product);

    $sku->save();

    // Update product media to set proper position.
    $sku->media = $this->getProcessedMedia($product, $sku->media->value);
    $sku->getMedia();

    if (empty($sku->get('image')->target_id)) {
      $thumbnail = $sku->getThumbnail();
      if (!empty($thumbnail)) {
        $sku->set('image', $thumbnail['fid']);
        $sku->save();
      }
    }
    return $sku;
  }

  /**
   * Update linked Skus.
   *
   * Prepare the field value for linked type (upsell, crosssell, etc.).
   * Get the position based on the position coming from API.
   *
   * @param string $type
   *   Type of link.
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   Root SKU.
   * @param array $linked
   *   Linked SKUs.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   *   If the data is read-only.
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *   If the complex data structure is unset and no item can be set.
   */
  private function updateLinkedSkus($type, SKU &$sku, array $linked) {
    // Reset the upsell skus to null.
    $sku->{$type}->setValue([]);
    $fieldData = [];
    foreach ($linked as $link) {
      if ($link['type'] != $type) {
        continue;
      }
      $fieldData[$link['position']] = $link['linked_sku'];
    }
    // If there is no upsell skus to link, we simply return from here.
    if (empty($fieldData)) {
      return;
    }
    // Sort them based on position.
    ksort($fieldData);
    // Update the index to sequential values so we can set in field.
    $fieldData = array_values($fieldData);
    foreach ($fieldData as $delta => $value) {
      $sku->{$type}->set($delta, $value);
    }
  }

  /**
   * Update attribute fields.
   *
   * Update the fields based on the values from attributes.
   *
   * @param string $parent
   *   Fields to get from this parent will be processed.
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   The root SKU.
   * @param array $values
   *   The product attributes/extensions to get value from.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   *   If the data is read-only.
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *   If the complex data structure is unset and no item can be set.
   */
  private function updateFields($parent, SKU $sku, array $values) {
    $additionalFields = $this->skuFieldsManager->getFieldAdditions();

    // Filter fields for the parent requested.
    $additionalFields = array_filter($additionalFields, function ($field) use ($parent) {
      return ($field['parent'] == $parent);
    });

    // Loop through all the fields we want to read from product data.
    foreach ($additionalFields as $key => $field) {
      $source = isset($field['source']) ? $field['source'] : $key;

      // Field key.
      $field_key = 'attr_' . $key;

      switch ($field['type']) {
        case 'attribute':
          // If attribute is not coming in response, then unset it.
          if (!isset($values[$source])) {
            $sku->{$field_key}->set(0, NULL);
          }
          else {
            $value = $values[$source];
            $value = $field['cardinality'] != 1 ? explode(',', $value) : [$value];
            foreach ($value as $index => $val) {
              if ($term = $this->productOptionsManager->loadProductOptionByOptionId($source, $val, $sku->language()->getId())) {
                $sku->{$field_key}->set($index, $term->getName());
              }
              else {
                $sku->{$field_key}->set($index, $val);
              }
            }
          }
          break;

        case 'string':
          // If attribute is not coming in response, then unset it.
          if (!isset($values[$source])) {
            $sku->{$field_key}->setValue(NULL);
          }
          else {
            $value = $values[$source];
            $value = $field['cardinality'] != 1 ? explode(',', $value) : $value;
            $sku->{$field_key}->setValue($value);
          }
          break;

        case 'text_long':
          // If attribute is not coming in response, then unset it.
          if (!isset($values[$source])) {
            $sku->{$field_key}->setValue(NULL);
          }
          else {
            $value = $values[$source];
            $value = !empty($field['serialize']) ? serialize($value) : $value;
            $sku->{$field_key}->setValue($value);
          }
          break;
      }
    }
  }

  /**
   * Extracts and orders the media gallery by position and sets the main image.
   *
   * Extracts the media gallery fields from product[extension][media][0].
   * Orders the media gallery by position and sets the base (main) image
   * to be positioned first.
   * Disabled images are left in place.
   *
   * @param array $product
   *   Array with product information.
   * @param string $current_value
   *   The current media data of the sku bein processed.
   *
   * @return string
   *   Serialized array with product's media information.
   */
  protected function getProcessedMedia(array $product, $current_value) {
    $media = [];

    // The Commerce Connector Service sends the media information
    // in $product['extension']['media'][0].
    // We note $product['extension']['media'][1] is deliberately always empty.
    // See Magento module method...
    // Model/ProductSyncManagement::processMediaGalleryExtension()
    // TODO What does Hybris send? The connector doesn't normalize.
    if (isset(
      $product['extension'],
      $product['extension']['media'],
      $product['extension']['media'][0])) {

      // Conveniently reset() returns the first element of the array.
      $media = reset($product['extension']['media']);

      if (isset($product['attributes'], $product['attributes']['image'])) {
        $image = $product['attributes']['image'];

        // If the base image is in the gallery then position it first.
        // But why? Is this an Acquia Commerce Manager requirement?
        // It is normal in Magento to set a gallery position for the base image
        // And to set images as 'hide in gallery' ie ['disabled'] = 1
        // So I suggest we honor the Magento data here.
        // TODO What does Hybris send? (what would the normalization be?)
        // Maybe we just bring in all images and let the Drupal user decide
        // what to display and in which position.
        // Later, will the Drupal theme honor ['disabled'] = 1 or do we
        // need to omit ['disabled'] = 1 images here.
        foreach ($media as &$data) {
          if (substr_compare($data['file'], $image, -strlen($image)) === 0) {
            $data['position'] = -1;
            break;
          }
        }
      }

      // Sort media data by position. Noting disabled images are included.
      usort($media, function ($a, $b) {
        $position1 = (int) $a['position'];
        $position2 = (int) $b['position'];

        if ($position1 == $position2) {
          return 0;
        }

        return ($position1 < $position2) ? -1 : 1;
      });
    }

    // Reassign old files to not have to redownload them.
    if (!empty($media)) {
      $current_media = unserialize($current_value);
      if (!empty($current_media) && is_array($current_media)) {
        $current_mapping = [];
        foreach ($current_media as $value) {
          if (!empty($value['fid'])) {
            $current_mapping[$value['value_id']]['fid'] = $value['fid'];
            $current_mapping[$value['value_id']]['file'] = $value['file'];
          }
        }

        foreach ($media as $key => $value) {
          if (isset($current_mapping[$value['value_id']])) {
            $media[$key]['fid'] = $current_mapping[$value['value_id']]['fid'];
            $media[$key]['file'] = $current_mapping[$value['value_id']]['file'];
          }
        }
      }
    }

    return serialize($media);
  }

}
