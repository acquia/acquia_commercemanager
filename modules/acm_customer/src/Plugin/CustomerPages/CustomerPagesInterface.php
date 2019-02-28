<?php

namespace Drupal\acm_customer\Plugin\CustomerPages;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormInterface;

/**
 * Creates a page that customer forms can be placed on.
 */
interface CustomerPagesInterface extends FormInterface, PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Gets a config factory object.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config object.
   */
  public function getConfigFactory();

  /**
   * Gets the cart.
   *
   * @return \Drupal\acm_cart\CartStorageInterface
   *   The cart.
   */
  public function getCart();

  /**
   * Gets the API wrapper.
   *
   * @return \Drupal\acm\Connector\APIWrapperInterface
   *   The API wrapper.
   */
  public function getApiWrapper();

  /**
   * Gets the commerce user manager.
   *
   * @return \Drupal\acm\User\AccountProxyInterface
   *   The commerce user manager.
   */
  public function getCommerceUserManager();

  /**
   * Redirects to a specific customer page.
   *
   * @param string $page_id
   *   The page ID to redirect to.
   *
   * @throws \Drupal\acm\Response\NeedsRedirectException
   */
  public function redirectToPage($page_id);

  /**
   * Gets the defined pages.
   *
   * @return array
   *   An array of page definitions, keyed by page ID.
   *   Each page definition has the following keys:
   *   - title: The title of the page.
   *   - edit_title: The title of the edit page.
   *   - local_task: The local task link text.
   */
  public function getPages();

  /**
   * Gets the visible pages.
   *
   * @return array
   *   An array of page definitions, keyed by page ID.
   */
  public function getVisiblePages();

  /**
   * Gets the page's child forms.
   *
   * @param string $page_id
   *   (Optional) The page ID to filter on.
   *
   * @return \Drupal\acm_customer\Plugin\CustomerForm\CustomerFormInterface[]
   *   The forms, keyed by form id, ordered by weight.
   */
  public function getChildForms($page_id = NULL);

}
