<?php

/**
 * @file
 * Provides drush commands related to Product position.
 */

use Drupal\taxonomy\Entity\Term;

/**
 * Implements hook_drush_command().
 */
function acm_sku_position_drush_command() {
  $commands = [];

  $commands['position-sync'] = [
    'description' => 'Drush command to sync sku product position based on category.',
    'aliases' => ['aapps'],
    'arguments'   => [
      'position_type' => 'Name of the position type.',
    ],
    'examples' => [
      'drush aapps' => 'Sync product position based on category, by default "position".',
      'drush aapps myargument' => 'Sync product position based on category, by "myargument".',
    ],
  ];

  return $commands;
}

/**
 * Sync product position based on category.
 */
function drush_acm_sku_position_position_sync($position_type = 'position') {
  drush_print(dt('Product position sync in progress...'));
  // Get root category configuration.
  $filter_root_category = \Drupal::config('acm.connector')->get('filter_root_category');

  $database = \Drupal::database();

  // Get all product category terms.
  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('acm_product_category');
  foreach ($terms as $term) {
    // Skip root category if configured in settings.
    if ($filter_root_category && isset($term->parents[0]) && $term->parents[0] == '0') {
      continue;
    }

    // Find the magento commerce id from the term. Skip if not found.
    $commerce_id = Term::load($term->tid)->get('field_commerce_id')->value;
    if (!$commerce_id) {
      continue;
    }

    // Get product position for this category from Magento.
    $response = \Drupal::service('acm.api')->getProductPosition($commerce_id);
    if (!is_array($response)) {
      continue;
    }

    // Skip sync if error is found in the response for a particular category.
    if (is_array($response) && isset($response['message'])) {
      drush_print(dt("Error in position sync for @name (tid: @tid). Response: @message", [
        '@name' => $term->name,
        '@tid' => $term->tid,
        '@message' => $response['message'],
      ]));
      continue;
    }

    // Start product position sync for this category.
    drush_print(dt("Product position sync for @name (tid: @tid) in progress...", [
      '@name' => $term->name,
      '@tid' => $term->tid,
    ]));

    // Delete existing records of position for this category.
    $database->delete('acm_sku_position')
      ->condition('tid', $term->tid)
      ->condition('position_type', $position_type)
      ->execute();

    // Get all skus from the response.
    $skus = array_column($response, 'sku');
    if (empty($skus)) {
      continue;
    }

    // Get all product nids from skus.
    $query = $database->select('node__field_skus', 'n');
    $query->fields('n', ['field_skus_value', 'entity_id']);
    $query->condition('n.bundle', 'acm_product');
    $query->condition('n.field_skus_value', $skus, 'IN');
    $nids = $query->execute()->fetchAllKeyed();
    // Skip if not product found for any sku.
    if (empty($nids)) {
      continue;
    }

    $is_data_available = FALSE;
    $query = $database->insert('acm_sku_position')
      ->fields(['nid', 'tid', 'position', 'position_type']);
    foreach ($response as $product_position) {
      if (isset($nids[$product_position['sku']])) {
        // Insert new position data for the product.
        $record = [
          'nid' => $nids[$product_position['sku']],
          'tid' => $term->tid,
          'position' => $product_position['position'],
          'position_type' => $position_type,
        ];
        $is_data_available = TRUE;
        $query->values($record);
      }
    }

    if ($is_data_available) {
      $query->execute();
    }
  }

  // Allow other modules to take action after position sync finished.
  \Drupal::moduleHandler()->invokeAll('acm_sku_position_sync_finished');

  drush_print(dt('Product position sync completed!'));
}
