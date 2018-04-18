<?php

namespace Drupal\acm_customer\Plugin\CustomerForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the customer orders page.
 *
 * @ACMCustomerForm(
 *   id = "orders",
 *   label = @Translation("Orders"),
 *   defaultPage = "orders",
 * )
 */
class Orders extends CustomerFormBase implements CustomerFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'weight' => 0,
    ] + parent::defaultConfiguration();
  }

  /**
   * Builds a summary of orders.
   *
   * @param array $orders
   *   The orders to build a summary for.
   *
   * @return array
   *   An render array.
   */
  public function buildSummary(array $orders = []) {
    if (empty($orders)) {
      return [
        '#markup' => $this->t('You have no orders'),
      ];
    }

    $build = [];

    foreach ($orders as $order) {
      $build[] = [
        '#theme' => 'user_order',
        '#order' => $order,
        '#order_details_path' => Url::fromRoute('acm_customer.view_page', [
          'page' => $this->getPageId(),
          'action' => 'edit',
          'id' => $order['order_id'],
        ])->toString(),
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array &$complete_form, $action = NULL, $id = NULL) {
    $user = $this->getCommerceUserManager()->getAccount();
    $apiWrapper = $this->getApiWrapper();
    $orders = $apiWrapper->getCustomerOrders($user->getEmail());

    // Remove default save button since we're putting a custom one on the page.
    unset($complete_form['actions']);

    // If viewing only, return a list view.
    if ($action == 'view') {
      $form['orders'] = $this->buildSummary($orders);
      return $form;
    }

    // Find the order to get more details for.
    foreach ($orders as $delta => $order) {
      if ($id && $order['order_id'] == $id) {
        $form_state->set('edit_order', $order);
        break;
      }
    }

    $edit_order = $form_state->get('edit_order');

    // If no order found, redirect back to list page.
    if (!$edit_order) {
      $this->customerPagesManager->redirectToPage($this->getPageId());
      return $form;
    }

    $form['order'] = [
      '#theme' => 'user_order_detailed',
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $form['order']['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => [
          'customer-order__actions',
        ],
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Reorder'),
      ],
    ];

    $form['order']['items'] = [
      '#theme' => 'user_order_items',
      '#order' => $edit_order,
    ];

    $form['order']['information'] = [
      '#theme' => 'user_order_information',
      '#order' => $edit_order,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, array &$complete_form) {
    if (!$form_state->get('edit_order')) {
      $form_state->setErrorByName('actions', $this->t('Something went wrong and the order could not be added to your cart.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, array &$complete_form) {
    $order = $form_state->get('edit_order');
    $cart = $this->getCart();
    $cart->addItemsToCart($order['items']);

    try {
      $cart->updateCart();
      drupal_set_message($this->t('Your cart has been updated.'));
      $form_state->setRedirect('acm_cart.cart');
    }
    catch (\Exception $e) {
      drupal_set_message($this->t('Something went wrong and the order could not be added to your cart.'));
    }
  }

}
