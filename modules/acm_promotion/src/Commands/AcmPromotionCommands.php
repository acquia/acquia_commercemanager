<?php

namespace Drupal\acm_promotion\Commands;

use Drupal\acm_promotion\AcmPromotionsManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drush\Commands\DrushCommands;

/**
 * Class AcmPromotionCommands.
 *
 * @package Drupal\acm_promotion\Commands
 */
class AcmPromotionCommands extends DrushCommands {

  /**
   * ACM Promotions Manager.
   *
   * @var \Drupal\acm_promotion\AcmPromotionsManager
   */
  private $acmPromotionsManager;

  /**
   * AcmPromotionCommands constructor.
   *
   * @param \Drupal\acm_promotion\AcmPromotionsManager $acmPromotionsManager
   *   ACM Promotion Manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger Factory.
   */
  public function __construct(AcmPromotionsManager $acmPromotionsManager,
                              LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->acmPromotionsManager = $acmPromotionsManager;
    $this->logger = $loggerChannelFactory->get('acm_promotion');
  }

  /**
   * Run a full synchronization of all commerce promotion records.
   *
   * @param array $options
   *   Command Options.
   *
   * @command acm_promotion:sync-promotions
   *
   * @option types Type of promotions that need to be synced.
   *
   * @validate-module-enabled acm_promotion
   *
   * @aliases acspm,sync-commerce-promotions
   *
   * @usage drush acspm
   *   Run a full synchronization of all available promotions.
   * @usage drush acspm --types=cart
   *   Run a full synchronization of all available cart promotions.
   */
  public function synPromotions(array $options = ['types' => NULL]) {
    if ($types = $options['types']) {
      $this->logger->notice(dt('Synchronizing all @types commerce promotions, this usually takes some time...', ['@types' => $types]));
      $types = explode(',', $types);
      $types = array_map('trim', $types);
      $this->acmPromotionsManager->syncPromotions($types);
    }
    else {
      $this->logger->notice(dt('Synchronizing all commerce promotions, this usually takes some time...'));
      $this->acmPromotionsManager->syncPromotions();
    }

    $this->logger->notice(dt('Promotion sync completed.'));
  }

}
