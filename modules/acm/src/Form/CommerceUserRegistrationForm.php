<?php

namespace Drupal\acm\Form;

use Drupal\acm_cart\CartStorageInterface;
use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\User\CommerceUserSession;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\user\UserAuthInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommerceUserRegistrationForm.
 *
 * @package Drupal\acm\Form
 */
class CommerceUserRegistrationForm extends FormBase {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * API Wrapper object.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  protected $apiWrapper;

  /**
   * The user authentication object.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The shopping cart.
   *
   * @var \Drupal\acm_cart\CartStorageInterface
   */
  protected $cartStorage;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   ApiWrapper object.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication object.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\acm_cart\CartStorageInterface $cart_storage
   *   The cart storage.
   */
  public function __construct(ModuleHandlerInterface $module_handler, APIWrapperInterface $api_wrapper, UserAuthInterface $user_auth, AccountProxy $current_user, CartStorageInterface $cart_storage) {
    $this->moduleHandler = $module_handler;
    $this->apiWrapper = $api_wrapper;
    $this->userAuth = $user_auth;
    $this->currentUser = $current_user;
    $this->cartStorage = $cart_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('acm.api'),
      $container->get('acm.auth'),
      $container->get('acm.current_user'),
      $container->get('acm_cart.cart_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_user_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['title'] = [
      '#type' => 'acm_title_select',
      '#title' => $this->t('Title'),
      '#empty_option' => $this->t('Select'),
      '#required' => TRUE,
    ];

    $form['firstname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];

    $form['lastname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['email_confirm'] = [
      '#type' => 'email',
      '#title' => $this->t('Confirm Email'),
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
    ];

    $form['dob'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Birth'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Create account & sign in'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $email = $values['email'];

    // Account for email confirm being optional.
    if (!isset($values['email_confirm'])) {
      return;
    }

    $email_confirm = $values['email_confirm'];
    if (strlen($email) > 0 || strlen($email_confirm) > 0) {
      if (strcmp($email, $email_confirm)) {
        $form_state->setErrorByName('email_confirm', $this->t('The specified emails do not match.'));
      }
    }

    // Show an error message if the user already exists in the system.
    $existingCustomer = $this->apiWrapper->silentRequest('getCustomer', [$email]);

    if (!empty($existingCustomer)) {
      $form_state->setError(
        $form['email'],
        t('You already have an account with this email address. Please use a different email address or <a href="@url">sign in</a> to your account.', [
          '@url' => Url::fromRoute('acm.external_user_login')->toString(),
        ]
        )
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $password = $values['password'];
    $customer_fields = [
      'firstname' => $values['firstname'],
      'lastname' => $values['lastname'],
      'email' => $values['email'],
      'password' => $password,
      'title' => $values['title'],
      'dob' => $values['dob'],
    ];

    // Create customer and get/save access token to log them in.
    try {
      $customer = $this->apiWrapper->createCustomer($customer_fields);
      if ($customer && $token = $this->userAuth->authenticate($customer['email'], $password)) {
        $account = new CommerceUserSession($customer);
        $account->setAccessToken($token);
        $this->currentUser->setAccount($account);
        $this->logger('acm')->notice('Commerce session opened for %name.', ['%name' => $account->getUsername()]);

        // Convert the guest cart after login.
        $customer_id = $account->id();
        $this->cartStorage->convertGuestCart($customer_id);

        if ($this->moduleHandler->moduleExists('acm_customer')) {
          $form_state->setRedirect('acm_customer.view_page', [
            'page' => 'profile',
          ]);
        }
        else {
          $form_state->setRedirect('<front>');
        }
      }
    }
    catch (\Exception $e) {
      $this->logger('acm')->error($e->getMessage());
    }
  }

}
