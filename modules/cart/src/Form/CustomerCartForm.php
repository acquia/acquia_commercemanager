<?php

namespace Drupal\acm_cart\Form;

use Drupal\acm_cart\CartStorageInterface;
use Drupal\acm\UpdateCartErrorEvent;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CustomerCartForm.
 *
 * @package Drupal\acm_cart\Form
 */
class CustomerCartForm extends FormBase {

  /**
   * Drupal\acm_cart\CartStorageInterface definition.
   *
   * @var \Drupal\acm_cart\Cart
   */
  protected $cart;

  /**
   * Drupal\acm_cart\CartStorageInterface definition.
   *
   * @var \Drupal\acm_cart\CartStorageInterface
   */
  protected $cartStorage;

  /**
   * The success message to be displayed on coupon apply.
   *
   * @var string
   */
  protected $successMessage = NULL;

  /**
   * Constructor.
   *
   * @param \Drupal\acm_cart\CartStorageInterface $cart_storage
   *   The cart storage.
   */
  public function __construct(CartStorageInterface $cart_storage) {
    $this->cartStorage = $cart_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acm_cart.cart_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'customer_cart_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // We always want this cache context.
    $form['#cache']['contexts'][] = 'cookies:Drupal_visitor_acm_cart_id';

    $cart = $this->cartStorage->getCart(FALSE);

    if (empty($cart)) {
      return $form;
    }

    // Add this cache tag if cart exists.
    $form['#cache']['tags'][] = 'cart:' . $cart->id();

    $items = NULL;

    if ($cart) {
      $items = $cart->items();
    }

    $form['cart'] = [
      '#type' => 'table',
      '#header' => [
        t('Product'),
        t('Quantity'),
        t('Price'),
      ],
      '#empty' => t('There are no products in your cart yet.'),
    ];

    if (empty($items)) {
      return $form;
    }

    foreach ($items as $index => $line_item) {
      // Ensure object notation.
      $line_item = (object) $line_item;

      $id = $line_item->sku;

      $form['cart'][$id]['name'] = $line_item->name;

      $form['cart'][$id]['quantity'] = [
        '#type' => 'number',
        '#default_value' => $line_item->qty,
      ];

      if (is_numeric($line_item->price)) {
        $form['cart'][$id]['price'] = [
          '#markup' => \Drupal::service('acm.i18n_helper')
            ->formatPrice($line_item->price),
        ];
      }
      else {
        $form['cart'][$id]['price'] = [
          '#plain_text' => $line_item->price,
        ];
      }
    }

    $form['totals'] = [
      '#type' => 'table',
    ];

    $totals = $cart->totals();

    $form['totals']['sub'] = [
      'label' => ['#plain_text' => t('Subtotal')],
      'value' => [
        '#markup' => \Drupal::service('acm.i18n_helper')
          ->formatPrice($totals['sub']),
      ],
    ];

    if ((float) $totals['tax'] > 0) {
      $form['totals']['tax'] = [
        'label' => ['#plain_text' => t('Tax')],
        'value' => [
          '#markup' => \Drupal::service('acm.i18n_helper')
            ->formatPrice($totals['tax']),
        ],
      ];
    }

    if ((float) $totals['discount'] != 0) {
      $form['totals']['discount'] = [
        'label' => ['#plain_text' => t('Discount')],
        'value' => [
          '#markup' => \Drupal::service('acm.i18n_helper')
            ->formatPrice($totals['discount']),
        ],
      ];
    }

    if ((float) $totals['shipping'] != 0) {
      $form['totals']['shipping'] = [
        'label' => ['#plain_text' => t('Shipping')],
        'value' => [
          '#markup' => \Drupal::service('acm.i18n_helper')
            ->formatPrice($totals['shipping']),
        ],
      ];
    }

    $form['totals']['grand'] = [
      'label' => ['#plain_text' => t('Grand total')],
      'value' => [
        '#markup' => \Drupal::service('acm.i18n_helper')
          ->formatPrice($totals['grand']),
      ],
    ];

    $form['coupon'] = [
      '#title' => t('Coupon code'),
      '#type' => 'textfield',
    ];
    $coupon = $cart->getCoupon();
    if ($coupon) {
      $form['coupon']['#value'] = (string) $coupon;
      $form['coupon']['#disabled'] = TRUE;
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['update'] = [
      '#type' => 'submit',
      '#value' => t('Update'),
    ];

    if ($coupon) {
      $form['actions']['removeCoupon'] = [
        '#type' => 'submit',
        '#value' => t('Remove coupon code'),
      ];
    }

    $form['actions']['checkout'] = [
      '#type' => 'submit',
      '#value' => t('Checkout'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $cartFormItems = $form_state->getValue('cart');

    if (!empty($cartFormItems)) {
      $update_cart = [];

      foreach ($cartFormItems as $sku => $item) {
        $update_cart[] = ['sku' => $sku, 'qty' => $item['quantity']];
      }

      $this->cartStorage->setItemsInCart($update_cart);

      $removeCoupon = ($form_state->getTriggeringElement()['#parents'][0] == 'removeCoupon');
      if ($removeCoupon) {
        // Forcing the coupon empty will reset it in the ecom. application.
        $form_state->setValue('coupon', "");
        $this->cartStorage->setCoupon("");
      }
      else {
        $coupon = $form_state->getValue('coupon');
        if (!empty($coupon)) {
          $this->cartStorage->setCoupon($coupon);
        }
      }
      $this->updateCart($form_state);
    }

    // Routing.
    $action = $form_state->getTriggeringElement()['#parents'][0];
    switch ($action) {
      case 'checkout':
        $form_state->setRedirect('acm_checkout.form');
        break;

      case 'update':
      case 'removeCoupon':
      default:
        // The cart is always updated here, above;
        // (Is it? What about the error state?)
        // If we have success message available.
        $msg = !empty($this->successMessage) ? $this->successMessage : $this->t('Your cart has been updated.');
        $this->messenger()->addMessage($msg);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Cart update utility.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormStateInterface object.
   */
  private function updateCart(FormStateInterface $form_state) {
    try {
      $cart = $this->cartStorage->updateCart();
      $response_message = $cart->get('response_message');
      // We will have type of message like error or success. key '0' contains
      // the response message string while key '1' contains the response
      // message context/type like success or coupon.
      if (!empty($response_message[1])) {
        // If its success.
        if ($response_message[1] == 'success') {
          $this->successMessage = $response_message[0];
        }
        elseif ($response_message[1] == 'error_coupon') {
          // Set the error and require rebuild.
          $form_state->setErrorByName('coupon', $response_message[0]);
          $form_state->setRebuild(TRUE);

          // Remove the coupon and update the cart.
          $this->cartStorage->setCoupon('');
          $this->updateCart($form_state);
        }
      }
    }
    catch (\Exception $e) {
      if (acm_is_exception_api_down_exception($e)) {
        $this->messenger()->addError($e->getMessage());
        $form_state->setErrorByName('custom', $e->getMessage());
        $form_state->setRebuild(TRUE);
      }

      // Dispatch event so action can be taken.
      $dispatcher = \Drupal::service('event_dispatcher');
      $event = new UpdateCartErrorEvent($e);
      $dispatcher->dispatch(UpdateCartErrorEvent::SUBMIT, $event);
      $this->messenger()->addError($e->getMessage());
    }
  }

}
