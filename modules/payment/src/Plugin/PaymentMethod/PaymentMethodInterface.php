<?php

namespace Drupal\acm_payment\Plugin\PaymentMethod;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the interface for ACM Payment Method plugins.
 *
 * Checkout panes are configurable forms embedded into the checkout form.
 */
interface PaymentMethodInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Gets the shopping cart.
   *
   * @return object
   *   The shopping cart.
   */
  public function getCart();

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
  public function buildPaymentSummary();

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
   * Builds the payment form.
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
  public function buildPaymentForm(array $pane_form, FormStateInterface $form_state, array &$complete_form);

  /**
   * Validates the payment form.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validatePaymentForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form);

  /**
   * Handles the submission of an payment form.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function submitPaymentForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form);

}
