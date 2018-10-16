<?php

namespace Drupal\acm_checkout\Plugin\CheckoutPane;

use Drupal\acm\ACMAddressFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\address\LabelHelper;

/**
 * Provides the contact information pane.
 *
 * @ACMCheckoutPane(
 *   id = "billing_information",
 *   label = @Translation("Billing information"),
 *   defaultStep = "billing",
 *   wrapperElement = "fieldset",
 * )
 */
class BillingInformation extends CheckoutPaneBase {

  use StoredAddressTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'weight' => 1,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $cart = $this->getCart();
    $billing = $cart->getBilling();
    $address_formatter = new ACMAddressFormatter();
    /** @var \stdClass $billing */
    $billing = (object) ($billing);
    return $address_formatter->render($billing);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $cart = $this->getCart();
    $billing_address = $cart->getBilling();
    $billing_address = (array) $billing_address;
    $form_state->setTemporaryValue('address', $billing_address);

    $pane_form += $this->buildStoredAddressOptions($pane_form, $form_state, 'billing', $billing_address);

    if ($this->getCurrentUser()->isAnonymous()) {
      $pane_form['address']['email'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Email Address'),
        '#default_value' => $cart->getGuestCartEmail(),
        '#required' => TRUE,
        '#attributes' => ['placeholder' => [$this->t('Email Address')]],
        '#weight' => -10,
      ];
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // @TODO: Add field validation.
    $values = $form_state->getValue($pane_form['#parents']);

    if (!isset($values['address']['email'])) {
      return;
    }

    $email = $values['address']['email'];

    if (!(\Drupal::service('email.validator')->isValid($email))) {
      $form_state->setErrorByName('email', t('You have entered an invalid email addresss.'));
    }

    $use_ecomm_sessions = \Drupal::config('acm.commerce_users')
      ->get('use_ecomm_sessions');

    $user = FALSE;

    if ($use_ecomm_sessions) {
      try {
        $user = $this->getApiWrapper()->getCustomer($email);
      }
      catch (\Exception $e) {
      }
    }
    else {
      $user = user_load_by_mail($email);
    }

    if ($user !== FALSE) {
      $form_state->setErrorByName('email', t('You already have an account, please login.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if (isset($values['billing_address_options']) && $values['billing_address_options'] != 'new_address') {
      $address = self::findAddress($values['billing_address_options']);
    }
    else {
      $address_values = $values['address'];
      $address = [];

      array_walk_recursive($address_values, function ($value, $key) use (&$address) {
        $address[$key] = $value;
      });
    }

    // Remove email from address in case it came through, but save it to
    // associate a guest to a cart.
    $email = '';
    if (isset($address['email'])) {
      $email = $address['email'];
      unset($address['email']);
    }

    // Remove region from address in case it came through.
    // Consider doing this in the JS of the form or possibly in AcmAddress.
    $addressFormatRepository = \Drupal::service('address.address_format_repository');
    $address_format = $addressFormatRepository->get($address['country_id']);
    $subdivisionRepository = \Drupal::service('address.subdivision_repository');
    $options = $subdivisionRepository->getList([$address['country_id']]);
    $labels = LabelHelper::getFieldLabels($address_format);
    $hasNoRegion = (empty($labels['administrativeArea']) || empty($options));
    if ($hasNoRegion) {
      $address['region'] = "";
    }

    $cart = $this->getCart();
    $cart->setBilling($address);
    $cart->updateCart();
    $cart_customer_id = $cart->customerId();

    // If this is a customer cart we don't have anything left to do.
    if (!empty($cart_customer_id)) {
      return;
    }

    // If guest checkouts allowed we need to set the customer email address.
    // If guest checkout not allowed, we need to convert guest to a customer.
    $allow_guest_checkout = \Drupal::config('acm_checkout.settings')
      ->get('allow_guest_checkout');

    if ($allow_guest_checkout) {
      $cart->setGuestCartEmail($email);
    }
    else {
      $this->convertCustomerCart($values);
    }

    // @TODO(mirom): Determine need for this calls.
    $cart->setBilling($address);
    $cart->updateCart();
  }

  /**
   * Creates/logs in user and converts their cart.
   *
   * @param array $values
   *   The submitted form values.
   */
  public function convertCustomerCart(array $values) {
    $use_ecomm_sessions = \Drupal::config('acm.commerce_users')
      ->get('use_ecomm_sessions');

    if ($use_ecomm_sessions) {
      $this->convertExternalUserCart($values);
    }
    else {
      $this->convertDrupalUserCart($values);
    }
  }

  /**
   * Creates/logs in a drupal user and converts their cart.
   *
   * @param array $values
   *   The submitted form values.
   */
  public function convertDrupalUserCart(array $values) {
    $account = NULL;
    $current_user = $this->getCurrentUser();
    $firstname = $values['address']['address_fields']['firstname'];
    $lastname = $values['address']['address_fields']['lastname'];

    if ($current_user->isAnonymous()) {
      $name = $firstname . $lastname . rand(100, 999);

      $account = User::create(
        [
          'name' => $name,
          'mail' => $values['address']['email'],
          'roles' => ['authenticated'],
          'status' => 1,
        ]
      );
      $account->save();
      user_login_finalize($account);
    }
    else {
      // We can't use a session, so full load user.
      $account = User::load($current_user->id());
    }

    if (empty($account->acm_customer_id->value)) {
      $customer = \Drupal::service('acm.api')
        ->createCustomer([
          'firstname' => $firstname,
          'lastname' => $lastname,
          'email' => $account->getEmail(),
        ]);

      $account->acm_customer_id->value = $customer['customer_id'];
      $account->save();
    }

    $customer_cart = $this->getApiWrapper()
      ->createCart($account->acm_customer_id->value);

    $cart = $this->getCart();
    $cart->convertToCustomerCart($customer_cart);
    $cart->updateCart();
  }

  /**
   * Creates/logs in an external user and converts their cart.
   *
   * @param array $values
   *   The submitted form values.
   *
   * @todo: Send customer a reset password link.
   *
   * @throws \Exception
   *   Throws exception when API is down.
   */
  public function convertExternalUserCart(array $values) {
    $current_user = $this->getCurrentUser();
    $customer_id = 0;

    if ($current_user->isAnonymous()) {
      $customer = $this->getApiWrapper()
        ->createCustomer([
          'firstname' => $values['address']['address_fields']['firstname'],
          'lastname' => $values['address']['address_fields']['lastname'],
          'email' => $values['address']['email'],
        ]);
      $customer_id = $customer['customer_id'];
    }
    else {
      $customer_id = $current_user->id();
    }

    $customer_cart = $this->getApiWrapper()
      ->createCart($customer_id);

    /** @var \Drupal\acm_cart\CartStorage $cart */
    $cart = $this->getCart();
    $cart->convertToCustomerCart($customer_cart);
    $cart->updateCart();
  }

}
