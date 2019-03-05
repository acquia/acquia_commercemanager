<?php

namespace Drupal\acm\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\Access\AccessInterface;

/**
 * Checks access for displaying commerce admin pages with v2 specific functions.
 */
class VersionAccessCheck implements AccessInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Run access checks for this account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Constructs a new VersionAccessCheck object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(AccountInterface $account, ConfigFactoryInterface $config_factory) {
    $this->account = $account;
    $this->configFactory = $config_factory;
  }

  /**
   * Check if v2 is set up and user can access commerce admin pages.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    $version = $this->configFactory->get('acm.connector')->get('api_version');
    if (($version === 'v2') && ($this->account->hasPermission('access commerce administration pages'))) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
