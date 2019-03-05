<?php

namespace Drupal\acm_sku_stock\EventSubscriber;

use Drupal\acm_sku\Entity\SKU;
use Drupal\acm_sku_stock\Event\StockUpdatedEvent;
use Drupal\Core\Cache\Cache;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class StockUpdatedEventSubscriber.
 *
 * @package Drupal\acm_sku_stock\EventSubscriber
 */
class StockUpdatedEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];

    $events[StockUpdatedEvent::EVENT_NAME][] = [
      'onStockUpdated',
      -100,
    ];

    return $events;
  }

  /**
   * Subscriber Callback for the event.
   *
   * @param \Drupal\acm_sku_stock\Event\StockUpdatedEvent $event
   *   Event object.
   */
  public function onStockUpdated(StockUpdatedEvent $event) {
    // Do nothing if stock status not changed.
    if (!$event->isStockStatusChanged()) {
      return;
    }

    // This is last fallback. Any custom event subscriber should use
    // higher priority and stop event propagation to apply smarter logic.
    $sku = $event->getSku();
    $cacheTagsToInvalidate = $sku->getCacheTagsToInvalidate();

    /** @var \Drupal\acm_sku\AcquiaCommerce\SKUPluginBase $plugin */
    $plugin = $sku->getPluginInstance();

    // Invalidate cache for parent sku.
    $parent = $plugin->getParentSku($sku);
    if ($parent instanceof SKU) {
      $cacheTagsToInvalidate = array_merge($cacheTagsToInvalidate, $parent->getCacheTagsToInvalidate());
    }

    // Invalidate cache for display node.
    $node = $plugin->getDisplayNode($sku);
    if ($node instanceof NodeInterface) {
      $cacheTagsToInvalidate = array_merge($cacheTagsToInvalidate, $node->getCacheTagsToInvalidate());
    }

    Cache::invalidateTags($cacheTagsToInvalidate);
  }

}
