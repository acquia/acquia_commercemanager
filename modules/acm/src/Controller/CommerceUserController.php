<?php

namespace Drupal\acm\Controller;

use Drupal\acm_cart\CartStorageInterface;
use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\Form\CommerceUserPasswordResetForm;
use Drupal\acm\SessionStoreInterface;
use Drupal\acm\User\AccountProxyInterface;
use Drupal\acm\User\CommerceUserSession;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\UserAuthInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class CommerceUserController.
 */
class CommerceUserController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The session storage.
   *
   * @var \Drupal\acm\SessionStoreInterface
   */
  protected $session;

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
   * API Wrapper object.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  protected $apiWrapper;

  /**
   * Cart Storage.
   *
   * @var \Drupal\acm_cart\CartStorageInterface
   */
  protected $cartStorage;

  /**
   * Constructs a new CommerceUserController.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\acm\SessionStoreInterface $session
   *   The session.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication object.
   * @param \Drupal\acm\User\AccountProxyInterface $commerce_user_manager
   *   The commerce user manager.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   ApiWrapper object.
   * @param \Drupal\acm_cart\CartStorageInterface $cartStorage
   *   Cart Storage object.
   */
  public function __construct(DateFormatterInterface $date_formatter, SessionStoreInterface $session, UserAuthInterface $user_auth, AccountProxyInterface $commerce_user_manager, APIWrapperInterface $api_wrapper, CartStorageInterface $cartStorage) {
    $this->dateFormatter = $date_formatter;
    $this->session = $session;
    $this->userAuth = $user_auth;
    $this->commerceUserManager = $commerce_user_manager;
    $this->apiWrapper = $api_wrapper;
    $this->cartStorage = $cartStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('acm.session_storage'),
      $container->get('acm.auth'),
      $container->get('acm.commerce_user_manager'),
      $container->get('acm.api'),
      $container->get('acm_cart.cart_storage')
    );
  }

  /**
   * Logs the current user out.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirection to home page.
   */
  public function logout() {
    $this->commerceUserManager->setAccount();

    $this->cartStorage->clearCart();

    return $this->redirect('<front>');
  }

  /**
   * Redirects to the user password reset form.
   *
   * In order to never disclose a reset link via a referrer header this
   * controller must always return a redirect response.
   *
   * @param string $email
   *   The base64 encoded email address of the user requesting a password reset.
   * @param int $timestamp
   *   The current timestamp.
   * @param string $hash
   *   Login link hash.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function resetPass($email, $timestamp, $hash) {
    $this->session->set('pass_reset_hash', $hash);
    $this->session->set('pass_reset_timeout', $timestamp);
    return $this->redirect(
      'acm.external_user_password_reset_form',
      ['email' => $email]
    );
  }

  /**
   * Returns the user password reset form.
   *
   * @param string $email
   *   The base64 encoded email address of the user requesting a password reset.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The form structure or a redirect response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the pass_reset_timeout or pass_reset_hash are not available in the
   *   session. Or if $uid is for a blocked user or invalid user ID.
   */
  public function getResetPassForm($email) {
    $timestamp = $this->session->get('pass_reset_timeout');
    $hash = $this->session->get('pass_reset_hash');

    // As soon as the session variables are used they are removed to prevent the
    // hash and timestamp from being leaked unexpectedly. This could occur if
    // the user does not click on the log in button on the form.
    $this->session->remove('pass_reset_timeout');
    $this->session->remove('pass_reset_hash');
    if (!$hash || !$timestamp) {
      throw new AccessDeniedHttpException();
    }

    // Time out, in seconds, until login URL expires.
    $timeout = $this->config('user.settings')->get('password_reset_timeout');
    $expiration_date = $this->dateFormatter->format($timestamp + $timeout);

    return $this->formBuilder()->getForm(CommerceUserPasswordResetForm::class, $email, $expiration_date, $timestamp, $hash);
  }

  /**
   * Reset password and log in.
   *
   * Validates user, hash, and timestamp; logs the user in and resets password
   * if correct.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param int $email
   *   The base64 encoded email address of the user requesting a password reset.
   * @param int $timestamp
   *   The current timestamp.
   * @param string $hash
   *   Login link hash.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the user edit form if the information is correct.
   *   If the information is incorrect redirects to 'user.pass' route with a
   *   message for the user.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If $uid is for a blocked user or invalid user ID.
   */
  public function resetPassLogin(Request $request, $email, $timestamp, $hash) {
    $user = NULL;
    $email = base64_decode($email);

    try {
      $user = $this->apiWrapper->getCustomer($email);
    }
    catch (\Exception $e) {
    }

    // Verify that the user exists and is active.
    if (!$user) {
      // Blocked or invalid user, so deny access. The parameters will be in the
      // watchdog's URL for the administrator to check.
      throw new AccessDeniedHttpException();
    }

    // Wrap user in CommerceUserSession.
    $account = new CommerceUserSession($user);

    // The current user is not logged in, so check the parameters.
    $current = REQUEST_TIME;
    // Time out, in seconds, until login URL expires.
    $timeout = $this->config('user.settings')->get('password_reset_timeout');

    if ($current - $timestamp > $timeout) {
      drupal_set_message($this->t('you have tried to use a one-time password reset link that has expired. Please request a new one using the form below.'), 'error');
      return $this->redirect('acm.external_user_password');
    }
    elseif (($timestamp <= $current) && Crypt::hashEquals($hash, acm_commerce_user_pass_rehash($account, $timestamp))) {
      $password_values = $request->get('new_password');
      $pass1 = trim($password_values['pass1']);
      $pass2 = trim($password_values['pass2']);
      if (strlen($pass1) > 0 || strlen($pass2) > 0) {
        if (strcmp($pass1, $pass2)) {
          // Passwords don't match, return back to the reset password page so
          // they can correct.
          drupal_set_message($this->t('The passwords you entered do not match.'), 'error');
          return $this->redirect(
            'acm.external_user_password_reset', [
              'email' => base64_encode($email),
              'timestamp' => $timestamp,
              'hash' => $hash,
            ]
          );
        }
      }

      $updated_user = FALSE;
      try {
        // Update customer's password.
        $updated_user = $this->apiWrapper->updateCustomer($user, ['password' => $pass1]);
        // Generate and store the access token and log the user in.
        if ($token = $this->userAuth->authenticate($email, $pass1)) {
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
        return $this->redirect('acm.external_user_password');
      }
    }

    drupal_set_message($this->t('You have tried to use a one-time password reset link that has either been used or is no longer valid. Please request a new one using the form below.'), 'error');
    return $this->redirect('acm.external_user_password');
  }

}
