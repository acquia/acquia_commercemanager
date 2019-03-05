<?php

namespace Drupal\acm_customer\Plugin\CustomerForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides the customer profile page.
 *
 * @ACMCustomerForm(
 *   id = "addresses",
 *   label = @Translation("Addresses"),
 *   defaultPage = "addresses",
 * )
 */
class Addresses extends CustomerFormBase implements CustomerFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'weight' => 0,
    ] + parent::defaultConfiguration();
  }

  /**
   * Builds a summary of addresses.
   *
   * @param array $addresses
   *   The addresses to build a summary for.
   * @param bool $show_add
   *   TRUE to show an add address button, FALSE to not show it.
   *
   * @return array
   *   An render array.
   */
  public function buildSummary(array $addresses = [], $show_add = TRUE) {
    $build = [];

    // Display an "Add Address" button.
    if ($show_add) {
      $build['add_address'] = [
        '#type' => 'acm_composite',
        '#field_type' => 'acm_address',
        '#default_value' => empty($addresses) ? $this->t('You have no saved addresses.') : '',
        '#form_mode' => FALSE,
        '#buttons' => [
          'add' => [
            '#type' => 'link',
            '#title' => $this->t('Add address'),
            '#url' => Url::fromRoute('acm_customer.view_page', [
              'page' => $this->getPageId(),
              'action' => 'add',
            ]),
            '#attributes' => [
              'class' => [
                'form-item__add-button',
              ],
            ],
          ],
        ],
      ];
    }

    // Build the summary for each user address.
    foreach ($addresses as $address) {
      if ($address['default_billing'] && $address['default_shipping']) {
        $address['heading'] = $this->t('Default Billing and Shipping Address');
      }
      elseif ($address['default_billing']) {
        $address['heading'] = $this->t('Default Billing Address');
      }
      elseif ($address['default_shipping']) {
        $address['heading'] = $this->t('Default Shipping Address');
      }
      else {
        $address['heading'] = '';
      }

      // Build a link for each action.
      $actions = [
        'edit' => [
          'label' => t('Edit'),
        ],
        'delete' => [
          'label' => t('Delete'),
          'options' => [
            'attributes' => [
              'class' => ['bypass-js'],
            ],
          ],
        ],
      ];

      $links = [];

      foreach ($actions as $action => $config) {
        $options = isset($config['options']) ? $config['options'] : [];
        $label = isset($config['label']) ? $config['label'] : $action;
        $link = Link::fromTextAndUrl($label, Url::fromRoute('acm_customer.view_page', [
          'page' => $this->getPageId(),
          'action' => $action,
          'id' => $address['address_id'],
        ], $options));

        $link = $link->toRenderable();
        $links[$action] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ["customer-address__{$action}-button"],
          ],
          'link' => $link,
        ];
      }

      $build[] = [
        '#theme' => 'user_address',
        '#address' => $address,
        '#links' => $links,
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array &$complete_form, $action = NULL, $id = NULL) {
    /** @var \Drupal\acm\Connector\APIWrapperInterface $api_wrapper */
    $api_wrapper = $this->getApiWrapper();
    /** @var \Drupal\acm\User\AccountProxyInterface $commerce_user_manager */
    $commerce_user_manager = $this->getCommerceUserManager();
    /** @var \Drupal\acm\User\CommerceAccountInterface $user */
    $user = $commerce_user_manager->getAccount();

    $addresses = $user->getAddresses();

    // Add wrapper around the form for Backbone.js to target.
    $complete_form['#prefix'] = '<div class="customer-addresses-form-wrapper">';
    $complete_form['#suffix'] = '</div>';

    $form['#attributes']['class'][] = 'customer-addresses';

    if ($action == 'delete' && isset($id)) {
      try {
        $customer_id = $user->id();
        // Delete the address.
        $api_wrapper->deleteCustomerAddress($customer_id, $id);
        // Load the updated user and set the account in the manager. This is
        // required since the user info is cached.
        $customer = $api_wrapper->getCustomer($customer_id);
        $commerce_user_manager->setAccount($customer);
      }
      catch (\Exception $e) {
      }

      $this->customerPagesManager->redirectToPage($this->getPageId());
      return $form;
    }

    if ($action == 'view') {
      $form['addresses'] = $this->buildSummary($addresses);
      return $form;
    }

    if ($action == 'add') {
      $this->buildAddressFields($form, $complete_form);
      return $form;
    }

    foreach ($addresses as $delta => $address) {
      if (isset($id) && $id != $address['address_id']) {
        $form[$delta] = $this->buildSummary([$address], FALSE);
        continue;
      }

      $this->buildAddressFields($form, $complete_form, $address);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, array &$complete_form) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, array &$complete_form) {
    $user = $this->getCommerceUserManager()->getAccount();
    $addresses = $user->getAddresses();
    $values = $form_state->getValues($form['#parents']);
    $address_values = $values['addresses']['address_wrapper']['address'];

    // Updating old address.
    foreach ($addresses as &$address) {
      if ($address['address_id'] != $address_values['address_id']) {
        unset($address);
      }

      // Update the stored address with the new values.
      foreach ($address_values as $key => $value) {
        $address[$key] = $value;
      }
    }

    // Adding new address.
    if (empty($address_values['address_id'])) {
      $addresses[] = $address_values;
    }

    $this->getCommerceUserManager()->updateAccount(['addresses' => $addresses]);
  }

  /**
   * Builds the address fields.
   *
   * @param array $form
   *   The form.
   * @param array $complete_form
   *   The complete form structure.
   * @param array $address
   *   The default address values.
   */
  protected function buildAddressFields(array &$form, array &$complete_form, array $address = []) {
    $form['address_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['address_wrapper'],
        'class' => ['customer-address-form'],
      ],
    ];

    $checkout_config = \Drupal::config('acm_checkout.settings');
    $validate_saved_address = $checkout_config->get('validate_saved_address');
    $address_review_text = $checkout_config->get('saved_address_review_text');
    $address_failed_text = $checkout_config->get('saved_address_failed_text');

    $include_address_id = empty($address) ? FALSE : TRUE;
    $form['address_wrapper']['address'] = [
      '#type' => 'acm_address',
      '#default_value' => $address,
      '#display_title' => TRUE,
      '#display_firstname' => TRUE,
      '#display_lastname' => TRUE,
      '#display_billing' => TRUE,
      '#display_shipping' => TRUE,
      '#include_address_id' => $include_address_id,
      '#validate_address' => $validate_saved_address,
      '#address_review_text' => $address_review_text,
      '#address_failed_text' => $address_failed_text,
    ];

    $form['address_wrapper']['actions'] = [
      '#type' => 'actions',
    ];

    $form['address_wrapper']['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('acm_customer.view_page', [
        'page' => $this->getPageId(),
      ]),
    ];

    $form['address_wrapper']['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    // Remove parent actions.
    unset($complete_form['actions']);
  }

}
