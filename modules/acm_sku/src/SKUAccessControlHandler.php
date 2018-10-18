<?php

namespace Drupal\acm_sku;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the SKU entity.
 *
 * @see \Drupal\acm_sku\Entity\SKU.
 */
class SKUAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view sku entity');

      case 'edit':
        return AccessResult::allowedIfHasPermission($account, 'edit sku entity');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete sku entity');
    }
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add sku entity');
  }

}
