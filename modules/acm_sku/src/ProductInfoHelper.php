<?php

namespace Drupal\acm_sku;

use Drupal\acm_sku\Entity\SKUInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ProductInfoHelper.
 *
 * @package Drupal\acm_sku
 */
class ProductInfoHelper {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * ProductInfoHelper constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Dispatch event and get updated value for specific field and context.
   *
   * @param \Drupal\acm_sku\Entity\SKUInterface $sku
   *   SKU Entity.
   * @param string $field_code
   *   Field code, title/description/etc.
   * @param string $context
   *   Context to apply rules plp/pdp/basket.
   * @param mixed $value
   *   Default value.
   *
   * @return mixed
   *   Processed value.
   */
  public function getValue(SKUInterface $sku, string $field_code, string $context, $value) {
    $event = new ProductInfoRequestedEvent($sku, $field_code, $context, $value);
    $this->eventDispatcher->dispatch(ProductInfoRequestedEvents::EVENT_NAME, $event);
    return $event->getValue();
  }

  /**
   * Get title for particular SKU.
   *
   * @param \Drupal\acm_sku\Entity\SKUInterface $sku
   *   SKU Entity.
   * @param string $context
   *   Context to apply rules plp/pdp/basket.
   *
   * @return mixed
   *   Processed value.
   */
  public function getTitle(SKUInterface $sku, string $context) {
    $default = $sku->label();
    return $this->getValue($sku, 'title', $context, $default);
  }

  /**
   * Get media items for particular SKU.
   *
   * @param \Drupal\acm_sku\Entity\SKUInterface $sku
   *   SKU Entity.
   * @param string $context
   *   Context to apply rules plp/pdp/basket.
   *
   * @return array
   *   Processed media items.
   */
  public function getMedia(SKUInterface $sku, string $context) {
    return $this->getValue($sku, 'media', $context, []);
  }

}
