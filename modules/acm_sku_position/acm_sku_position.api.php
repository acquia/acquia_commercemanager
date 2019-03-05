<?php

/**
 * @file
 * Hooks specific to the acm_sku_position module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow other modules to take action after position sync finished.
 */
function hook_acm_sku_position_sync_finished() {

}

/**
 * Allow other modules to skip terms from position sync.
 *
 * @param array $terms
 *   Terms array (passed by reference) to alter.
 */
function hook_acm_sku_position_sync_alter(array &$terms) {
  if (!empty($terms)) {
    unset($terms[0]);
  }
}

/**
 * @} End of "addtogroup hooks".
 */
