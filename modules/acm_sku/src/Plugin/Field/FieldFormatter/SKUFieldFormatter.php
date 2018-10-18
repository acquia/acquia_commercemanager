<?php

namespace Drupal\acm_sku\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\acm_sku\Entity\SKU;

/**
 * Plugin implementation of the 'sku_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "sku_formatter",
 *   label = @Translation("SKU Formatter"),
 *   field_types = {
 *     "sku"
 *   }
 * )
 */
class SKUFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'view_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $view_modes = \Drupal::service('entity_display.repository')
      ->getViewModes('acm_sku');
    $options = [];

    foreach ($view_modes as $id => $view_mode) {
      $options[$id] = $view_mode['label'];
    }

    return [
      'view_mode' => [
        '#type' => 'select',
        '#title' => t('View mode of the SKU entity.'),
        '#options' => $options,
        '#required' => TRUE,
      ],
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = t('SKU view mode: @view_mode', ['@view_mode' => $this->getSetting('view_mode')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $sku = $this->viewValue($item);

      if (empty($sku)) {
        continue;
      }

      $view_builder = \Drupal::entityTypeManager()
        ->getViewBuilder($sku->getEntityTypeId());

      $elements[$delta] = $view_builder
        ->view($sku, $this->getSetting('view_mode'), $langcode);
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    $sku = $item->value;

    $query = \Drupal::entityQuery('acm_sku')
      ->condition('sku', $sku);

    $ids = $query->execute();

    if (empty($ids)) {
      \Drupal::logger('acm_sku')
        ->notice(t('No SKU found for @sku.', ['@sku' => $sku]));
      return '';
    }
    elseif (count($ids) > 1) {
      \Drupal::logger('acm_sku')
        ->notice(t('More than one SKU found for @sku. Using first.', ['@sku' => $sku]));
    }

    $sku_id = reset($ids);
    $sku = SKU::load($sku_id);

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    if ($sku->hasTranslation($langcode)) {
      $sku = $sku->getTranslation($langcode);
    }

    return $sku;
  }

}
