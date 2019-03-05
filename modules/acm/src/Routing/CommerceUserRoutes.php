<?php

namespace Drupal\acm\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines a route subscriber to register a url for external user logins.
 */
class CommerceUserRoutes {

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a new CommerceUserRoutes object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('acm.commerce_users');
  }

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {
    $routes = [];

    // Only add the routes if we're using ecomm sessions, otherwise drupal user
    // accounts will be created for everyone and the core user login/logout
    // functionality can be used.
    if (!$this->config->get('use_ecomm_sessions')) {
      return $routes;
    }

    $external_registration_path = $this->config->get('external_registration_path') ?: '/register';
    $external_login_path = $this->config->get('external_login_path') ?: '/login';
    $external_logout_path = $this->config->get('external_logout_path') ?: '/logout';

    if ($this->config->get('use_ecomm_pass_reset')) {
      $ecomm_forgot_password_path = $this->config->get('ecomm_forgot_password_path') ?: '/forgottenpassword';
      $routes['acm.ecomm_password_reset'] = new Route(
        $ecomm_forgot_password_path,
        [
          '_form' => 'Drupal\acm\Form\CommerceUserPasswordTokenResetForm',
          '_title' => 'Reset Password',
        ],
        [
          // Authentication status will be checked within the form.
          '_access' => 'TRUE',
        ],
        [
          'no_cache' => 'TRUE',
        ]
      );
    }

    $routes['acm.external_user_registration'] = new Route(
      $external_registration_path,
      [
        '_form' => 'Drupal\acm\Form\CommerceUserRegistrationForm',
        '_title' => 'Create Account',
      ],
      [
        '_commerce_user_is_logged_in' => 'FALSE',
      ],
      [
        'no_cache' => 'TRUE',
      ]
    );

    $routes['acm.external_user_login'] = new Route(
      $external_login_path,
      [
        '_form' => 'Drupal\acm\Form\CommerceUserLoginForm',
        '_title' => 'Log In',
      ],
      [
        '_commerce_user_is_logged_in' => 'FALSE',
      ],
      [
        'no_cache' => 'TRUE',
      ]
    );

    $routes['acm.external_user_logout'] = new Route(
      $external_logout_path,
      [
        '_controller' => 'Drupal\acm\Controller\CommerceUserController::logout',
      ],
      [
        '_commerce_user_is_logged_in' => 'TRUE',
      ],
      [
        'no_cache' => 'TRUE',
      ]
    );

    $routes['acm.external_user_password'] = new Route(
      '/commerce-user/password',
      [
        '_form' => 'Drupal\acm\Form\CommerceUserPasswordForm',
        '_title' => 'Reset your password',
      ],
      [
        '_commerce_user_is_logged_in' => 'FALSE',
      ],
      [
        'no_cache' => 'TRUE',
      ]
    );

    $routes['acm.external_user_password_reset'] = new Route(
      '/commerce-user/reset/{email}/{timestamp}/{hash}',
      [
        '_controller' => 'Drupal\acm\Controller\CommerceUserController::resetPass',
        '_title' => 'Reset password',
      ],
      [
        '_commerce_user_is_logged_in' => 'FALSE',
      ],
      [
        'no_cache' => 'TRUE',
      ]
    );

    $routes['acm.external_user_password_reset_form'] = new Route(
      '/commerce-user/reset/{email}',
      [
        '_controller' => 'Drupal\acm\Controller\CommerceUserController::getResetPassForm',
        '_title' => 'Reset password',
      ],
      [
        '_commerce_user_is_logged_in' => 'FALSE',
      ],
      [
        'no_cache' => 'TRUE',
      ]
    );

    $routes['acm.external_user_password_reset_login'] = new Route(
      '/commerce-user/reset/{email}/{timestamp}/{hash}/login',
      [
        '_controller' => 'Drupal\acm\Controller\CommerceUserController::resetPassLogin',
        '_title' => 'Reset password',
      ],
      [
        '_commerce_user_is_logged_in' => 'FALSE',
      ],
      [
        'no_cache' => 'TRUE',
      ]
    );

    return $routes;
  }

}
