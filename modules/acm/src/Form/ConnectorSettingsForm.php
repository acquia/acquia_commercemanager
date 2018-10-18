<?php

namespace Drupal\acm\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConnectorSettingsForm.
 *
 * @package Drupal\acm\Form
 *
 * @ingroup acm
 */
class ConnectorSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager instance.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acm_connector_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['acm.connector'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $product_node_type = $form_state->getValue('product_node_type');
    $sku_field_name = $this->getSkuFieldName($product_node_type);

    $category_vid = $form_state->getValue('category_vid');
    $category_field_name = $this->getCategoryFieldName($product_node_type, $category_vid);

    // TODO Validate Connector URL endpoints with watchdog request.
    $this->config('acm.connector')
      ->set('url', $form_state->getValue('url'))
      ->set('api_version', $form_state->getValue('api_version'))
      ->set('hmac_id', $form_state->getValue('hmac_id'))
      ->set('hmac_secret', $form_state->getValue('hmac_secret'))
      ->set('timeout', (int) $form_state->getValue('timeout'))
      ->set('verify_ssl', (bool) $form_state->getValue('verify_ssl'))
      ->set('product_page_size', (int) $form_state->getValue('product_page_size'))
      ->set('filter_root_category', (bool) $form_state->getValue('filter_root_category'))
      ->set('product_node_type', $product_node_type)
      ->set('text_format', $form_state->getValue('text_format'))
      ->set('sku_field_name', $sku_field_name)
      ->set('product_title_use_sku', (bool) $form_state->getValue('product_title_use_sku'))
      ->set('product_publish', (bool) $form_state->getValue('product_publish'))
      ->set('category_vid', $category_vid)
      ->set('category_field_name', $category_field_name)
      ->set('delete_disabled_skus', (bool) $form_state->getValue('delete_disabled_skus'))
      ->set('test_mode', (bool) $form_state->getValue('test_mode'))
      ->save();

    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('acm.connector');

    $form['basic'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic information'),
    ];

    $form['basic']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Connector URL'),
      '#required' => TRUE,
      '#default_value' => $config->get('url'),
    ];

    $form['basic']['api_version'] = [
      '#type' => 'select',
      '#title' => $this->t('API version'),
      '#required' => TRUE,
      '#default_value' => $config->get('api_version'),
      '#options' => [
        'v2' => 'V2',
        'v1' => 'V1',
      ],
    ];

    $form['security'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Security configuration'),
    ];

    $form['security']['hmac_id'] = [
      '#type' => 'password',
      '#title' => $this->t('HMAC Key ID'),
      '#required' => TRUE,
    ];

    $form['security']['hmac_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('HMAC Key Secret'),
      '#required' => TRUE,
    ];

    $form['content'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content configuration'),
    ];

    $node_types = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();

    $node_type_options = [];
    foreach ($node_types as $node_type) {
      $node_type_options[$node_type->id()] = $node_type->label();
    }

    $form['content']['product_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Product Node Type'),
      '#description' => $this->t('Select the node type being used to display products.'),
      '#options' => $node_type_options,
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
      '#default_value' => $config->get('product_node_type'),
    ];

    $form['content']['product_title_use_sku'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this to use the sku as the product title.'),
      '#default_value' => $config->get('product_title_use_sku'),
    ];

    $form['content']['product_publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this to publish new product nodes after synchronization.'),
      '#default_value' => $config->get('product_publish'),
    ];
    $form['content']['delete_disabled_skus'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this to delete disabled SKUs and product nodes.'),
      '#default_value' => $config->get('delete_disabled_skus'),
    ];

    $category_vocabs = $this->entityTypeManager
      ->getStorage('taxonomy_vocabulary')
      ->loadMultiple();

    $category_vid_options = [];
    foreach ($category_vocabs as $category_vocab) {
      $category_vid_options[$category_vocab->id()] = $category_vocab->label();
    }

    $form['content']['category_vid'] = [
      '#type' => 'select',
      '#title' => $this->t('Category Vocabulary'),
      '#description' => $this->t('Select the taxonomy vocabulary to sync categories to.'),
      '#options' => $category_vid_options,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $config->get('category_vid'),
    ];

    $form['content']['text_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Text Format'),
      '#description' => $this->t('The text format to use when importing product content.'),
      '#default_value' => $config->get('text_format'),
      '#options' => $this->getFilterFormats(),
    ];

    $form['content']['filter_root_category'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Filter root level category'),
      '#default_value' => $config->get('filter_root_category'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced configuration'),
      '#open' => FALSE,
    ];

    $form['advanced']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Connector Connection Timeout'),
      '#required' => TRUE,
      '#default_value' => $config->get('timeout'),
    ];

    $form['advanced']['verify_ssl'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Connector Verify SSL'),
      '#default_value' => $config->get('verify_ssl'),
    ];

    $form['advanced']['page_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Connector Product Synchronization Page Size'),
      '#default_value' => $config->get('product_page_size'),
    ];

    $form['advanced']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug Level Logging Of API Connections'),
      '#default_value' => $config->get('debug'),
    ];

    $form['advanced']['test_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable test mode to return mock data'),
      '#default_value' => $config->get('test_mode'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $product_node_type = $form_state->getValue('product_node_type');
    $sku_field_name = $this->getSkuFieldName($product_node_type);

    // Make sure product node type has a SKU entity reference.
    if (!$sku_field_name) {
      $form_state->setErrorByName('product_node_type', $this->t('Node type does not contain a SKU reference field.'));
    }

    // If syncing categories, make sure the product node type has an entity
    // reference to the defined categories vocab.
    if ($category_vid = $form_state->getValue('category_vid')) {
      $category_field_name = $this->getCategoryFieldName($product_node_type, $category_vid);
      if (!$category_field_name) {
        $form_state->setErrorByName('category_vid', $this->t('Node type does not contain an entity reference to the defined Category vocabulary.'));
      }
    }
  }

  /**
   * Gets the SKU field on a given node type.
   *
   * @param string $node_type
   *   The node type to scan.
   *
   * @return string
   *   The SKU field name.
   */
  protected function getSkuFieldName($node_type) {
    $fields = $this->entityFieldManager
      ->getFieldDefinitions('node', $node_type);

    // Determine which field is used for sku references based on the configured
    // product node type.
    $sku_field_name = FALSE;
    foreach ($fields as $field_name => $field_instance) {
      $field_type = $field_instance->getType();
      if ($field_type == 'sku') {
        $sku_field_name = $field_name;
        break;
      }
    }

    return $sku_field_name;
  }

  /**
   * Gets the field used to store the category on a given node type.
   *
   * @param string $node_type
   *   The node type to scan.
   * @param string $vid
   *   The vocabulary id to check for.
   *
   * @return string
   *   The Categories field name.
   */
  protected function getCategoryFieldName($node_type, $vid) {
    $fields = $this->entityFieldManager
      ->getFieldDefinitions('node', $node_type);

    // Determine which field is used for categories based on the configured
    // product node type.
    $category_field_name = FALSE;
    foreach ($fields as $field_name => $field_instance) {
      $field_type = $field_instance->getType();
      if ($field_type != 'entity_reference') {
        continue;
      }

      $settings = $field_instance->getSettings();
      if ($settings['target_type'] != 'taxonomy_term') {
        continue;
      }

      if (in_array($vid, $settings['handler_settings']['target_bundles'])) {
        $category_field_name = $field_name;
        break;
      }
    }

    return $category_field_name;
  }

  /**
   * Get all filter formats available to the user.
   *
   * @return array
   *   An array of available html filters.
   */
  public function getFilterFormats() {
    $options = [];
    $formats = filter_formats($this->currentUser);
    foreach ($formats as $format) {
      $options[$format->id()] = $format->label();
    }
    return $options;
  }

}
