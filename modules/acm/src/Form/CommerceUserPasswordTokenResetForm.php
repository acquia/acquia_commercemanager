<?php

namespace Drupal\acm\Form;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\User\AccountProxyInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserAuthInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class CommerceUserPasswordTokenResetForm.
 *
 * @package Drupal\acm\Form
 */
class CommerceUserPasswordTokenResetForm extends FormBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The api wrapper.
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
   * The commerce user manager.
   *
   * @var \Drupal\acm\User\AccountProxyInterface
   */
  protected $commerceUserManager;

  /**
   * Constructs a new CommerceUserPasswordTokenResetForm.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   The user authentication object.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication object.
   * @param \Drupal\acm\User\AccountProxyInterface $commerce_user_manager
   *   The commerce user manager.
   */
  public function __construct(RequestStack $request_stack, APIWrapperInterface $api_wrapper, UserAuthInterface $user_auth, AccountProxyInterface $commerce_user_manager) {
    $this->requestStack = $request_stack;
    $this->apiWrapper = $api_wrapper;
    $this->userAuth = $user_auth;
    $this->commerceUserManager = $commerce_user_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('acm.api'),
      $container->get('acm.auth'),
      $container->get('acm.commerce_user_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_user_password_token_reset_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Redirect the user to the homepage if they are already logged in.
    if ($this->commerceUserManager->isAuthenticated()) {
      return $this->redirect('<front>');
    }

    $request = $this->requestStack->getCurrentRequest();
    $password_token = $request->query->get('token');
    $email = $request->query->get('email');

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#default_value' => $email,
      '#disabled' => isset($email) ? TRUE : FALSE,
      '#required' => TRUE,
    ];

    $form['new_password'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
    ];

    $form['password_token'] = [
      '#type' => 'hidden',
      '#value' => $password_token,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update password'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $password = $form_state->getValue('new_password');
    $password_token = $form_state->getValue('password_token');
    $updated_user = FALSE;

    try {
      $customer = $this->apiWrapper->getCustomer($email);
      $options = [
        'password' => $password,
        'password_token' => $password_token,
      ];
      $updated_user = $this->apiWrapper->updateCustomer($customer, $options);

      // Generate and store the access token and log the user in.
      if ($token = $this->userAuth->authenticate($email, $password)) {
        $this->commerceUserManager->setAccessToken($token);
        // This will retrieve and store the account with the new access token.
        $this->commerceUserManager->getAccount();
      }
    }
    catch (\Exception $e) {
    }

    if ($updated_user) {
      drupal_set_message($this->t('Your password has been updated.'));
      return $this->redirect('acm_customer.view_page');
    }
    else {
      drupal_set_message($this->t('There was an issue updating your password.'), 'error');
    }
  }

}
