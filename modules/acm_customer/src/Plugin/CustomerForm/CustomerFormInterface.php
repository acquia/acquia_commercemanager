<?php

namespace Drupal\acm_customer\Plugin\CustomerForm;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the interface for customer forms.
 *
 * Customer forms are configurable forms embedded into a customer page.
 */
interface CustomerFormInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

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
   * @return \Drupal\acm\Connector\APIWrapper
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
   * Gets the form label.
   *
   * @return string
   *   The form label.
   */
  public function getLabel();

  /**
   * Gets the form wrapper element.
   *
   * Used when rendering the form.
   * E.g: 'container', 'fieldset'. Defaults to 'container'.
   *
   * @return string
   *   The form wrapper element.
   */
  public function getWrapperElement();

  /**
   * Gets the form page ID.
   *
   * @return string
   *   The form page ID.
   */
  public function getPageId();

  /**
   * Sets the form page ID.
   *
   * @param string $page_id
   *   The form page ID.
   *
   * @return $this
   */
  public function setPageId($page_id);

  /**
   * Gets the form weight.
   *
   * @return string
   *   The form weight.
   */
  public function getWeight();

  /**
   * Sets the form weight.
   *
   * @param int $weight
   *   The form weight.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Determines whether the form is visible.
   *
   * @return bool
   *   TRUE if the form is visible, FALSE otherwise.
   */
  public function isVisible();

  /**
   * Builds the form.
   *
   * @param array $form
   *   The form, containing the following basic properties:
   *   - #parents: Identifies the position of the form in the overall parent
   *     form, and identifies the location where the field values are placed
   *     within $form_state->getValues().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param array $complete_form
   *   The complete form structure.
   * @param string $action
   *   The action parameter.
   * @param int $id
   *   The ID parameter.
   */
  public function buildForm(array $form, FormStateInterface $form_state, array &$complete_form, $action = NULL, $id = NULL);

  /**
   * Validates the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateForm(array &$form, FormStateInterface $form_state, array &$complete_form);

  /**
   * Handles the submission of an form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function submitForm(array &$form, FormStateInterface $form_state, array &$complete_form);

}
