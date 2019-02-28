<?php

namespace Drupal\acm_sku;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Provides a service for product synchronization to load categories.
 *
 * @ingroup acm_sku
 */
class CategoryRepository implements CategoryRepositoryInterface {

  /**
   * Loaded Taxonomy Terms By Commerce ID.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  private $terms = [];

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
   * Drupal Config Factory Instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManager object.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   LoggerFactory object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactory $logger_factory, ConfigFactoryInterface $config_factory) {
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->vocabStorage = $entity_type_manager->getStorage('taxonomy_vocabulary');
    $this->logger = $logger_factory->get('acm_sku');
    $this->configFactory = $config_factory;

    $category_vid = $config_factory
      ->get('acm.connector')
      ->get('category_vid');

    if ($category_vid) {
      $this->loadVocabulary($category_vid);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadCategoryTerm($commerce_id) {

    if (!$this->vocabulary) {
      throw new \RuntimeException('No Taxonomy vocabulary set.');
    }

    $commerce_id = (int) $commerce_id;
    if ($commerce_id < 1) {
      $this->logger->error(
        'Invalid category id @cid',
        ['@cid' => $commerce_id]
      );

      return (NULL);
    }

    if (isset($this->terms[$commerce_id])) {
      return ($this->terms[$commerce_id]);
    }

    $query = $this->termStorage->getQuery();
    $group = $query->andConditionGroup()
      ->condition('field_commerce_id', $commerce_id)
      ->condition('vid', $this->vocabulary->id());
    $query->condition($group);

    $tids = $query->execute();

    if (count($tids) == 0) {
      return (NULL);
    }
    elseif (count($tids) > 1) {
      $this->logger->error('Multiple terms found for category id @cid (only one will be returned)', ['@cid' => $commerce_id]);
    }

    $term = $this->termStorage->load(array_shift($tids));
    $this->terms[$commerce_id] = $term;

    return ($term);
  }

  /**
   * {@inheritdoc}
   */
  public function setVocabulary($vocabulary) {

    $this->loadVocabulary($vocabulary);
    return ($this);
  }

  /**
   * LoadVocabulary.
   *
   * Load a taxonomy vocabulary from a vid.
   *
   * @param string $vocabulary
   *   Vocabulary VID.
   *
   * @throws \InvalidArgumentException
   */
  private function loadVocabulary($vocabulary) {

    if (!strlen($vocabulary)) {
      throw new \InvalidArgumentException(
        'CategoryRepository requires a taxonomy vocabulary machine name.'
      );
    }

    $vocab = $this->vocabStorage->load($vocabulary);

    if (!$vocab || !$vocab->id()) {
      throw new \InvalidArgumentException(sprintf(
        'CategoryRepository unable to locate vocabulary %s.',
        $vocabulary
      ));
    }

    $this->vocabulary = $vocab;
  }

}
