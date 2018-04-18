<?php

namespace Drupal\acm_customer\Plugin\CustomerForm;

use Drupal\acm\Element\AcmTitleSelect;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the customer profile page.
 *
 * @ACMCustomerForm(
 *   id = "profile",
 *   label = @Translation("Profile"),
 *   defaultPage = "profile",
 * )
 */
class Profile extends CustomerFormBase implements CustomerFormInterface {

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    // If not using ecomm sessions, then a drupal account is created for each
    // customer, so we don't need a profile page.
    return $this->getConfigFactory()
      ->get('acm.commerce_users')
      ->get('use_ecomm_sessions');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'weight' => 0,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array &$complete_form, $action = NULL, $id = NULL) {
    $user = $this->getCommerceUserManager()->getAccount();

    // Add wrapper around the form for Backbone.js to target.
    $complete_form['#prefix'] = '<div class="customer-profile-form-wrapper">';
    $complete_form['#suffix'] = '</div>';

    $form['user_fields'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'class' => [
          'customer-profile',
        ],
      ],
    ];

    $title_options = AcmTitleSelect::processAcmTitleSelect([]);

    $form['user_fields']['title'] = [
      '#type' => 'acm_composite',
      '#field_type' => 'acm_title_select',
      '#title' => $this->t('Title'),
      '#empty_option' => $this->t('Select'),
      '#default_value' => $user->getTitle(),
      '#display_value' => $title_options['#options'][$user->getTitle()],
    ];

    $form['user_fields']['firstname'] = [
      '#type' => 'acm_composite',
      '#field_type' => 'textfield',
      '#title' => $this->t('First name'),
      '#default_value' => $user->getFirstname(),
    ];

    $form['user_fields']['lastname'] = [
      '#type' => 'acm_composite',
      '#field_type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#default_value' => $user->getLastname(),
    ];

    $form['user_fields']['dob'] = [
      '#type' => 'acm_composite',
      '#field_type' => 'date',
      '#title' => $this->t('Birthdate'),
      '#default_value' => empty($user->getDateOfBirth()) ? NULL : date('Y-m-d', strtotime($user->getDateOfBirth())),
      '#display_value' => empty($user->getDateOfBirth()) ? NULL : date('m/d/Y', strtotime($user->getDateOfBirth())),
    ];

    $form['user_fields']['email'] = [
      '#type' => 'acm_composite',
      '#field_type' => 'acm_email_update',
      '#title' => $this->t('Email'),
      '#default_value' => $user->getEmail(),
      '#validate' => ['::validateEmail'],
    ];

    $form['user_fields']['password'] = [
      '#type' => 'acm_composite',
      '#field_type' => 'acm_password_update',
      '#title' => $this->t('Password'),
      '#display_value' => $this->t('**********'),
    ];

    // Remove all fields except the requested.
    foreach ($form['user_fields'] as $field_name => &$field) {
      if (!isset($field['#field_type'])) {
        continue;
      }

      // The form is in view mode, or the current field does no match the one
      // that we want to edit.
      if ($action == 'view' || $field_name != $id) {
        $this->addEditButton($field, $field_name);
        $field['#form_mode'] = FALSE;
        // Make sure field is no longer required.
        $field['#required'] = FALSE;
        continue;
      }

      if ($field_name == 'password') {
        $field['#title_display'] = 'hidden';
      }

      // Remove the display value so the form element shows, and add a save
      // and cancel button.
      $this->addSaveButtons($field, $field_name);
    }

    return $form;
  }

  /**
   * Adds an edit button to a form field.
   *
   * @param array $field
   *   The form field.
   * @param string $field_name
   *   The name of the form field.
   */
  public function addEditButton(array &$field, $field_name) {
    $field['#buttons']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit'),
      '#url' => Url::fromRoute('acm_customer.view_page', [
        'page' => $this->getPageId(),
        'action' => 'edit',
        'id' => $field_name,
      ]),
      '#attributes' => [
        'class' => [
          'form-item__edit-button',
        ],
      ],
    ];
  }

  /**
   * Adds a save and cancel button to a form field.
   *
   * @param array $field
   *   The form field.
   * @param string $field_name
   *   The name of the form field.
   */
  public function addSaveButtons(array &$field, $field_name) {
    $field['#display_value'] = FALSE;
    $field['#buttons']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('acm_customer.view_page', [
        'page' => $this->getPageId(),
      ]),
      '#attributes' => [
        'class' => [
          'form-item__cancel-button',
        ],
      ],
    ];
    $field['#buttons']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm'],
      '#attributes' => [
        'class' => [
          'form-item__save-button',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, array &$complete_form) {
    $profile = $form_state->getValue('profile');
    $username = $profile['user_fields']['email'];
    $pass_old = $profile['user_fields']['password']['password_old'];

    $customer_auth = \Drupal::service('acm.auth');
    $token = $customer_auth->authenticate($username, $pass_old);

    if (empty($token)) {
      $form_state->setError($form['user_fields']['password'], t('The Old Password you entered is incorrect. Please re-enter the password.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValues($form['#parents']);
    $field_values = $values['profile']['user_fields'];

    foreach ($field_values as $field_name => $field) {
      if (empty($field)) {
        unset($field_values[$field_name]);
      }

      // Flatten password values.
      // @todo: Need to verify password was set properly.
      if ($field_name == 'password') {
        if (!empty($field['password']) && !empty($field['password_old'])) {
          $field_values['password'] = $field['password'];
          $field_values['password_old'] = $field['password_old'];
        }
        else {
          unset($field_values['password']);
        }
      }
    }

    // The email field will come through as an array with the email and
    // password. We need to flatten it.
    if (isset($field_values['email']) && is_array($field_values['email'])) {
      $field_values['password'] = $field_values['email']['password'];
      $field_values['email'] = $field_values['email']['email'];
    }

    $this->getCommerceUserManager()->updateAccount($field_values);
  }

}
