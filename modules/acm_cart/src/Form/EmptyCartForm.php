<?php

namespace Drupal\acm_cart\Form;

use Drupal\acm_cart\CartStorageInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Contains the EmptyCartForm.
 *
 * @package Drupal\acm_cart\Form
 */
class EmptyCartForm extends FormBase {

  /**
   * Constant for the empty operation.
   *
   * @var string
   */
  const OP_EMPTY = 'op_empty';

  /**
   * The cart storage object.
   *
   * @var \Drupal\acm_cart\CartStorageInterface
   */
  protected $cartStorage;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  protected $router;

  /**
   * Block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Construct an EmptyCartForm object.
   *
   * @param \Drupal\acm_cart\CartStorageInterface $cart_storage
   *   The cart storage instance.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   The router.
   */
  public function __construct(CartStorageInterface $cart_storage, Request $request, BlockManagerInterface $block_manager, RouterInterface $router) {
    $this->cartStorage = $cart_storage;
    $this->request = $request;
    $this->blockManager = $block_manager;
    $this->router = $router;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acm_cart.cart_storage'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('plugin.manager.block'),
      $container->get('router.no_access_checks')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'empty_cart_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['message'] = [
      '#type' => 'markup',
      '#markup' => $this->t('You are about to empty your cart.'),
    ];

    $form['cart_table'] = $this->getCartTable();

    $this->getRedirectUri();

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#url' => Url::fromUri($this->getRedirectUri()),
      '#title' => $this->t('Cancel'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Empty',
      '#op' => self::OP_EMPTY,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $result = $this->router->match($this->getRedirectUri());
    $route = isset($result['_route']) ? $result['_route'] : '<front>';

    $this->cartStorage->clearCart();
    $form_state->setRedirect($route);

    drupal_set_message($this->t('Cart has been cleared.'));
  }

  /**
   * Render the cart table block.
   *
   * @return array
   *   A valid render array.
   */
  public function getCartTable() {
    $block = $this->blockManager->createInstance('cart_block');
    return $block ? $block->build() : [];
  }

  /**
   * The return URL to redirect the user.
   *
   * @return string
   *   A route to redirect the user to.
   */
  public function getRedirectUri() {
    $return = $this->request->get('return');
    return $return ?: 'internal:/';
  }

}
