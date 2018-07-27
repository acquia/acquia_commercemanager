<?php

namespace Drupal\acm\Form;

use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PurgeQueueForm.
 *
 * @package Drupal\acm\Form
 *
 * @ingroup acm
 */
class PurgeQueueForm extends FormBase {

  /**
   * Connector Agent API Helper.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  private $api;

  /**
   * PurgeQueueForm constructor.
   *
   * @param \Drupal\acm\Connector\APIWrapperInterface $api
   *   APIWrapper object.
   */
  public function __construct(APIWrapperInterface $api) {
    $this->api = $api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acm.api')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'acm_purge_queue';
  }

  /**
   * Define the form used for settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions']['#type'] = 'actions';

    $form['actions']['purge_queue'] = [
      '#type' => 'submit',
      '#value' => t('Purge queue'),
    ];

    return ($form);
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->api->purgeQueue();
      $this->messenger()->addStatus($this->t('Queue was purged'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

}
