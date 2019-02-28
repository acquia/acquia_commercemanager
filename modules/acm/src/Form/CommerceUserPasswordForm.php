<?php

namespace Drupal\acm\Form;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\acm\User\CommerceUserSession;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommerceUserPasswordForm.
 *
 * @package Drupal\acm\Form
 */
class CommerceUserPasswordForm extends FormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * API Wrapper object.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  protected $apiWrapper;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\acm\Connector\APIWrapperInterface $api_wrapper
   *   ApiWrapper object.
   */
  public function __construct(LanguageManagerInterface $language_manager, APIWrapperInterface $api_wrapper) {
    $this->languageManager = $language_manager;
    $this->apiWrapper = $api_wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('acm.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_user_password_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email address'),
      '#size' => 60,
      '#required' => TRUE,
      '#attributes' => [
        'autocorrect' => 'off',
        'autocapitalize' => 'off',
        'spellcheck' => 'false',
        'autofocus' => 'autofocus',
      ],
    ];

    $form['message'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('Password reset instructions will be sent to your registered email address.'),
      '#suffix' => '</p>',
    ];

    $form['email']['#default_value'] = $this->getRequest()->query->get('name');

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Submit')];
    $form['#cache']['contexts'][] = 'url.query_args';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $name = trim($form_state->getValue('email'));
    try {
      $customer = $this->apiWrapper->getCustomer($name);
      $account = new CommerceUserSession($customer);
      $form_state->setValueForElement(['#parents' => ['account']], $account);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('email', $this->t('%name is not recognized as a username or an email address.', ['%name' => $name]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $ecomm_pass_reset = $this->config('acm.commerce_users')
      ->get('use_ecomm_pass_reset');

    $user_message = $this->t('Further instructions have been sent to your email address.');

    // If using ecomm password resets trigger a password reset email to be sent
    // from the ecommerce backend.
    if ($ecomm_pass_reset) {
      $this->apiWrapper->resetCustomerPassword($email);
      $this->logger('acm')->notice('Password reset triggered for email %email.', ['%email' => $email]);
      drupal_set_message($user_message);
      return;
    }

    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Get the custom site notification email to use as the from email address
    // if it has been set.
    $site_mail = $this->config('system.site')->get('mail_notification');
    // If the custom site notification email has not been set, we use the site
    // default for this.
    if (empty($site_mail)) {
      $site_mail = $this->config('system.site')->get('mail');
    }
    if (empty($site_mail)) {
      $site_mail = ini_get('sendmail_from');
    }

    $params['account'] = $form_state->getValue('account');

    // Mail one time login URL and instructions using current language.
    $mail = \Drupal::service('plugin.manager.mail')->mail('acm', 'commerce_user_reset_password', $email, $langcode, $params, $site_mail);
    if (!empty($mail)) {
      $this->logger('acm')->notice('Password reset instructions mailed to %email.', ['%email' => $email]);
      drupal_set_message($user_message);
    }

    $form_state->setRedirect('acm.external_user_login');
  }

}
