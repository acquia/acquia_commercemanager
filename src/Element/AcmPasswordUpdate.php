<?php

namespace Drupal\acm\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element for double-input of passwords and old password.
 *
 * Formats as a set of password fields, which do not validate unless the two
 * entered passwords match and the old password is entered.
 *
 * Properties:
 * - #size: The size of the input element in characters.
 *
 * Usage example:
 * @code
 * $form['pass'] = array(
 *   '#type' => 'acm_password_update',
 *   '#title' => $this->t('Password'),
 *   '#size' => 25,
 * );
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Password
 *
 * @FormElement("acm_password_update")
 */
class AcmPasswordUpdate extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#markup' => '',
      '#process' => [
        [$class, 'processPasswordUpdate'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE) {
      $element += ['#default_value' => []];
      return $element['#default_value'] + [
        'pass1' => '',
        'pass2' => '',
        'oldpass' => '',
      ];
    }
    $value = ['pass1' => '', 'pass2' => '', 'oldpass' => ''];
    // Throw out all invalid array keys; we only allow pass1 and pass2.
    foreach ($value as $allowed_key => $default) {
      // These should be strings, but allow other scalars since they might be
      // valid input in programmatic form submissions. Any nested array values
      // are ignored.
      if (isset($input[$allowed_key]) && is_scalar($input[$allowed_key])) {
        $value[$allowed_key] = (string) $input[$allowed_key];
      }
    }
    return $value;
  }

  /**
   * Expand an acm_password_update field into three text boxes.
   */
  public static function processPasswordUpdate(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['oldpass'] = [
      '#type' => 'password',
      '#title' => t('Old Password'),
      '#value' => empty($element['#value']) ? NULL : $element['#value']['oldpass'],
      '#required' => $element['#required'],
      '#error_no_message' => TRUE,
    ];
    $element['pass1'] = [
      '#type' => 'password',
      '#title' => t('Password'),
      '#value' => empty($element['#value']) ? NULL : $element['#value']['pass1'],
      '#required' => $element['#required'],
      '#attributes' => ['class' => ['password-field', 'js-password-field']],
      '#error_no_message' => TRUE,
    ];
    $element['pass2'] = [
      '#type' => 'password',
      '#title' => t('Confirm password'),
      '#value' => empty($element['#value']) ? NULL : $element['#value']['pass2'],
      '#required' => $element['#required'],
      '#attributes' => ['class' => ['password-confirm', 'js-password-confirm']],
      '#error_no_message' => TRUE,
    ];
    $element['#element_validate'] = [[get_called_class(), 'validatePasswordUpdate']];
    $element['#tree'] = TRUE;

    if (isset($element['#size'])) {
      $element['pass1']['#size'] = $element['pass2']['#size'] + $element['oldpass']['#size'] = $element['#size'];
    }

    return $element;
  }

  /**
   * Validates an acm_password_update element.
   */
  public static function validatePasswordUpdate(&$element, FormStateInterface $form_state, &$complete_form) {
    $pass1 = trim($element['pass1']['#value']);
    $pass2 = trim($element['pass2']['#value']);
    $oldpass = trim($element['oldpass']['#value']);
    if (strlen($pass1) > 0 || strlen($pass2) > 0) {
      if (strcmp($pass1, $pass2)) {
        $form_state->setError($element, t('The specified passwords do not match.'));
      }
      elseif (empty($oldpass)) {
        $form_state->setError($element, t('Old password is required.'));
      }
    }
    elseif ($element['#required'] && $form_state->getUserInput()) {
      $form_state->setError($element, t('Password field is required.'));
    }

    $form_state->setValueForElement($element['pass1'], NULL);
    $form_state->setValueForElement($element['pass2'], NULL);
    $form_state->setValueForElement($element, ['password' => $pass1, 'password_old' => $oldpass]);

    return $element;
  }

}
