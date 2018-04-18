<?php

namespace Drupal\acm\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides an ACM Composite form element.
 *
 * ACM Composite form elements contains a field that either displays a plain-
 * text value or a form element, along with optional buttons.
 *
 * @FormElement("acm_composite")
 */
class AcmComposite extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#markup' => '',
      '#process' => [
        [$class, 'processAcmComposite'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#field_type' => '',
      '#display_value' => '',
      '#form_mode' => TRUE,
      '#buttons' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // This should be a string, but allow other scalars since they might be
      // valid input in programmatic form submissions.
      if (!is_scalar($input)) {
        $input = '';
      }
      return str_replace(["\r", "\n"], '', $input);
    }
    return NULL;
  }

  /**
   * Expand a acm_address field into multiple inputs.
   */
  public static function processAcmComposite(&$element, FormStateInterface $form_state, &$complete_form) {
    if ($element['#form_mode'] === FALSE) {
      // Form-mode is off, so show the display value instead.
      $display_value = !empty($element['#display_value']) ? $element['#display_value'] : $element['#default_value'];
      $element['display_value'] = [
        '#prefix' => '<div class="form-item-display-value">',
        '#plain_text' => $display_value,
        '#suffix' => '</div>',
      ];
    }
    else {
      // Form-mode is on, so build a field for it.
      $element['form_value'] = [
        '#type' => $element['#field_type'],
      ];
      if (isset($element['#default_value'])) {
        $element['form_value']['#default_value'] = $element['#default_value'];
      }
      if (isset($element['#required'])) {
        $element['form_value']['#required'] = $element['#required'];
      }
      if (isset($element['#placeholder'])) {
        $element['form_value']['#placeholder'] = $element['#placeholder'];
      }
      if (isset($element['#options'])) {
        $element['form_value']['#options'] = $element['#options'];
      }
      if (isset($element['#empty_option'])) {
        $element['form_value']['#empty_option'] = $element['#empty_option'];
      }

      // Set #parents to the original element so that we get a flat value
      // in $form_state->getValues().
      $element['form_value']['#parents'] = $element['#parents'];
    }

    if (!empty($element['#buttons'])) {
      // Set #parents to the original element so that we get a flat value
      // in $form_state->getValues().
      $element['actions'] = [
        '#type' => 'actions',
        '#parents' => $element['#parents'],
      ];
      foreach ($element['#buttons'] as $name => &$button) {
        $button['#parents'] = $element['#parents'];
        $element['actions'][$name] = $button;
      }
    }

    return $element;
  }

}
