<?php

namespace Drupal\acm_sku;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Class SKUStorageSchema.
 *
 * Provides custom storage schema in order to add index to acm_sku_field_data.
 */
class SKUStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);
    if ($entity_type->id() == 'acm_sku') {
      $schema['acm_sku_field_data']['indexes'] += [
        'sku' => [['sku', 64]],
        'acm_sku_sku_langcode' => [
          ['sku', 64],
          ['langcode', 10],
        ],
      ];
    }

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDedicatedTableSchema(FieldStorageDefinitionInterface $storage_definition, ContentEntityTypeInterface $entity_type = NULL) {
    // Get default schema.
    $schema = parent::getDedicatedTableSchema($storage_definition, $entity_type);
    $field_name = $storage_definition->getName();
    if ($field_name == 'attributes') {
      // Schema starts with table name key as root, so let's loop through it, so
      // we don't need to guess.
      foreach ($schema as $table => &$data) {
        if (!isset($data['indexes'])) {
          $data['indexes'] = [];
        }
        $data['indexes'] += [
          'acm_sku__attr_join' => [
            'entity_id',
            ['attributes_key', 128],
          ],
          'acm_sku__attr_where' => [
            'entity_id',
            ['attributes_key', 128],
            ['attributes_value', 128],
          ],
        ];
      }
    }
    return $schema;
  }

}
