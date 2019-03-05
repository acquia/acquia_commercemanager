<?php

namespace Drupal\acm_sku\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SKUTypeForm.
 *
 * @package Drupal\acm_sku\Form
 */
class SKUTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $sku_type = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $sku_type->label(),
      '#description' => $this->t("Label for the SKU type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $sku_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\acm_sku\Entity\SKUType::load',
      ],
      '#disabled' => !$sku_type->isNew(),
    ];

    if ($sku_type_id = $sku_type->id()) {
      $plugin_manager = \Drupal::service('plugin.manager.sku');
      $plugin = $plugin_manager->pluginInstanceFromType($sku_type_id);
      $form += $plugin->decorateSettingsForm($form, $form_state, $sku_type);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $sku_type = $this->entity;

    if ($sku_type_id = $sku_type->id()) {
      $plugin_manager = \Drupal::service('plugin.manager.sku');
      $plugin = $plugin_manager->pluginInstanceFromType($sku_type_id);
      $plugin->saveSettingsForm($form, $form_state, $sku_type);
    }

    $status = $sku_type->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label SKU type.', [
          '%label' => $sku_type->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label SKU type.', [
          '%label' => $sku_type->label(),
        ]));
    }

    $form_state->setRedirectUrl($sku_type->urlInfo('collection'));
  }

}
