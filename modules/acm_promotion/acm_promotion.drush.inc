<?php

/**
 * @file
 * Provides drush commands for the promotions of Acquia Commerce connector.
 */

/**
 * Implements hook_drush_command().
 */
function acm_promotion_drush_command() {
  $commands = [];

  $commands['sync-commerce-promotions'] = [
    'description' => 'Run a full synchronization of all commerce promotion records.',
    'aliases' => ['acspm'],
    'options' => [
      'types' => dt('A list of promotion type to synchronize, separated by a comma.'),
    ],
    'examples' => [
      'drush acspm' => 'Run a full synchronization of all available promotions.',
      'drush acspm --types=cart' => 'Run a full synchronization of all available cart promotions.',
    ],
  ];

  return ($commands);
}

/**
 * Drush command callback for the promotion import.
 */
function drush_acm_promotion_sync_commerce_promotions() {
  if ($types = drush_get_option('types')) {
    drush_print(dt('Synchronizing all @types commerce promotions, this usually takes some time...', ['@types' => $types]));
    $types = explode(',', $types);
    $types = array_map('trim', $types);
    \Drupal::service('acm_promotion.promotions_manager')->syncPromotions($types);
  }
  else {
    drush_print(dt('Synchronizing all commerce promotions, this usually takes some time...'));
    \Drupal::service('acm_promotion.promotions_manager')->syncPromotions();
  }

  \Drupal::logger('acm_promotion')->notice('Promotion sync completed.');

  drush_print(dt('Done.'));
}
