<?php

namespace Drupal\acm_promotion\Form;

use Drupal\acm_promotion\AcmPromotionsManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PromotionSyncForm.
 *
 * @package Drupal\acm_promotion\Form
 *
 * @ingroup acm_promotion
 */
class PromotionSyncForm extends FormBase {

  /**
   * Promotion Manager.
   *
   * @var \Drupal\acm_promotion\AcmPromotionsManager
   */
  private $promotionManager;

  /**
   * PromotionSyncForm constructor.
   *
   * @param \Drupal\acm_promotion\AcmPromotionsManager $promotion_manager
   *   The factory for configuration objects.
   */
  public function __construct(AcmPromotionsManager $promotion_manager) {
    $this->promotionManager = $promotion_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acm_promotion.promotions_manager')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'acm_promotion_sync';
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

    $form['actions']['promotion_sync'] = [
      '#type' => 'submit',
      '#value' => t('Synchronize Promotions'),
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
    $action = $form_state->getUserInput()['op'];

    switch ($action) {
      case 'Synchronize Promotions':
        $this->promotionManager->syncPromotions();
        drupal_set_message('Promotions Synchronization Complete.', 'status');
        break;
    }
  }

}
