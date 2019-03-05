<?php

namespace Drupal\acm\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class CommerceUserPasswordResetForm.
 *
 * @package Drupal\acm\Form
 */
class CommerceUserPasswordResetForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_user_password_reset_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $email
   *   The base64 encoded email address of the user requesting a password reset.
   * @param string $expiration_date
   *   Formatted expiration date for the login link, or NULL if the link does
   *   not expire.
   * @param int $timestamp
   *   The current timestamp.
   * @param string $hash
   *   Login link hash.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $email = NULL, $expiration_date = NULL, $timestamp = NULL, $hash = NULL) {
    if ($expiration_date) {
      $form['message'] = ['#markup' => $this->t('<p>This is a one-time password reset and will expire on %expiration_date.</p>', ['%expiration_date' => $expiration_date])];
      $form['#title'] = $this->t('Reset password');
    }
    else {
      // No expiration for first time login.
      $form['message'] = ['#markup' => $this->t('<p>This is a one-time password reset.</p>')];
      $form['#title'] = $this->t('Set password');
    }

    $form['new_password'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update password'),
    ];

    $form['#action'] = Url::fromRoute('acm.external_user_password_reset_login', [
      'email' => $email,
      'timestamp' => $timestamp,
      'hash' => $hash,
    ])->toString();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This form works by submitting the hash, timestamp, email, and new
    // password to the acm.external_user_password_reset_login route.
  }

}
