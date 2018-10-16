<?php

namespace Drupal\acm_checkout\Plugin\CheckoutPane;

use Drupal\acm\ACMAddressFormatter;
use Drupal\acm\Element\AcmAddress;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the contact information pane.
 *
 * @ACMCheckoutPane(
 *   id = "shipping_information",
 *   label = @Translation("Shipping information"),
 *   defaultStep = "shipping",
 *   wrapperElement = "fieldset",
 * )
 */
class ShippingInformation extends CheckoutPaneBase {

  use StoredAddressTrait;

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
  public function isVisible() {
    // @TODO: Uncomment once cart sets if items are shippable or not.
    // $cart = $this->getCart();
    // return $cart->getShippable();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $cart = $this->getCart();
    $shipping = $cart->getShipping();
    $address_formatter = new ACMAddressFormatter();
    /** @var \stdClass $shipping */
    $shipping = (object) ($shipping);
    return $address_formatter->render($shipping);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $cart = $this->getCart();
    $address = $cart->getShipping();
    $address = (array) $address;
    $billing_address = $cart->getBilling();
    $billing_address = (array) $billing_address;

    $form_state->setTemporaryValue('address', $address);
    $form_state->setTemporaryValue('billing_address', $billing_address);

    $pane_form += $this->buildStoredAddressOptions($pane_form, $form_state, 'shipping', $address);

    $pane_form['address']['use_billing_address'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use same address as billing'),
      '#default_value' => '',
      '#ajax' => [
        'callback' => [$this, 'updateAddressAjaxCallback'],
        'wrapper' => $pane_form['#wrapper_id'],
      ],
      '#weight' => -10,
    ];

    // Block proceeding checkout until shipping method is chosen.
    $complete_form['actions']['next']['#states'] = [
      'invisible' => [
        '#shipping_methods_wrapper' => ['value' => ''],
      ],
    ];

    $pane_form['shipping_methods'] = [
      '#type' => 'select',
      '#title' => t('Shipping Methods'),
      '#empty_option' => t('Available Shipping Methods'),
      '#default_value' => $cart->getShippingMethodAsString(),
      '#validated' => TRUE,
      '#attributes' => [
        'id' => ['shipping_methods_wrapper'],
      ],
    ];

    ShippingInformation::generateShippingEstimates(
      $address,
      $pane_form['shipping_methods']
    );

    $complete_form['actions']['get_shipping_methods'] = [
      '#type' => 'button',
      '#value' => $this->t('Estimate Shipping'),
      '#ajax' => [
        'callback' => [$this, 'updateAddressAjaxCallback'],
        'wrapper' => $pane_form['#wrapper_id'],
      ],
      '#weight' => -50,
    ];

    return $pane_form;
  }

  /**
   * Ajax handler for re-use address checkbox.
   */
  public static function updateAddressAjaxCallback($form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    $values = $values['shipping_information'];
    $use_billing_address = $values['address']['use_billing_address'];
    $address = $form_state->getTemporaryValue('address');
    $shipping_methods =& $form['shipping_information']['shipping_methods'];
    if (!empty($use_billing_address)) {
      $address = $form_state->getTemporaryValue('billing_address');
    }
    else {
      // Check if a stored address was selected.
      if (isset($values['shipping_address_options']) && $values['shipping_address_options'] != 'new_address') {
        $address = self::findAddress($values['shipping_address_options']);
      }
      else {
        $address = [
          'region' => $values['address']['address_fields']['region'],
          'country_id' => $values['address']['address_fields']['country_id'],
          'street' => $values['address']['address_fields']['street'],
          'street2' => $values['address']['address_fields']['street2'],
          'postcode' => $values['address']['address_fields']['postcode'],
          'city' => $values['address']['address_fields']['city'],
          'firstname' => $values['address']['address_fields']['firstname'],
          'lastname' => $values['address']['address_fields']['lastname'],
          'telephone' => isset($values['address']['address_fields']['telephone']) ? $values['address']['address_fields']['telephone'] : '',
          'title' => isset($values['address']['address_fields']['title']) ? $values['address']['address_fields']['title'] : '',
        ];
      }
    }

    ShippingInformation::generateShippingEstimates(
      $address,
      $shipping_methods
    );

    if (isset($form['shipping_information']['address'])) {
      $address_fields =& $form['shipping_information']['address'];
      if (isset($address_fields['address_fields']['title'])) {
        $address_fields['address_fields']['title']['#value'] = isset($address['title']) ? $address['title'] : '';
      }
      if (isset($address_fields['address_fields']['telephone'])) {
        $address_fields['address_fields']['telephone']['#value'] = isset($address['telephone']) ? $address['telephone'] : '';
      }
      $address_fields['address_fields']['firstname']['#value'] = isset($address['firstname']) ? $address['firstname'] : '';
      $address_fields['address_fields']['lastname']['#value'] = isset($address['lastname']) ? $address['lastname'] : '';
      $address_fields['address_fields']['street']['#value'] = isset($address['street']) ? $address['street'] : '';
      $address_fields['address_fields']['street2']['#value'] = isset($address['street2']) ? $address['street2'] : '';
      $address_fields['address_fields']['dynamic_parts']['city']['#value'] = isset($address['city']) ? $address['city'] : '';
      $address_fields['address_fields']['dynamic_parts']['postcode']['#value'] = isset($address['postcode']) ? $address['postcode'] : '';
      $address_fields['address_fields']['dynamic_parts']['country_id']['#value'] = isset($address['country_id']) ? $address['country_id'] : '';

      $country = $address_fields['address_fields']['dynamic_parts']['country_id']['#value'];

      $dynamic_parts = AcmAddress::calculateDynamicParts($country);
      // Set the region value accordingly.
      $possiblyMatchingRegion = "";
      if ($address['region'] && $dynamic_parts['region']['#access']) {
        $subdivisionRepository = \Drupal::service('address.subdivision_repository');
        $regions = $subdivisionRepository->getList([$country]);
        $possiblyMatchingRegion = AcmAddress::fixRegionMess($address['region'], $regions);
      }
      // Noting that ['region']['#value'] will now be set to ""
      // if  $dynamic_parts['region']['#access'] is falsey
      // (which is what we want: clear any pre-existing region value).
      $address_fields['address_fields']['dynamic_parts']['region']['#value'] = $possiblyMatchingRegion;

      $address_fields['address_fields']['dynamic_parts'] = array_replace_recursive($address_fields['address_fields']['dynamic_parts'], $dynamic_parts);
    }

    return $form['shipping_information'];
  }

  /**
   * Generates shipping estimate based on address and chosen shipping method.
   *
   * @param array|object $address
   *   The object of address.
   * @param array $select
   *   Array with selected shipping method.
   */
  public static function generateShippingEstimates($address, array &$select) {

    // Default suffix (unset or updated later).
    $select['#suffix'] = t("No shipping methods available. Please complete this shipping address form and press the 'Estimate Shipping' button.");

    if (empty($address)) {
      return;
    }

    $hasCountrySet = FALSE;
    if (is_array($address)) {
      if (strlen((string) ($address['country_id'])) > 0) {
        $hasCountrySet = TRUE;
      }
    }
    elseif (is_object($address)) {
      $countryThing = "";
      if (isset($address->countryCode)) {
        $countryThing = $address->countryCode;
      }
      elseif (isset($address->country_id)) {
        $countryThing = $address->country_id;
      }
      if (strlen((string) ($countryThing)) > 0) {
        $hasCountrySet = TRUE;
      }
    }
    if (!$hasCountrySet) {
      return;
    }

    $cart = \Drupal::service('acm_cart.cart_storage');
    $user = \Drupal::service('acm.commerce_user_manager')->getAccount();

    unset($address['address_id']);

    try {
      // Some ecommerce backends like hybris require a shipping address to be
      // set before an estimate can be calculated, but we only want to update
      // the address if it's different than what's already set on the cart.
      $current_shipping = (array) $cart->getShipping();
      if (!self::isMateriallyTheSameAddress($current_shipping, (array) $address)) {
        $cart->setShipping($address);
        $cart->updateCart();
      }

      $customer_id = NULL;
      if ($user->isAuthenticated()) {
        $customer_id = $user->id();
      }

      $shipping_methods = \Drupal::service('acm.api')
        ->getShippingEstimates($cart->id(), $address, $customer_id);
    }
    catch (\Exception $error) {
      \Drupal::logger('acm_checkout')->error(t('Unable to fetch shipping methods: @reason', [
        '@reason' => $error->getMessage(),
      ]));
    }

    if (empty($shipping_methods)) {
      $select['#suffix'] = t('No shipping methods found, please check your address and try again.');
      return;
    }
    else {
      unset($select['#suffix']);
    }

    foreach ($shipping_methods as $method) {
      // Key needs to hold both carrier and method.
      $key = implode(
        ',',
        [
          $method['carrier_code'],
          $method['method_code'],
        ]
      );

      $name = t(
        '@carrier — @method (@price)',
        [
          '@carrier' => $method['carrier_title'],
          '@method' => $method['method_title'],
          '@price' => $method['amount'] ? $method['amount'] : t('Free'),
        ]
      );

      $select['#options'][$key] = $name;
    }

    // TODO If address has shipping method, if it matches an option,
    // set that as the selected default option.
    // TODO Use radio buttons instead of dropdown.
  }

  /**
   * Function isMateriallyTheSameAddress().
   *
   * Checks values in the new address against the corresponding
   * values in the incumbent address. If they are equal, we say that
   * the two addresses are materially the same.
   *
   * @param array $incumbentAddress
   *   The incumbent address.
   * @param array $newAddress
   *   The new address.
   *
   * @return bool
   *   True if a human would say the two address are the same.
   */
  public static function isMateriallyTheSameAddress(array $incumbentAddress, array $newAddress) {
    $isMateriallyTheSameAddress = TRUE;
    foreach ($newAddress as $key => $value) {
      if (array_key_exists($key, $incumbentAddress)) {
        if ($incumbentAddress[$key] != $newAddress[$key]) {
          $isMateriallyTheSameAddress = FALSE;
        }
      }
      else {
        $isMateriallyTheSameAddress = FALSE;
      }
    }
    return $isMateriallyTheSameAddress;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);

    $shipping_method = NULL;

    if (isset($values['shipping_methods'])) {
      $shipping_method = $values['shipping_methods'];
      unset($values['shipping_methods']);
    }

    if (isset($values['shipping_address_options']) && $values['shipping_address_options'] != 'new_address') {
      $address = self::findAddress($values['shipping_address_options']);
    }
    else {
      $address_values = $values['address'];
      $address = [];

      array_walk_recursive($address_values, function ($value, $key) use (&$address) {
        $address[$key] = $value;
      });
    }

    $cart = $this->getCart();
    $cart->setShipping($address);

    if (empty($shipping_method)) {
      return;
    }

    list($carrier, $method) = explode(',', $shipping_method);

    $cart->setShippingMethod($carrier, $method);
    $cart->updateCart();
  }

}
