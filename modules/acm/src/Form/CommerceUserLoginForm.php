<?php

namespace Drupal\acm\Form;

use Drupal\acm_cart\CartStorageInterface;
use Drupal\acm\User\AccountProxyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\user\UserAuthInterface;
use Drupal\user\Form\UserLoginForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommerceUserLoginForm.
 *
 * @package Drupal\acm\Form
 */
class CommerceUserLoginForm extends UserLoginForm {

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
  private $commerceUserManager;

  /**
   * The shopping cart.
   *
   * @var \Drupal\acm_cart\CartStorageInterface
   */
  protected $cartStorage;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructor.
   *
   * @param \Drupal\acm\User\AccountProxyInterface $commerce_user_manager
   *   The current user.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication object.
   * @param \Drupal\acm_cart\CartStorageInterface $cart_storage
   *   The cart storage.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(AccountProxyInterface $commerce_user_manager, UserAuthInterface $user_auth, CartStorageInterface $cart_storage, RendererInterface $renderer) {
    $this->commerceUserManager = $commerce_user_manager;
    $this->userAuth = $user_auth;
    $this->cartStorage = $cart_storage;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acm.commerce_user_manager'),
      $container->get('acm.auth'),
      $container->get('acm_cart.cart_storage'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_user_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['remember_me'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remember me'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateAuthentication(array &$form, FormStateInterface $form_state) {
    // @todo: Need to add some sort of flood control.
    $password = trim($form_state->getValue('pass'));
    if (!$form_state->isValueEmpty('name') && strlen($password) > 0) {
      try {
        $token = $this->userAuth->authenticate($form_state->getValue('name'), $password);
        $form_state->set('access_token', $token);
      }
      catch (\Exception $e) {
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateFinal(array &$form, FormStateInterface $form_state) {
    if (!$form_state->get('access_token')) {
      $user_input = $form_state->getuserinput();
      $query = isset($user_input['name']) ? ['name' => $user_input['name']] : [];
      $form_state->setErrorByName('name', $this->t('unrecognized username or password'));
      $this->logger('acm')->notice(
        'Commerce login attempt failed from %ip.',
        ['%ip' => $this->getrequest()->getclientip()]
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $token = $form_state->get('access_token');
    $values = $form_state->getValues();

    $token_expire = NULL;
    if (isset($values['remember_me']) && $values['remember_me']) {
      $token_expire = 31536000;
    }

    // Setting the access token effectively logs a user in since that token
    // will be able to be used on every getCustomer API call until the token
    // expires.
    $this->commerceUserManager->setAccessToken($token, $token_expire);
    $account = $this->commerceUserManager->getAccount();

    if ($account && $account->isAnonymous()) {
      drupal_set_message($this->t('There was an issue logging you in, please try again.'));
    }
    else {
      $this->logger('acm')->notice('Commerce session opened for %name.', ['%name' => $account->getUsername()]);

      // Convert the guest cart after login.
      $customer_id = $account->id();
      $this->cartStorage->convertGuestCart($customer_id);

      $form_state->setRedirect('acm_customer.view_page', [
        'page' => 'profile',
      ]);
    }
  }

}
