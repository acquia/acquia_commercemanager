<?php

namespace Drupal\acm\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element for password and email collection.
 *
 * Formats as an email and password field, which do not validate unless the
 * password is correct.
 *
 * Usage example:
 * @code
 * $form['email_update'] = array(
 *   '#type' => 'acm_email_update',
 *   '#title' => $this->t('Email'),
 * );
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Password
 *
 * @FormElement("acm_email_update")
 */
class AcmEmailUpdate extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#markup' => '',
      '#process' => [
        [$class, 'processEmailUpdate'],
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
      return $element['#default_value'];
    }
    $value = ['email' => '', 'password' => ''];
    // Throw out all invalid array keys; we only allow email and password.
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
   * Expand an acm_email_update field into two text boxes.
   */
  public static function processEmailUpdate(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['email'] = [
      '#type' => 'email',
      '#title' => isset($element['#title']) ? $element['#title'] : '',
      '#value' => isset($element['#value']['email']) ? $element['#value']['email'] : NULL,
      '#required' => $element['#required'],
      '#error_no_message' => TRUE,
    ];
    $element['password'] = [
      '#type' => 'password',
      '#title' => t('Password'),
      '#value' => isset($element['#value']['password']) ? $element['#value']['password'] : NULL,
      '#required' => $element['#required'],
      '#error_no_message' => TRUE,
    ];
    $element['#element_validate'] = [[get_called_class(), 'validateEmailUpdate']];
    $element['#tree'] = TRUE;

    return $element;
  }

  /**
   * Validates an acm_password_update element.
   */
  public static function validateEmailUpdate(&$element, FormStateInterface $form_state, &$complete_form) {
    $email = trim($element['email']['#value']);
    $password = trim($element['password']['#value']);

    if (strlen($email) == 0 || strlen($password) == 0) {
      $form_state->setError($element, t('Email and password fields are required.'));
    }
    else {
      $user_auth = \Drupal::service('acm.auth');
      // Make sure the password they entered is correct.
      try {
        $user_auth->authenticate($element['#default_value'], $password);
      }
      catch (\Exception $e) {
        $form_state->setError($element, t('Password is incorrect. You must enter your current password in order to change your email.'));
      }
    }

    $form_state->setValueForElement($element['password'], NULL);
    $form_state->setValueForElement($element, ['email' => $email, 'password' => $password]);

    return $element;
  }

}
