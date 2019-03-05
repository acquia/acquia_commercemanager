<?php

namespace Drupal\acm_checkout\Plugin\CheckoutPane;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the interface for checkout panes.
 *
 * Checkout panes are configurable forms embedded into the checkout form.
 */
interface CheckoutPaneInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Gets the current user.
   *
   * @return \Drupal\Core\Session\AccountProxy
   *   The current user.
   */
  public function getCurrentUser();

  /**
   * Gets the current commerce user.
   *
   * @return \Drupal\acm\User\AccountProxyInterface
   *   The current user.
   */
  public function getCurrentCommerceUser();

  /**
   * Gets the current cart.
   *
   * @return \Drupal\acm_cart\CartStorageInterface
   *   The current cart.
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
   * Gets the pane ID.
   *
   * @return string
   *   The pane ID.
   */
  public function getId();

  /**
   * Gets the pane label.
   *
   * @return string
   *   The pane label.
   */
  public function getLabel();

  /**
   * Gets the pane administrative label.
   *
   * @return string
   *   The pane administrative label.
   */
  public function getAdminLabel();

  /**
   * Gets the pane wrapper element.
   *
   * Used when rendering the pane's form.
   * E.g: 'container', 'fieldset'. Defaults to 'container'.
   *
   * @return string
   *   The pane wrapper element.
   */
  public function getWrapperElement();

  /**
   * Gets the pane step ID.
   *
   * @return string
   *   The pane step ID.
   */
  public function getStepId();

  /**
   * Sets the pane step ID.
   *
   * @param string $step_id
   *   The pane step ID.
   *
   * @return $this
   */
  public function setStepId($step_id);

  /**
   * Gets the pane weight.
   *
   * @return string
   *   The pane weight.
   */
  public function getWeight();

  /**
   * Sets the pane weight.
   *
   * @param int $weight
   *   The pane weight.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Determines whether the pane is visible.
   *
   * @return bool
   *   TRUE if the pane is visible, FALSE otherwise.
   */
  public function isVisible();

  /**
   * Builds a summary of the pane values.
   *
   * Important:
   * The review pane shows summaries for both visible and non-visible panes.
   * To skip showing a summary for a non-visible pane, check isVisible()
   * and return an empty string.
   *
   * @return string
   *   An HTML summary of the pane values.
   */
  public function buildPaneSummary();

  /**
   * Builds the pane form.
   *
   * @param array $pane_form
   *   The pane form, containing the following basic properties:
   *   - #parents: Identifies the position of the pane form in the overall
   *     parent form, and identifies the location where the field values are
   *     placed within $form_state->getValues().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form);

  /**
   * Validates the pane form.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form);

  /**
   * Handles the submission of an pane form.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @throws \Exception
   *   If an exception is thrown during the submit callback of a pane, it is
   *   caught to allow the pane to prevent the parent form from submitting.
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form);

}
