<?php

namespace Drupal\acm_sku_stock\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class StockSettingsForm.
 *
 * @package Drupal\acm_sku_stock\Form
 *
 * @ingroup acm_sku_stock
 */
class StockSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acm_sku_stock.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acm_sku_stock_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $sku_settings = $this->config('acm_sku_stock.settings');
    $sku_settings->set('low_stock', $form_state->getValue('low_stock'));
    $sku_settings->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $sku_settings = $this->config('acm_sku.settings');

    $form['low_stock'] = [
      '#type' => 'number',
      '#title' => $this->t('Low Stock'),
      '#description' => $this->t('Stock quantity below which product should be considered as having low stock.'),
      '#default_value' => $sku_settings->get('low_stock'),
    ];

    return $form;
  }

}
