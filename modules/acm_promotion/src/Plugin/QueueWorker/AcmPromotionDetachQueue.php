<?php

namespace Drupal\acm_promotion\Plugin\QueueWorker;

use Drupal\acm_promotion\AcmPromotionQueueBase;
use Drupal\acm_sku\Entity\SKU;

/**
 * Processes Skus to detach Promotions.
 *
 * @QueueWorker(
 *   id = "acm_promotion_detach_queue",
 *   title = @Translation("Acm Commerce Promotion detach queue"),
 * )
 */
class AcmPromotionDetachQueue extends AcmPromotionQueueBase {

  /**
   * Works on a single queue item.
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   *
   * @throws \Drupal\Core\Queue\RequeueException
   *   Processing is not yet finished. This will allow another process to claim
   *   the item immediately.
   * @throws \Exception
   *   A QueueWorker plugin may throw an exception to indicate there was a
   *   problem. The cron process will log the exception, and leave the item in
   *   the queue to be processed again later.
   * @throws \Drupal\Core\Queue\SuspendQueueException
   *   More specifically, a SuspendQueueException should be thrown when a
   *   QueueWorker plugin is aware that the problem will affect all subsequent
   *   workers of its queue. For example, a callback that makes HTTP requests
   *   may find that the remote server is not responding. The cron process will
   *   behave as with a normal Exception, and in addition will not attempt to
   *   process further items from the current item's queue during the current
   *   cron run.
   *
   * @see \Drupal\Core\Cron::processQueues()
   */
  public function processItem($data) {
    $skus = $data['skus'];
    $promotion_nid = $data['promotion'];
    foreach ($skus as $sku) {
      // Check if the SKU added to queue is available before processing.
      if (($sku_entity = SKU::loadFromSku($sku)) &&
        ($sku_entity instanceof SKU)) {
        $this->promotionManager->removeOrphanPromotionFromSku($sku_entity, $promotion_nid);
      }
      else {
        $unprocessed_skus[] = $sku;
      }
    }

    $sku_texts = implode(',', $skus);

    // Invalidate cache tags for updated skus & promotions.
    $this->tagInvalidate->invalidateTags($invalidate_tags);

    $this->logger->info('Detached Promotion:@promo from SKUs: @skus',
      ['@promo' => $promotion_nid, '@skus' => $sku_texts]);

    // Log unprocessed SKUs while detatching from Promotion.
    if (!empty($unprocessed_skus)) {
      $this->logger->info('SKUs @skus not found while detatching from promotion: @promo',
        ['@promo' => $promotion_nid, '@skus' => implode(',', $unprocessed_skus)]);
    }
  }

}
