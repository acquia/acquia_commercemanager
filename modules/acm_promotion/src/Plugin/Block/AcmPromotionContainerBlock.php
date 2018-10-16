<?php

namespace Drupal\acm_promotion\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a block to display promotion information to users.
 *
 * @Block(
 *   id = "acm_promotion_container_block",
 *   admin_label = @Translation("Acquia Commerce Promotion Container Block"),
 *   category = @Translation("Acquia Commerce Promotion"),
 *   context = { *
 *     "node" = @ContextDefinition("entity:node", required = FALSE),
 *   }
 * )
 */
class AcmPromotionContainerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();

    $display_mode_options = [];
    $display_modes = acm_promotion_get_display_modes();

    foreach ($display_modes as $display_mode => $label) {
      if ($display_mode === 'full') {
        continue;
      }
      $display_mode_options[$display_mode] = $label;
    }

    $form['display_mode'] = [
      '#title' => t('Display Mode'),
      '#description' => t(
        'Select the promotion display mode you want this block to use to render promotion nodes.'
      ),
      '#type' => 'select',
      '#options' => $display_mode_options,
      '#default_value' => (isset($config['display_mode']) && !empty($config['display_mode']) ? $config['display_mode'] : 'teaser'),
      '#required' => TRUE,
    ];

    $container_mode_options = acm_promotion_get_container_modes();

    $form['container_mode'] = [
      '#title' => t('Container Mode'),
      '#description' => t(
        'Select "Always On" if you want to target a all "always on" promotion nodes.<br />
        Select "Sku" if you want to target a promotions that target a a specific sku.<br />
        Select "Promotion" if you want to target a specific promotion node.<br />'
      ),
      '#type' => 'select',
      '#options' => $container_mode_options,
      '#default_value' => (isset($config['container_mode']) && !empty($config['container_mode']) ? $config['container_mode'] : 'always_on'),
      '#required' => TRUE,
    ];

    // TODO In the future it would be nice to have different fields for when
    // 'sku' or 'promotion' are selected. We could potentially add an
    // auto complete field.
    $form['argument'] = [
      '#title' => t('Argument'),
      '#description' => t(
        'If the container mode is "Sku", this should be set to the sku you would like to target.<br />
        If the container mode is "Promotion" this should be the title of the promotion node you would like to target.'
      ),
      '#type' => 'textfield',
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[container_mode]"]' => ['value' => 'sku'],
          ],
          [
            ':input[name="settings[container_mode]"]' => ['value' => 'promotion'],
          ],
        ],
      ],
      '#default_value' => (isset($config['argument']) && !empty($config['argument']) ? $config['argument'] : ''),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    // Make sure argument is present if needed.
    if (
      in_array($form_state->getValue('container_mode'), ['sku', 'promotion'])
      && empty($form_state->getValue('argument'))
    ) {
      $form_state->setErrorByName('argument', t('You must provide an argument.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('display_mode', $form_state->getValue('display_mode'));
    $this->setConfigurationValue('container_mode', $form_state->getValue('container_mode'));
    $this->setConfigurationValue('argument', $form_state->getValue('argument'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Grab display mode from configuration or default to teaser.
    $config = $this->getConfiguration();
    $display_mode = (isset($config['display_mode']) && !empty($config['display_mode']) ? $config['display_mode'] : 'teaser');
    $container_mode = (isset($config['container_mode']) && !empty($config['container_mode']) ? $config['container_mode'] : 'always_on');

    $argument = '';
    if (isset($config['argument']) && !empty($config['argument'])) {
      $argument = 'data-arg="' . $config['argument'] . '"';
    }

    // Allow token replacement (ie [node:field_skus]).
    $contexts = array_filter($this->getContextValues(), function ($value) {
      return !empty($value);
    });

    $token = \Drupal::token();
    $argument = $token->replace($argument, $contexts);

    // TODO May need to update this in the future to make this more flexible,
    // perhaps with a theme function.
    $html = sprintf(
      '<div class="acm-promo-container" data-mode="%s" data-display="%s" %s></div>',
      $container_mode,
      $display_mode,
      $argument
    );

    return ['#markup' => $html];
  }

}
