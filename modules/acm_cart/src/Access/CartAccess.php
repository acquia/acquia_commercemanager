<?php

namespace Drupal\acm_cart\Access;

use Drupal\acm_cart\CartStorageInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\Routing\Route;

/**
 * Class CartAccess.
 *
 * @package Drupal\acm_cart\Access
 */
class CartAccess implements AccessInterface {

  /**
   * The key used by the routing requirement.
   *
   * @var string
   */
  protected $requirementsKey = '_cart_access';

  /**
   * Cart storage object.
   *
   * @var \Drupal\acm_cart\CartStorageInterface
   */
  protected $cartStorage;

  /**
   * Constructs a CartAccess object.
   *
   * @param \Drupal\acm_cart\CartStorageInterface $cart_storage
   *   The cart storage object.
   */
  public function __construct(CartStorageInterface $cart_storage) {
    $this->cartStorage = $cart_storage;
  }

  /**
   * Determine access by ensuring that the cart object has items.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    return AccessResult::allowedIf(
      $this->cartStorage->getCart() && $this->cartStorage->getCart()->getCartItemsCount() > 0
    );
  }

}
