<?php

namespace Drupal\acm_promotion;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm_sku\Entity\SKU;
use Drupal\acm_sku\Entity\SKUInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\acm\I18nHelper;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Class AcmPromotionsManager.
 */
class AcmPromotionsManager {

  use StringTranslationTrait;

  /**
   * Language Manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Sku Entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $skuStorage;

  /**
   * Entity Repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Node Entity Storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  private $nodeStorage;

  /**
   * The api wrapper.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  protected $apiWrapper;

  /**
   * Queue Factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * I18n Helper.
   *
   * @var \Drupal\acm\I18nHelper
   */
  private $i18nHelper;

  /**
   * Constructs a new AcmPromotionsManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManager object.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   The api wrapper.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   LoggerFactory object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language Manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   Entity Repository service.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   Queue factory service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection service.
   * @param \Drupal\acm\I18nHelper $i18n_helper
   *   I18nHelper object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              APIWrapperInterface $api_wrapper,
                              LoggerChannelFactoryInterface $logger_factory,
                              LanguageManagerInterface $languageManager,
                              EntityRepositoryInterface $entityRepository,
                              QueueFactory $queue,
                              ConfigFactoryInterface $configFactory,
                              Connection $connection,
                              I18nHelper $i18n_helper) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->skuStorage = $entity_type_manager->getStorage('acm_sku');
    $this->apiWrapper = $api_wrapper;
    $this->logger = $logger_factory->get('acm_promotion');
    $this->languageManager = $languageManager;
    $this->entityRepository = $entityRepository;
    $this->queue = $queue;
    $this->configFactory = $configFactory;
    $this->connection = $connection;
    $this->i18nHelper = $i18n_helper;
  }

  /**
   * Synchronize promotions through the API.
   *
   * @param mixed $types
   *   The type of promotion to synchronize.
   */
  public function syncPromotions($types = ['category', 'cart']) {
    $types = is_array($types) ? $types : [$types];
    $ids = [];
    $fetched_promotions = [];

    foreach ($types as $type) {
      $promotions = $this->apiWrapper->getPromotions($type);

      foreach ($promotions as $key => $promotion) {
        // Add type to $promotion array, to be saved later.
        $promotion['promotion_type'] = $type;
        $fetched_promotions[] = $promotion;
        $ids[] = $promotion['rule_id'];
      }
    }

    if (!empty($fetched_promotions)) {
      $this->processPromotions($fetched_promotions);
    }

    // Delete promotions, which are not part of API response.
    if (!empty($ids)) {
      $this->deletePromotions($types, $ids);
    }
  }

  /**
   * Delete Promotion nodes, not part of API Response.
   *
   * @param array $types
   *   Promotions types to delete.
   * @param array $validIDs
   *   Valid Rule ID's from API.
   */
  protected function deletePromotions(array $types, array $validIDs = []) {
    $query = $this->nodeStorage->getQuery();
    $query->condition('type', 'acm_promotion');
    $query->condition('field_acm_promotion_type', $types, 'IN');

    if ($validIDs) {
      $query->condition('field_acm_promotion_rule_id', $validIDs, 'NOT IN');
    }

    $nids = $query->execute();

    foreach ($nids as $nid) {
      /* @var $node \Drupal\node\NodeInterface */
      $node = $this->nodeStorage->load($nid);
      if ($node instanceof NodeInterface) {
        $node->delete();
        $this->logger->notice('Deleted orphan promotion node @promotion having rule_id:@rule_id.', [
          '@promotion' => $node->label(),
          '@rule_id' => $node->get('field_acm_promotion_rule_id')->first()->getString(),
        ]);
      }
    }
  }

  /**
   * Helper function to fetch promotion node given rule id.
   *
   * @param int $rule_id
   *   Rule id of the promotion to load.
   * @param string $rule_type
   *   Rule type of the promotion to load.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Return node if a promotion found associated with the rule id else Null.
   */
  public function getPromotionByRuleId($rule_id, $rule_type) {
    $query = $this->nodeStorage->getQuery();
    $query->condition('type', 'acm_promotion');
    $query->condition('field_acm_promotion_rule_id', $rule_id);
    $query->condition('field_acm_promotion_type', $rule_type);
    $nids = $query->execute();

    if (empty($nids)) {
      return NULL;
    }
    else {
      // Log a message for admin to check errors in data.
      if (count($nids) > 1) {
        $this->logger->critical('Multiple nodes found for rule id @rule_id', ['@rule_id' => $rule_id]);
        return NULL;
      }

      // We only load the first node.
      /* @var $node \Drupal\node\NodeInterface */
      $node = $this->nodeStorage->load(reset($nids));
      $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
      // Get the promotion with language fallback, if it did not have a
      // translation for $langcode.
      $node = $this->entityRepository->getTranslationFromContext($node, $langcode);
      return $node;
    }
  }

  /**
   * Helper function to get skus attached with a promotion.
   *
   * @param \Drupal\node\NodeInterface $promotion
   *   Promotion node for which we need to find skus.
   *
   * @return array
   *   Array of sku objects attached with the promotion.
   */
  public function getSkusForPromotion(NodeInterface $promotion) {
    $query = $this->connection->select('acm_sku__field_acm_sku_promotions', 'fasp');
    if (\Drupal::entityTypeManager()->getDefinition('acm_sku')->isTranslatable()) {
      $query->join('acm_sku_field_data', 'asfd', 'asfd.id = fasp.entity_id');
    }
    else {
      $query->join('acm_sku', 'asfd', 'asfd.id = fasp.entity_id');
    }
    $query->condition('fasp.field_acm_sku_promotions_target_id', $promotion->id());
    $query->fields('asfd', ['id', 'sku']);
    $query->distinct();
    $skus = $query->execute()->fetchAllKeyed(0, 1);

    return $skus;
  }

  /**
   * Helper function to create Promotion node from connector response.
   *
   * @param array $promotion
   *   Promotion response from Connector.
   * @param \Drupal\node\NodeInterface $promotion_node
   *   Promotion node in case we need to update Promotion.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Promotion node.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   *   Exception thrown when trying to write or set ready-only data.
   * @throws \Exception
   *   Generic exception.
   */
  public function syncPromotionWithConnectorResponse(array $promotion, NodeInterface $promotion_node = NULL) {
    if (!$promotion_node) {
      $promotion_node = $this->nodeStorage->create([
        'type' => 'acm_promotion',
      ]);
    }

    $config = $this->configFactory->get('acm.connector');
    $text_format = $config->get('text_format') ?: 'rich_text';
    $promotions_labels = $promotion['labels'];
    $promotion_label_languages = [];
    $site_default_langcode = $this->languageManager->getDefaultLanguage()->getId();

    $promotions_label = reset($promotion['labels']);
    foreach ($promotions_labels as $promotion_label) {
      $promotion_label_language = $this->i18nHelper->getLangcodeFromStoreId($promotion_label['store_id']);

      // Magento might have stores that what we don't support.
      if (empty($promotion_label_language)) {
        continue;
      }

      $promotion_label_languages[$promotion_label_language] = $promotion_label['store_label'];
    }

    $promotion_node->get('title')->setValue($promotion['name']);

    // Set the description.
    $promotion_node->get('field_acm_promotion_description')->setValue(['value' => $promotion['description'], 'format' => $text_format]);

    // Set promotion rule_id.
    $promotion_node->get('field_acm_promotion_rule_id')->setValue($promotion['rule_id']);

    // Set the status.
    $promotion_node->setPublished((bool) $promotion['status']);

    // Store everything as serialized string in DB.
    $promotion_node->get('field_acm_promotion_data')->setValue(serialize($promotion));

    // Set the Promotion type.
    $promotion_node->get('field_acm_promotion_type')->setValue($promotion['promotion_type']);

    // Set the Promotion label.
    if (isset($promotion_label_languages[$site_default_langcode])) {
      $promotion_node->get('field_acm_promotion_label')->setValue($promotion_label_languages[$site_default_langcode]);
    }

    // Set promotion coupon code.
    $promotion_node->get('field_coupon_code')->setValue($promotion['coupon_code']);

    // Set the Promotion skus.
    $skus = [];
    foreach ($promotion['products'] as $product) {
      $sku = SKU::loadFromSku($product['product_sku']);
      if ($sku instanceof SKUInterface) {
        $skus[] = $product['product_sku'];
      }
    }
    $promotion_node->get('field_skus')->setValue($skus);

    // Set promotion type to percent & discount value depending on the promotion
    // being imported.
    if (($promotion['type'] === 'NO_COUPON') && isset($promotion['action']) && ($promotion['action'] === 'by_percent')) {
      $promotion_node->get('field_acm_promotion_disc_type')->setValue('percentage');
      $promotion_node->get('field_acm_promotion_discount')->setValue($promotion['discount']);
    }

    // Check promotion action type & store in Drupal.
    if (!empty($promotion['action'])) {
      $promotion_node->get('field_acm_promotion_action')->setValue($promotion['action']);
    }

    // Invoke the alter hook to allow modules to update the node from API data.
    \Drupal::moduleHandler()->alter('acm_promotion_promotion_node', $promotion_node, $promotion);

    $status = $promotion_node->save();
    // Create promotion translations based on the language codes available in
    // promotion labels.
    foreach ($promotion_label_languages as $langcode => $promotion_label_language) {
      if ($langcode !== $site_default_langcode) {
        if ($promotion_node->hasTranslation($langcode)) {
          $promotion_node->removeTranslation($langcode);
        }

        $node_translation = $promotion_node->addTranslation($langcode, $promotion_node->toArray());

        $node_translation->get('field_acm_promotion_label')->setValue($promotion_label_languages[$langcode]);
        $node_translation->save();
      }
    }

    if ($status) {
      return $promotion_node;
    }
    else {
      $this->logger->critical('Error occured while creating Promotion node for rule id: @rule_id.', ['@rule_id' => $promotion['rule_id']]);
      return NULL;
    }
  }

  /**
   * Helper function to process Promotions obtained from connector.
   *
   * @param array $promotions
   *   List of promotions to sync.
   *
   * @return array
   *   Messages around attach & detach queues.
   */
  public function processPromotions(array $promotions = []) {
    $output = [];
    $acm_promotion_attach_batch_size = $this->configFactory
      ->get('acm_promotion.settings')
      ->get('promotion_attach_batch_size');

    $promotion_detach_queue = $this->queue->get('acm_promotion_detach_queue');
    $promotion_attach_queue = $this->queue->get('acm_promotion_attach_queue');

    // Clear any outstanding items in queue before starting promotion import to
    // avoid duplicate queues.
    $promotion_detach_queue->deleteQueue();
    $promotion_attach_queue->deleteQueue();

    foreach ($promotions as $promotion) {
      $fetched_promotion_skus = [];
      $fetched_promotion_sku_attach_data = [];

      // Extract list of sku text attached with the promotion passed.
      $products = $promotion['products'];
      foreach ($products as $product) {
        if (!in_array($product['product_sku'], array_keys($fetched_promotion_skus))) {
          $fetched_promotion_skus[$product['product_sku']] = $product['product_sku'];

          $fetched_promotion_sku_attach_data[$product['product_sku']] = [
            'sku' => $product['product_sku'],
          ];

          if (($promotion['promotion_type'] === 'category') && isset($product['final_price'])) {
            $fetched_promotion_sku_attach_data[$product['product_sku']]['final_price'] = $product['final_price'];
          }
        }
      }

      // Check if this promotion exists in Drupal.
      // Assuming rule_id is unique across a promotion type.
      $promotion_node = $this->getPromotionByRuleId($promotion['rule_id'], $promotion['promotion_type']);

      // If promotion exists, we update the related skus & final price.
      if ($promotion_node) {
        $promotion_nid = $promotion_node->id();
        // Update promotion metadata.
        $this->syncPromotionWithConnectorResponse($promotion, $promotion_node);
        $attached_promotion_skus = $this->getSkusForPromotion($promotion_node);
        $detach_promotion_skus = [];

        // Get list of skus for which promotions should be detached.
        if (!empty($attached_promotion_skus)) {
          $detach_promotion_skus = array_diff($attached_promotion_skus, $fetched_promotion_skus);
        }

        // Create a queue for removing promotions from skus.
        if (!empty($detach_promotion_skus)) {
          $chunks = array_chunk($detach_promotion_skus, $acm_promotion_attach_batch_size);
          foreach ($chunks as $chunk) {
            $data['promotion'] = $promotion_nid;
            $data['skus'] = $chunk;
            $promotion_detach_queue->createItem($data);
            $output['detached_message'] = t(
              'Skus @skus queued up to detach promotion rule: @rule_id',
              [
                '@skus' => implode(',', $data['skus']),
                '@rule_id' => $promotion['rule_id'],
              ]
            );
          }
        }
      }
      else {
        // Create promotions node using Metadata from Promotions Object.
        $promotion_node = $this->syncPromotionWithConnectorResponse($promotion);
      }

      // Attach promotions to skus.
      if ($promotion_node && (!empty($fetched_promotion_sku_attach_data))) {
        $data['promotion'] = $promotion_node->id();
        $chunks = array_chunk($fetched_promotion_sku_attach_data, $acm_promotion_attach_batch_size);
        foreach ($chunks as $chunk) {
          $data['skus'] = $chunk;
          $promotion_attach_queue->createItem($data);
          $output['attached_message'] = t(
            'Skus @skus queued up to attach promotion rule: @rule_id',
            [
              '@skus' => implode(',', array_keys($fetched_promotion_sku_attach_data)),
              '@rule_id' => $promotion['rule_id'],
            ]
          );
        }
      }

      $this->logger->notice($this->t('Promotion `@node` having rule_id:@rule_id created or updated successfully with @attach items in attach queue and @detach items in detach queue.', [
        '@node' => $promotion_node->getTitle(),
        '@rule_id' => $promotion['rule_id'],
        '@attach' => !empty($fetched_promotion_sku_attach_data) ? count($fetched_promotion_sku_attach_data) : 0,
        '@detach' => !empty($detach_promotion_skus) ? count($detach_promotion_skus) : 0,
      ]));
    }

    return $output;
  }

  /**
   * Removes the given promotion from SKU entity.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   SKU Entity.
   * @param int $nid
   *   Promotion node id.
   */
  public function removeOrphanPromotionFromSku(SKU $sku, int $nid) {
    $promotion_detach_item[] = ['target_id' => $nid];
    $sku_promotions = $sku->get('field_acm_sku_promotions')->getValue();
    $sku_promotions = array_udiff($sku_promotions, $promotion_detach_item, function ($array1, $array2) {
      return $array1['target_id'] - $array2['target_id'];
    });
    $sku->get('field_acm_sku_promotions')->setValue($sku_promotions);
    $sku->save();
    // Update Sku Translations.
    $translation_languages = $sku->getTranslationLanguages(TRUE);
    if (!empty($translation_languages)) {
      foreach ($translation_languages as $langcode => $language) {
        $sku_entity_translation = $sku->getTranslation($langcode);
        $sku_entity_translation->get('field_acm_sku_promotions')->setValue($sku_promotions);
        $sku_entity_translation->save();
      }
    }
  }

}
