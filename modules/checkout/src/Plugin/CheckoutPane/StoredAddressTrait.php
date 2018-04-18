<?php

namespace Drupal\acm_checkout\Plugin\CheckoutPane;

use Drupal\acm\ACMAddressFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;

/**
 * Provides a trait for stored address forms.
 */
trait StoredAddressTrait {

  /**
   * Builds the address options list.
   *
   * @return array
   *   An array of address options.
   */
  public function buildAddressOptions() {
    $address_formatter = new ACMAddressFormatter();
    $user = $this->getCurrentCommerceUser();
    $addresses = $user->getAddresses();

    // Stored addresses.
    foreach ($addresses as $address) {
      $option_id = $address['address_id'];
      $options[$option_id] = [
        'id' => $option_id,
        'label' => $address_formatter->render((object) $address),
      ];
    }

    // New address.
    $option_id = 'new_address';
    $options[$option_id] = [
      'id' => $option_id,
      'label' => $this->t('New address'),
    ];

    return $options;
  }

  /**
   * Finds the default address option.
   *
   * @param string $type
   *   The address type.
   * @param array $options
   *   An array of address options.
   *
   * @return string
   *   The key of the default option.
   */
  protected function getDefaultAddressOption($type, array $options) {
    $default_option = NULL;

    // Check if any address is a default for this type.
    foreach ($options as $option_id => $option) {
      $key = "default-{$type}";
      if (isset($option[$key]) && $option[$key]) {
        $default_option = $option_id;
        break;
      }
    }

    // Fallback to the first option.
    if (!$default_option || !isset($options[$default_option])) {
      $option_ids = array_keys($options);
      $default_option = reset($option_ids);
    }

    return $default_option;
  }

  /**
   * Finds an address by id.
   *
   * @param string|int $address_id
   *   The address id.
   *
   * @return null|array
   *   The found address.
   */
  public static function findAddress($address_id) {
    $found_address = NULL;
    $user = \Drupal::service('acm.commerce_user_manager')->getAccount();
    $addresses = $user->getAddresses();

    $extra_keys = [
      'customer_id',
      'customer_address_id',
      'region_id',
      'default_billing',
      'default_shipping',
      'extension',
    ];

    foreach ($addresses as $address) {
      if ($address['address_id'] != $address_id) {
        continue;
      }

      // Found address, strip out extra info.
      foreach ($extra_keys as $key) {
        unset($address[$key]);
      }

      $found_address = $address;
      break;
    }

    return $found_address;
  }

  /**
   * Decorates the form with the stored address options.
   *
   * @param array $pane_form
   *   The pane form, containing the following basic properties:
   *   - #parents: Identifies the position of the pane form in the overall
   *     parent form, and identifies the location where the field values are
   *     placed within $form_state->getValues().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param string $type
   *   Whether it's shipping or billing address.
   * @param array $current_address
   *   The current set address.
   *
   * @return array
   *   The updated form array.
   */
  protected function buildStoredAddressOptions(array $pane_form, FormStateInterface $form_state, $type = 'billing', array $current_address = []) {
    $field_name = "{$type}_address_options";
    $options = $this->buildAddressOptions();

    $user_input = $form_state->getUserInput();
    $values = NestedArray::getValue($user_input, $pane_form['#parents']);
    $default_option = NULL;
    if (!empty($values[$field_name])) {
      // The form was rebuilt via AJAX, use the submitted value.
      $default_option = $values[$field_name];
    }
    else {
      $default_option = $this->getDefaultAddressOption($type, $options);

      // Check if the saved address matches an option, if so use that as the
      // default.
      if ($default_option != 'new_address') {
        if (isset($current_address['address_id']) && isset($options[$current_address['address_id']])) {
          $default_option = $current_address['address_id'];
        }
        elseif (!empty($current_address)) {
          $default_option = 'new_address';
        }
      }
    }

    // Prepare the form for ajax.
    $pane_form['#wrapper_id'] = "{$type}-information-wrapper";
    $pane_form['#prefix'] = '<div id="' . $pane_form['#wrapper_id'] . '">';
    $pane_form['#suffix'] = '</div>';

    $pane_form[$field_name] = [
      '#type' => 'radios',
      '#options' => array_column($options, 'label', 'id'),
      '#default_value' => $default_option,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $pane_form['#wrapper_id'],
      ],
      '#access' => count($options) > 1,
    ];

    $pane_form['address'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['address_wrapper'],
      ],
      '#access' => ($default_option == 'new_address') ? TRUE : FALSE,
    ];

    $checkout_config = \Drupal::config('acm_checkout.settings');
    $validate_address = $checkout_config->get("validate_{$type}_address");
    $address_review_text = $checkout_config->get("{$type}_address_review_text");
    $address_failed_text = $checkout_config->get("{$type}_address_failed_text");

    $pane_form['address']['address_fields'] = [
      '#type' => 'acm_address',
      '#default_value' => $current_address,
      '#display_telephone' => TRUE,
      '#display_title' => TRUE,
      '#display_firstname' => TRUE,
      '#display_lastname' => TRUE,
      '#validate_address' => $validate_address,
      '#address_review_text' => $address_review_text,
      '#address_failed_text' => $address_failed_text,
    ];

    return $pane_form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];
    array_pop($parents);
    return NestedArray::getValue($form, $parents);
  }

}
