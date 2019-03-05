<?php

/**
 * @file
 * Hooks specific to the acm_sku module.
 */

use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter (add/update/delete) fields to be added to SKU entity.
 *
 * @param array $fields
 *   Fields array.
 */
function hook_acm_sku_base_field_additions_alter(array &$fields = []) {

}

/**
 * Allow modules to do something after base fields are updated.
 *
 * For instance, create facets, create facet blocks,
 *
 * @param array $fields
 *   Fields array.
 * @param string $op
 *   Operation performed on fields.
 */
function hook_acm_sku_base_fields_updated(array $fields, $op = 'add') {

}

/**
 * Alter product node before it is saved during insert or update.
 *
 * Product data from API is passed here to allow other modules to read from
 * the data provided by API and update product node accordingly.
 *
 * @param \Drupal\node\NodeInterface $node
 *   Node to alter.
 * @param array $product
 *   Array containing details provided by API.
 */
function hook_acm_sku_product_node_alter(NodeInterface $node, array $product) {

}

/**
 * Alter Taxonomy Term before it is saved during insert or update.
 *
 * Category data from API is passed here to allow other modules to read from
 * the data provided by API and update Taxonomy Term accordingly.
 *
 * @param \Drupal\taxonomy\TermInterface $term
 *   Taxonomy term to alter.
 * @param array $category
 *   Array containing details provided by API.
 * @param mixed $parent
 *   Parent Taxonomy term to if available.
 */
function hook_acm_sku_commerce_category_alter(TermInterface $term, array $category, $parent) {

}

/**
 * @} End of "addtogroup hooks".
 */
