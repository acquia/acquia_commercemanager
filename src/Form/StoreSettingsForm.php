<?php

namespace Drupal\acm\Form;

use CommerceGuys\Intl\Currency\CurrencyRepository;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class StoreSettingsForm.
 *
 * @package Drupal\acm\Form
 *
 * @ingroup acm
 */
class StoreSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acm_store_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['acm.store', 'acm.currency'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('acm.store')
      ->set('store_id', $form_state->getValue('store_id'))
      ->save();

    $this->config('acm.currency')
      ->set('currency_code', $form_state->getValue('currency_code'))
      ->set('price_range_format_string', $form_state->getValue('price_range_format_string'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('acm.store');
    $form['store_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Store id'),
      '#default_value' => $config->get('store_id'),
    ];

    $config = $this->config('acm.currency');
    // Load all possible currencies.
    $currencyRepository = new CurrencyRepository();
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage();
    $options = $currencyRepository->getList($currentLanguage->getId(), 'en');
    $form['currency_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#default_value' => $config->get('currency_code'),
      '#options' => $options,
      '#description' => $this->t('The currency for @language', ['@language' => $currentLanguage->getName()]),
      '#required' => TRUE,
    ];

    $form['price_range_format_string'] = [
      '#type' => 'textfield',
      '#description' => $this->t("The format string to use for generating price ranges in the admin SKU table. For example 'From @min to @max'. Use @min and @max just like that as placeholders for the actual prices. This is not used on the front end. See the Twig template files for front-end product price formatting, for example docroot/modules/contrib/acm/modules/acm_sku/templates/acm-sku-price--configurable.html.twig"),
      '#title' => $this->t('Price range format'),
      '#required' => TRUE,
      '#default_value' => $config->get('price_range_format_string'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
