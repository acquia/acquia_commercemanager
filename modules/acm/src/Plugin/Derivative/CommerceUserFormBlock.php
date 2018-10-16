<?php

namespace Drupal\acm\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for Commerce User Form blocks.
 *
 * @see \Drupal\acm\Plugin\Block\CommerceUserFormBlock
 */
class CommerceUserFormBlock extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Drupal Config Factory Instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Constructs new GuidedSellingBlock.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $use_ecomm_sessions = $this->configFactory
      ->get('acm.commerce_users')
      ->get('use_ecomm_sessions');

    if (!$use_ecomm_sessions) {
      return $this->derivatives;
    }

    $forms = [
      [
        'machine_name' => 'commerce_user_registration_form',
        'label' => 'Commerce User Registration Form',
        'config' => [
          'class' => 'Drupal\acm\Form\CommerceUserRegistrationForm',
        ],
      ],
      [
        'machine_name' => 'commerce_user_login_form',
        'label' => 'Commerce User Login Form',
        'config' => [
          'class' => 'Drupal\acm\Form\CommerceUserLoginForm',
        ],
      ],
      [
        'machine_name' => 'commerce_user_password_form',
        'label' => 'Commerce User Password Form',
        'config' => [
          'class' => 'Drupal\acm\Form\CommerceUserPasswordForm',
        ],
      ],
    ];

    foreach ($forms as $form) {
      $machine_name = $form['machine_name'];
      $this->derivatives[$machine_name] = $base_plugin_definition;
      $this->derivatives[$machine_name]['admin_label'] = $form['label'];
      $this->derivatives[$machine_name]['config'] = $form['config'];
    }

    return $this->derivatives;
  }

}
