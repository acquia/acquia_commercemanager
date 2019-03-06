<?php

namespace Drupal\acm_sku\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Defines the SKU entity.
 *
 * @ContentEntityType(
 *   id = "acm_sku",
 *   label = @Translation("SKU entity"),
 *   handlers = {
 *     "view_builder" = "Drupal\acm_sku\Entity\Controller\SKUViewBuilder",
 *     "list_builder" = "Drupal\acm_sku\Entity\Controller\SKUListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\acm_sku\Form\SKUForm",
 *       "add" = "Drupal\acm_sku\Form\SKUForm",
 *       "edit" = "Drupal\acm_sku\Form\SKUForm",
 *       "delete" = "Drupal\acm_sku\Form\SKUDeleteForm",
 *     },
 *     "access" = "Drupal\acm_sku\SKUAccessControlHandler",
 *     "storage_schema" = "Drupal\acm_sku\SKUStorageSchema",
 *   },
 *   base_table = "acm_sku",
 *   data_table = "acm_sku_field_data",
 *   translatable = TRUE,
 *   common_reference_target = TRUE,
 *   admin_permission = "administer commerce sku entity",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "bundle" = "type",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *     "status" = "status",
 *   },
 *   bundle_entity_type = "acm_sku_type",
 *   bundle_label = @Translation("SKU type"),
 *   links = {
 *     "canonical" = "/admin/commerce/sku/{acm_sku}",
 *     "edit-form" = "/admin/commerce/sku/{acm_sku}/edit",
 *     "delete-form" = "/admin/commerce/sku/{acm_sku}/delete",
 *     "collection" = "/admin/commerce/sku/list"
 *   },
 *   field_ui_base_route = "acm_sku.configuration",
 * )
 */
class SKU extends ContentEntityBase implements SKUInterface {

  /**
   * Processed media array.
   *
   * @var array
   */
  protected $mediaData = [];

  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the user_id entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * Loads a SKU Entity from SKU.
   *
   * @param string $sku
   *   SKU to load.
   * @param string $langcode
   *   Language code.
   * @param bool $log_not_found
   *   Log errors when store not found. Can be false during sync.
   * @param bool $create_translation
   *   Create translation and return if entity available but translation is not.
   *
   * @return SKU|null
   *   Found SKU
   *
   * @throws \Exception
   */
  public static function loadFromSku($sku, $langcode = '', $log_not_found = TRUE, $create_translation = FALSE) {

    $skus_static_cache = &drupal_static(__FUNCTION__, []);

    $is_multilingual = \Drupal::languageManager()->isMultilingual();
    if ($is_multilingual && empty($langcode)) {
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    $static_cache_sku_identifier = $sku . ':' . $langcode;

    // Check if data is available in static cache, return from there.
    // If create translation is true, it means we are doing product sync.
    // For this case we don't want to use any static cache.
    if (isset($skus_static_cache[$static_cache_sku_identifier]) && !$create_translation) {
      return $skus_static_cache[$static_cache_sku_identifier];
    }

    $storage = \Drupal::entityTypeManager()->getStorage('acm_sku');
    $skus = $storage->loadByProperties(['sku' => $sku]);

    if (count($skus) == 0) {
      // We don't log the error while doing sync.
      if ($log_not_found) {
        \Drupal::logger('acm_sku')->error('No SKU found for @sku.', ['@sku' => $sku]);
      }
      return NULL;
    }

    $sku_entity = reset($skus);

    // Sanity check.
    if (!($sku_entity instanceof SKUInterface)) {
      return NULL;
    }

    // Now discard all skus in other languages if there is more than one.
    if ($is_multilingual && count($skus) > 1) {
      // Get rid of undesired languages. Later the first sku is picked up.
      foreach ($skus as $key => $skuEntity) {
        if ($skuEntity->language()->getId() != $langcode) {
          unset($skus[$key]);
        }
      }
    }

    // Now test if there is still more than one sku found.
    // Noting for multiple entries, we just log the error
    // and continue with first sku.
    if (count($skus) > 1) {
      \Drupal::logger('acm_sku')->error('Duplicate SKUs found while loading for @sku.', ['@sku' => $sku]);
    }

    if ($is_multilingual) {
      if ($sku_entity->hasTranslation($langcode)) {
        $sku_entity = $sku_entity->getTranslation($langcode);

        // Set value in static variable.
        // We set in static cache only for proper case, when returning different
        // language or creating translation we can avoid static cache.
        $skus_static_cache[$static_cache_sku_identifier] = $sku_entity;
      }
      elseif ($create_translation) {
        $sku_entity = $sku_entity->addTranslation($langcode, ['sku' => $sku]);
      }
      // We will continue execution with available translation and just log
      // a message. During sync we say don't log messages.
      elseif ($log_not_found) {
        \Drupal::logger('acm_sku')->error('SKU translation not found of @sku for @langcode', ['@sku' => $sku, '@langcode' => $langcode]);
      }
    }
    else {
      // Set value in static variable directly if not a multi-lingual site.
      $skus_static_cache[$static_cache_sku_identifier] = $sku_entity;
    }

    return $sku_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setChangedTime($timestamp) {
    $this->set('changed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTimeAcrossTranslations() {
    $changed = $this->getUntranslated()->getChangedTime();
    foreach ($this->getTranslationLanguages(FALSE) as $language) {
      $translation_changed = $this->getTranslation($language->getId())->getChangedTime();
      $changed = max($translation_changed, $changed);
    }
    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * Get all the cross sell sku values of current entity.
   */
  public function getCrossSell() {
    return $this->get('crosssell')->getValue();
  }

  /**
   * Get all the upsell sku values of current entity.
   */
  public function getUpSell() {
    return $this->get('upsell')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Define the field properties here.
   *
   * Field name, type and size determine the table structure.
   *
   * In addition, we can define how the field and its content can be manipulated
   * in the GUI. The behaviour of the widgets used can be determined here.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t("The SKU's human-friendly name."))
      ->setTranslatable(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['sku'] = BaseFieldDefinition::create('string')
      ->setLabel(t('SKU'))
      ->setDescription(t('The SKU.'))
      ->setTranslatable(TRUE)
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -11,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -11,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['price'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Display Price'))
      ->setDescription(t('Display Price of this SKU.'))
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['special_price'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Special Price'))
      ->setDescription(t('Special Price of this SKU.'))
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['final_price'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Final Price'))
      ->setDescription(t('Final Price of this SKU.'))
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['crosssell'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Cross sell SKU'))
      ->setDescription(t('Reference to all Cross sell SKUs.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 5,
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE);

    $fields['upsell'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Up sell SKU'))
      ->setDescription(t('Reference to all up sell SKUs.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 6,
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE);

    $fields['related'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Related SKU'))
      ->setDescription(t('Reference to all related SKUs.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 7,
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE);

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setDescription(t('Product image'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'image',
        'weight' => -11,
      ])
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['media'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Media'))
      ->setDescription(t('Store all the media files info.'))
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['attributes'] = BaseFieldDefinition::create('key_value_long')
      ->setLabel(t('Attributes'))
      ->setDescription(t('Non-Drupal native product data.'))
      ->setTranslatable(TRUE)
      ->setCardinality(-1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'))
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'))
      ->setTranslatable(TRUE);

    $fields['attribute_set'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Attribute Set'))
      ->setDescription(t('Attribtue set for the SKU.'))
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Whether the SKU is available or not.'))
      ->setDefaultValue(TRUE);

    $fields['product_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Product Id'))
      ->setDescription(t('Commerce Backend Product Id.'))
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Get all the fields added by other modules and add them as base fields.
    $additionalFields = \Drupal::service('acm_sku.fields_manager')->getFieldAdditions();

    // Get the default weight increment value from variables.
    $defaultWeightIncrement = \Drupal::state()
      ->get('acm_sku.base_field_weight_increment', 20);

    // Check if we have additional fields to be added as base fields.
    if (!empty($additionalFields) && is_array($additionalFields)) {
      foreach ($additionalFields as $machine_name => $field_info) {
        // Initialise the field variable.
        $field = NULL;

        // Showing the fields at the bottom.
        $weight = $defaultWeightIncrement + count($fields);

        switch ($field_info['type']) {
          case 'attribute':
          case 'string':
            $field = BaseFieldDefinition::create('string');

            if ($field_info['visible_view']) {
              $field->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => $weight,
              ]);
            }

            if ($field_info['visible_form']) {
              $field->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => $weight,
              ]);
            }
            break;

          case 'text_long':
            $field = BaseFieldDefinition::create('text_long');

            if ($field_info['visible_view']) {
              $field->setDisplayOptions('view', [
                'label' => 'hidden',
                'type' => 'text_default',
                'weight' => $weight,
              ]);
            }

            if ($field_info['visible_form']) {
              $field->setDisplayOptions('form', [
                'type' => 'text_textfield',
                'weight' => $weight,
              ]);
            }
            break;
        }

        // Check if we don't have the field type defined yet.
        if (empty($field)) {
          throw new \RuntimeException('Field type not defined yet, please contact TA.');
        }

        // We want to allow field labels to be translatable.
        // Since we try to do this dynamically, we need to use t() with
        // variable.
        // @codingStandardsIgnoreLine
        $field->setLabel(new TranslatableMarkup($field_info['label']));

        // Update cardinality with default value if empty.
        $field_info['description'] = empty($field_info['description']) ? 1 : $field_info['description'];
        $field->setDescription($field_info['description']);

        $field->setTranslatable(TRUE);

        // Update cardinality with default value if empty.
        $field_info['cardinality'] = empty($field_info['cardinality']) ? 1 : $field_info['cardinality'];
        $field->setCardinality($field_info['cardinality']);

        $field->setDisplayConfigurable('form', 1);
        $field->setDisplayConfigurable('view', 1);

        // We will use attr prefix to avoid conflicts with default base fields.
        $fields['attr_' . $machine_name] = $field;
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getSku() {
    return $this->get('sku')->value;
  }

  /**
   * Returns the locale-aware display formatted price like this '$1,234.56'.
   *
   * Calls the price formatting function of the SKU Type.
   */
  public function getAdminGridDisplayFormattedPrice(bool $returnOriginalPrice = FALSE) {
    $skuTypePlugin = $this->getPluginInstance();
    if ($skuTypePlugin === NULL) {
      /** @var \Drupal\acm_sku\AcquiaCommerce\SKUPluginManager $plugin_manager */
      $plugin_manager = \Drupal::service('plugin.manager.sku');
      $skuTypePlugin = $plugin_manager->pluginInstanceFromType('simple');
    }
    // Use the type plugin's price formatter.
    $formattedPrice = $skuTypePlugin->getAdminGridDisplayFormattedPrice($this, $returnOriginalPrice);

    return $formattedPrice;
  }

  /**
   * Returns the locale-aware display formatted price like this '$1,234.56'.
   *
   * Calls the price formatting function of the SKU Type.
   */
  public function getNumberFormattedPrice(bool $returnOriginalPrice = FALSE) {
    $skuTypePlugin = $this->getPluginInstance();
    if ($skuTypePlugin === NULL) {
      /** @var \Drupal\acm_sku\AcquiaCommerce\SKUPluginManager $plugin_manager */
      $plugin_manager = \Drupal::service('plugin.manager.sku');
      $skuTypePlugin = $plugin_manager->pluginInstanceFromType('simple');
    }
    // Use the type plugin's price formatter.
    $formattedPrice = $skuTypePlugin->getNumberFormattedPrice($this, $returnOriginalPrice);

    return $formattedPrice;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginInstance() {
    $plugin_manager = \Drupal::service('plugin.manager.sku');
    $plugin_definition = $plugin_manager->pluginFromSku($this);

    if (empty($plugin_definition)) {
      return NULL;
    }

    return $plugin_manager->createInstance($plugin_definition['id']);
  }

  /**
   * Function to return first image from media files for a SKU.
   *
   * @return array
   *   Array of media files.
   */
  public function getThumbnail() {
    $media = $this->getMedia();

    // We loop through all the media items and return the first image.
    foreach ($media as $media_item) {
      if (isset($media_item['media_type']) && $media_item['media_type'] == 'image') {
        return $media_item;
      }
    }

    return [];
  }

  /**
   * Function to return media files for a SKU.
   *
   * @param bool $download_media
   *   Whether to download media or not.
   * @param bool $reset
   *   Flag to reset cache and generate array again from serialized string.
   *
   * @return array
   *   Array of media files.
   */
  public function getMedia($download_media = TRUE, $reset = FALSE) {
    if (!$reset && !empty($this->mediaData)) {
      return $this->mediaData;
    }

    if ($media_data = $this->get('media')->getString()) {
      $update_sku = FALSE;

      $media_data = unserialize($media_data);

      if (empty($media_data)) {
        return [];
      }

      foreach ($media_data as &$data) {
        // We don't want to show disabled images.
        if (isset($data['disabled']) && $data['disabled']) {
          continue;
        }

        $media_item = $this->processMediaItem($update_sku, $data, $download_media);

        $this->mediaData[] = $media_item;
      }

      if ($update_sku) {
        $this->get('media')->setValue(serialize($media_data));
        $this->save();
      }
    }

    return $this->mediaData;
  }

  /**
   * Function to get processed media item with File entity in array.
   *
   * @param bool $update_sku
   *   Flag to specify if SKU should be updated or not.
   *   Update is done in parent function, here we only update the flag.
   * @param array $data
   *   Media item array.
   * @param bool $download
   *   Flag to specify if we should download missing images or not.
   *
   * @return array|null
   *   Processed media item or null if some error occurred.
   */
  protected function processMediaItem(&$update_sku, array &$data, $download = FALSE) {
    $media_item = $data;

    // Processing is required only for media type image as of now.
    if (isset($data['media_type']) && $data['media_type'] == 'image') {
      if (!empty($data['fid'])) {
        $file = File::load($data['fid']);
        if (!($file instanceof FileInterface)) {
          \Drupal::logger('acm_sku')->error('Empty file object for fid @fid on sku "@sku"', [
            '@fid' => $data['fid'],
            '@sku' => $this->getSku(),
          ]);

          unset($data['fid']);

          // Try to download again if download flag is set to true.
          if ($download) {
            return $this->processMediaItem($update_sku, $data, TRUE);
          }
        }
      }
      elseif ($download) {
        try {
          // Prepare the File object when we access it the first time.
          $file = $this->downloadMediaImage($data);
          $update_sku = TRUE;
        }
        catch (\Exception $e) {
          \Drupal::logger('acm_sku')->error($e->getMessage());
          return NULL;
        }
      }

      if ($file instanceof FileInterface) {
        $data['fid'] = $file->id();
        $media_item['fid'] = $data['fid'];
        $media_item['file'] = $file;
      }

      if (empty($data['label'])) {
        $media_item['label'] = $this->label();
      }

      return $media_item;
    }
  }

  /**
   * Function to save image file into public dir.
   *
   * @param array $data
   *   File data.
   *
   * @return \Drupal\file\Entity\File
   *   File object.
   *
   * @throws \Exception
   *   If media fails to be downloaded.
   */
  protected function downloadMediaImage(array $data) {
    // Preparing args for all info/error messages.
    $args = ['@file' => $data['file'], '@sku_id' => $this->id()];

    // Download the file contents.
    try {
      $file_data = \Drupal::httpClient()->get($data['file'])->getBody();
    }
    catch (RequestException $e) {
      watchdog_exception('acq_commerce', $e);
    }
    // Check to ensure empty file is not saved in SKU.
    if (empty($file_data)) {
      throw new \Exception(new FormattableMarkup('Failed to download file "@file" for SKU id @sku_id.', $args));
    }

    // Get the path part in the url, remove hostname.
    $path = parse_url($data['file'], PHP_URL_PATH);

    // Remove slashes from start and end.
    $path = trim($path, '/');

    // Get the file name.
    $file_name = basename($path);

    // Prepare the directory path.
    $directory = 'public://media/' . str_replace('/' . $file_name, '', $path);

    // Prepare the directory.
    file_prepare_directory($directory, FILE_CREATE_DIRECTORY);

    // Save the file as file entity.
    /** @var \Drupal\file\Entity\File $file */
    if ($file = file_save_data($file_data, $directory . '/' . $file_name, FILE_EXISTS_REPLACE)) {
      \Drupal::logger('acm_sku')
        ->debug('File @url downloaded to @file for SKU @sku',
          [
            '@url' => $data['file'],
            '@file' => $file->id(),
            '@sku' => $this->id(),
          ]);
      return $file;
    }
    else {
      throw new \Exception(new FormattableMarkup('Failed to save file "@file" for SKU id @sku_id.', $args));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refreshStock() {
    /** @var \Drupal\acm_sku\AcquiaCommerce\SKUPluginBase $plugin */
    $plugin = $this->getPluginInstance();
    $plugin->refreshStock($this);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Delete media files.
    foreach ($entities as $entity) {
      foreach ($entity->getMedia(FALSE) as $media) {
        if ($media['file'] instanceof FileInterface) {
          $media['file']->delete();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    /** @var \Drupal\acm_sku\AcquiaCommerce\SKUPluginBase $plugin */
    $plugin = $this->getPluginInstance();
    // Get parent skus(if any) for the sku.
    $parent_skus = array_values($plugin->getAllParentSkus($this->getSku()));
    // Prepare cache tags of parent sku.
    $parent_skus = array_map(function ($parent_sku) {
      return 'acm_sku:' . $parent_sku;
    }, $parent_skus);

    // @Todo: Add the tags of the display node as well.
    return Cache::mergeTags($cache_tags, $parent_skus);
  }

}
