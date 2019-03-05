<?php

namespace Drupal\acm_promotion\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to display promotion information to users.
 *
 * @Block(
 *   id = "acm_promotion_block",
 *   admin_label = @Translation("Acquia Commerce Promotion Block"),
 *   category = @Translation("Acquia Commerce Promotion"),
 * )
 */
class AcmPromotionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a NodeEmbedBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();

    $options = [];
    $display_modes = $this->entityDisplayRepository->getAllViewModes();
    if (isset($display_modes['node'])) {
      foreach ($display_modes['node'] as $display_mode => $display_mode_info) {
        if ($display_mode === 'full') {
          continue;
        }
        $options[$display_mode] = $display_mode_info['label'];
      }
    }

    $form['display_mode'] = [
      '#title' => t('Display Mode'),
      '#description' => t('Select the display mode you want this block to use to render promotion nodes.'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => (isset($config['display_mode']) && !empty($config['display_mode']) ? $config['display_mode'] : 'teaser'),
      '#required' => TRUE,
    ];

    $form['view_mode'] = [
      '#title' => t('View Mode'),
      '#description' => t('Select the view mode of this block. By selecting "No Limit", this block will show all active promotions. By selecting "Limit to SKU", this block will only show promotions relavent the main content sku.'),
      '#type' => 'select',
      '#options' => [
        'no_limit' => t('No Limit'),
        'sku_limit' => t('Limit to SKU'),
      ],
      '#default_value' => (isset($config['view_mode']) && !empty($config['view_mode']) ? $config['view_mode'] : 'no_limit'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('display_mode', $form_state->getValue('display_mode'));
    $this->setConfigurationValue('view_mode', $form_state->getValue('view_mode'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Grab display mode from configuration or default to teaser.
    $config = $this->getConfiguration();
    $display_mode = (isset($config['display_mode']) && !empty($config['display_mode']) ? $config['display_mode'] : 'teaser');
    $view_mode = (isset($config['view_mode']) && !empty($config['view_mode']) ? $config['view_mode'] : 'no_limit');

    $promos = $this->getPromotionNodes($view_mode);
    $json = [];
    foreach ($promos as $promo_node) {
      $promo_code = $promo_node->getTitle();
      $teaser = node_view($promo_node, $display_mode);
      $json[$promo_code] = \Drupal::service('renderer')->render($teaser);
    }

    // TODO We may need to remove the id and add better (more specific) classes.
    // TODO We need to make the CSS classes more specific for when there is more
    // than one block.
    $css_class = 'acm-promotion-block-' . $view_mode;

    $html = sprintf(
      '<div id="acm-promotion-block" class="acm-promotion-block %s"></div>',
      $css_class
    );

    $selector = '.' . $css_class;

    return [
      '#markup' => $html,
      '#attached' => [
        'library' => [
          'acm_promotion/acm_promotion_block',
        ],
        'drupalSettings' => [
          'acm_promotion' => [
            $selector => $json,
          ],
        ],
      ],
    ];
  }

  /**
   * Gets the relavent promotion nodes to be displayed.
   *
   * This method loads the active promotion nodes.
   *
   * @return Drupal\node\Entity\Node[]
   *   The promotion node for the user's session.
   */
  protected function getPromotionNodes($view_mode) {
    $nodes = [];

    // Default to loading all enabled (published) nodes.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'acm_promotion')
      ->condition('status', NodeInterface::PUBLISHED);

    if ($view_mode === 'sku_limit') {
      $viewing_node = \Drupal::routeMatch()->getParameter('node');

      if (
        !is_null($viewing_node)
        && $viewing_node->hasField('field_skus')
        && !empty($viewing_node->get('field_skus')->getValue())
      ) {

        $sku_field = $viewing_node->get('field_skus')->getValue();
        $skus = [];
        foreach ($sku_field as $sku) {
          $skus[] = $sku['value'];
        }

        $query->condition('field_skus', $skus, 'IN');
      }
    }

    // TODO Consider adding a module alter hook here for $query.
    $nids = $query->execute();
    $nodes = Node::loadMultiple($nids);

    // TODO Consider adding a module alter hook here for $nodes.
    return $nodes;
  }

}
