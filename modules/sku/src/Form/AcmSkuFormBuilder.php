<?php

namespace Drupal\acm_sku\Form;

use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;

/**
 * TODO: remove this class once https://www.drupal.org/node/766146 is fixed.
 *
 * Class AcmSkuFormBuilder.
 */
class AcmSkuFormBuilder extends FormBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId($form_arg, FormStateInterface &$form_state) {
    $form_id = parent::getFormId($form_arg, $form_state);

    // Get the build info.
    $build_info = $form_state->getBuildInfo();

    // Check if we have 0th index set in build info.
    if (isset($build_info['args'], $build_info['args'][0])) {
      $sku = $build_info['args'][0];

      // Check if argument is of type SKU.
      if (get_class($sku) == 'Drupal\acm_sku\Entity\SKU') {
        // Set the base_form_id.
        $build_info['base_form_id'] = $form_id;
        $form_state->setBuildInfo($build_info);

        // Prepare additional form_id parts using the entity.
        $additional_form_id_parts = [];
        $additional_form_id_parts[] = $sku->getEntityTypeId();
        $additional_form_id_parts[] = $sku->getType();
        $additional_form_id_parts[] = $sku->id();

        // Prepare the new form id.
        $form_id = implode('_', $additional_form_id_parts) . '__' . $form_id;
      }
    }

    return $form_id;
  }

}
