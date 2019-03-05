<?php

namespace Drupal\acm_sku;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class SKUFieldsManager.
 *
 * @package Drupal\acm_sku
 */
class SKUFieldsManager {

  const BASE_FIELD_ADDITIONS_CONFIG = 'acm_sku.base_field_additions';

  /**
   * The Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The Module Handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The Entity Definition Update Manager service.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  private $entityDefinitionUpdateManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * SKUFieldsManager constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The Module Handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_update_manager
   *   The Entity Definition Update Manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Logger.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              ModuleHandlerInterface $module_handler,
                              EntityTypeManagerInterface $entity_type_manager,
                              EntityDefinitionUpdateManagerInterface $entity_definition_update_manager,
                              LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDefinitionUpdateManager = $entity_definition_update_manager;
    $this->logger = $logger;
  }

  /**
   * Function to add all new field definitions from custom modules to SKU Base.
   */
  public function addFields() {
    $this->logger->info('addFields() invoked to add newly added base fields to SKU.');
    // Get all the additional fields from all custom modules.
    $fields = $this->getAllCustomFields();

    // Store the fields in config.
    $config = $this->configFactory->getEditable(self::BASE_FIELD_ADDITIONS_CONFIG);
    $existing_fields = $config->getRawData();

    $fields = array_diff_key($fields, $existing_fields);
    $existing_fields = array_merge($existing_fields, $fields);
    $config->setData($existing_fields)->save();

    if ($fields) {
      $this->logger->info('Adding new fields %fields.', [
        '%fields' => json_encode($fields),
      ]);

      // Apply entity updates, we will read from config and add/update fields.
      $this->entityDefinitionUpdateManager->applyUpdates();

      // Allow other modules to take some action after the fields are added.
      $this->moduleHandler->invokeAll('acm_sku_base_fields_updated', [$fields, 'add']);
    }
    else {
      $this->logger->warning('No new fields found to add.');
    }

  }

  /**
   * Remove base field from SKU entity.
   *
   * Note: Calling function needs to take care of clearing data.
   *
   * @param string $field_code
   *   Field code to remove.
   */
  public function removeField($field_code) {
    $config = $this->configFactory->getEditable(self::BASE_FIELD_ADDITIONS_CONFIG);
    $fields = $config->getRawData();

    if (!isset($fields[$field_code])) {
      return;
    }

    $field = $fields[$field_code];
    unset($fields[$field_code]);
    $config->setData($fields)->save();

    $this->entityTypeManager->clearCachedDefinitions();
    $this->entityDefinitionUpdateManager->applyUpdates();

    $fields_removed = [
      $field_code => $field,
    ];

    $this->moduleHandler->invokeAll('acm_sku_base_fields_updated', [$fields_removed, 'remove']);
  }

  /**
   * Function to update field definitions for the additional SKU base fields.
   *
   * This will not update the actual field but only additional information used
   * in custom code like field is configurable or not, indexable or not.
   *
   * It will not do anything except updating the config. Be very careful when
   * using this.
   *
   * @param string $field_code
   *   Field code.
   * @param array $field
   *   Field definition.
   *
   * @throws \Exception
   *   Throws exception if field doesn't exist in config.
   */
  public function updateFieldMetaInfo($field_code, array $field) {
    $config = $this->configFactory->getEditable(self::BASE_FIELD_ADDITIONS_CONFIG);
    $existing_fields = $config->getRawData();

    if (empty($existing_fields[$field_code])) {
      throw new \Exception('Field not available, try adding it first.');
    }

    // Checks to avoid errors.
    $field_structure_info = [
      'type',
      'cardinality',
    ];

    foreach ($field_structure_info as $info) {
      if (isset($field[$info]) && $field['type'] != $existing_fields[$field_code]['type']) {
        throw new \Exception('Can not modify field structure.');
      }
    }

    // Need to apply entity updates for following.
    $apply_updates = FALSE;
    $field_labels_info = [
      'label',
      'description',
      'visible_view',
      'visible_form',
      'weight',
    ];

    foreach ($field_labels_info as $info) {
      if (isset($field[$info]) && $field['type'] != $existing_fields[$field_code]['type']) {
        $apply_updates = TRUE;
        break;
      }
    }

    $existing_fields[$field_code] = array_replace($existing_fields[$field_code], $field);
    $config->setData($existing_fields)->save();

    if ($apply_updates) {
      $this->entityDefinitionUpdateManager->applyUpdates();
    }
  }

  /**
   * Get all existing field additions.
   *
   * @return array
   *   Existing field additions.
   */
  public function getFieldAdditions() {
    return $this->configFactory->get(self::BASE_FIELD_ADDITIONS_CONFIG)->getRawData();
  }

  /**
   * Get all fields defined in custom modules.
   *
   * @return array
   *   All fields defined in custom modules.
   */
  private function getAllCustomFields() {
    $fields = [];

    $this->moduleHandler->alter('acm_sku_base_field_additions', $fields);

    foreach ($fields as $field_code => $field) {
      $fields[$field_code] = $this->applyDefaults($field_code, $field);
    }

    return $fields;
  }

  /**
   * Function to apply defaults and complete field definition.
   *
   * @param string $field_code
   *   Field code.
   * @param array $field
   *   Field definition.
   *
   * @return array
   *   Field definition with all defaults applied.
   */
  private function applyDefaults($field_code, array $field) {
    $defaults = $this->getDefaults();

    if (empty($field['source'])) {
      $field['source'] = $field_code;
    }

    // We will always have label, still we do a check to avoid errors.
    if (empty($field['label'])) {
      $field['label'] = $field_code;
    }

    // Add description if empty.
    if (empty($field['description'])) {
      $field['description'] = str_replace('[label]', $field['label'], $defaults['description']);
    }

    // Merge all other defaults.
    $field += $defaults;

    return $field;
  }

  /**
   * Returns an associative [] containing all required fields with defaults set.
   *
   * @returns array
   *   Associative array containing all required values with defaults set.
   */
  private function getDefaults() {
    return [
      // (Required) Label to be used for admin forms and display.
      'label' => '',
      // Soruce field code to use for reading from product data.
      'source' => '',
      // Description of the field to be used in admin forms.
      'description' => '[label] attribute for the product.',
      // (Required) Parent key in the array where to look for data.
      'parent' => 'attributes',
      // Type of the field.
      'type' => 'attribute',
      // Number of values allowed to be stored.
      'cardinality' => 1,
      // (Optional) Should the data be stored as serialized.
      'serialize' => 0,
      // Whether the field is part of configurable options.
      'configurable' => 0,
      // Default weight of the field in form and display.
      'weight' => NULL,
      // Whether the field should be visible while viewing content.
      'visible_view' => 0,
      // Whether the field should be visible in form.
      'visible_form' => 1,
    ];
  }

}
