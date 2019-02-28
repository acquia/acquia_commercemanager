<?php

namespace Drupal\acm_cart\Event;

/**
 * Defines events for the cart storage system.
 */
final class Events {

  /**
   * Name of the event fired when saving a cart to storage.
   *
   * This event allows modules to perform an action whenever the cart is about
   * to be saved in storage.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartEvent
   *
   * @var string
   */
  const STORE_CART = 'acm_cart.store_cart';

  /**
   * Name of the event fired when a cart is cleared from storage.
   *
   * This event allows modules to perform an action whenever the cart is cleared
   * from storage.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartEvent
   *
   * @var string
   */
  const CLEAR_CART = 'acm_cart.clear_cart';

  /**
   * Name of the event fired after a cart is loaded from the connector.
   *
   * This event allows modules to perform an action after the cart is loaded
   * from the connector.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartEvent
   *
   * @var string
   */
  const LOAD_CART = 'acm_cart.load_cart';

  /**
   * Name of the event fired after a cart is pushed to the connector.
   *
   * This event allows modules to perform an action after the cart is pushed
   * to the connector.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartEvent
   *
   * @var string
   */
  const PUSH_CART = 'acm_cart.push_cart';
  const UPDATE_CART = 'acm_cart.push_cart';

  /**
   * Name of the event fired when an item is added to the cart.
   *
   * This event allows modules to perform an action when an item is added to
   * the cart.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartItemEvent
   *
   * @var string
   */
  const ADD_ITEM_TO_CART = 'acm_cart.add_item_to_cart';

  /**
   * Name of the event fired when an item is removed from the cart.
   *
   * This event allows modules to perform an action when an item is removed
   * from the cart.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartItemEvent
   *
   * @var string
   */
  const REMOVE_ITEM_FROM_CART = 'acm_cart.remove_item_to_cart';

  /**
   * Name of the event fired when a raw item is added to the cart.
   *
   * This event allows modules to perform an action when a raw item is added to
   * the cart.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartRawItemsEvent
   *
   * @var string
   */
  const ADD_RAW_ITEM_TO_CART = 'acm_cart.add_raw_item_to_cart';

  /**
   * Name of the event fired when the items are set in the cart.
   *
   * This event allows modules to perform an action when the items are set in
   * the cart.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartRawItemsEvent
   *
   * @var string
   */
  const SET_ITEMS_IN_CART = 'acm_cart.set_items_in_cart';

  /**
   * Name of the event fired when an items quantity it update.
   *
   * This event allows modules to perform an action when an items quantity is
   * updated.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartItemEvent
   *
   * @var string
   */
  const UPDATE_ITEM_QUANTITY = 'acm_cart.update_item_quantity';

  /**
   * Name of the event fired when a billing address is set on the cart.
   *
   * This event allows modules to perform an action when a billing address is
   * set on the cart.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartAddressEvent
   *
   * @var string
   */
  const SET_BILLING_ADDRESS = 'acm_cart.set_billing_address';

  /**
   * Name of the event fired when a shipping address is set on the cart.
   *
   * This event allows modules to perform an action when a shipping address is
   * set on the cart.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartAddressEvent
   *
   * @var string
   */
  const SET_SHIPPING_ADDRESS = 'acm_cart.set_shipping_address';

  /**
   * Name of the event fired when a coupon is added to the cart.
   *
   * This event allows modules to perform an action when a coupon is added to
   * the cart.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartCouponEvent
   *
   * @var string
   */
  const SET_COUPON = 'acm_cart.set_coupon';

  /**
   * Name of the event fired when an extension is added to the cart.
   *
   * This event allows modules to perform an action when an extension is added
   * to the cart.
   *
   * @Event
   *
   * @see \Drupal\acm_cart\Event\CartExtensionEvent
   *
   * @var string
   */
  const SET_EXTENSION = 'acm_cart.set_extension';

}
