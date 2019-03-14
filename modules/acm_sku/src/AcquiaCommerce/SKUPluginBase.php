<?php

namespace Drupal\acm_sku\AcquiaCommerce;

use Drupal\acm_sku\Entity\SKUInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\acm_sku\Entity\SKU;
use Drupal\acm_sku\Entity\SKUTypeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\PluginBase;

/**
 * Defines a base SKU Plugin. Can be used as a template for a new SKU type.
 */
abstract class SKUPluginBase extends PluginBase implements SKUPluginInterface, FormInterface, ContainerFactoryPluginInterface {

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Database\Connection $connection
   *   The current active database's master connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   *
   * If you need to alter the display of the whole entity, override this method.
   */
  public function build(array $build) {
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sku_base_form';
  }

  /**
   * {@inheritdoc}
   *
   * If you need more than one form in your SKU Type, separate out the forms
   * using form arguments. By default we fetch the SKU from the form state and
   * render the addToCartForm.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();

    if (empty($build_info['args'])) {
      return $this->addToCartForm($form, $form_state);
    }

    $sku = $build_info['args'][0];

    if (get_class($sku) == 'Drupal\acm_sku\Entity\SKU') {
      return $this->addToCartForm($form, $form_state, $build_info['args'][0]);
    }

    return $this->addToCartForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * If you need more than one form validation in your SKU Type, separate out
   * the form validation using form arguments.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->addToCartValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * If you need more than one form submission in your SKU Type, separate out
   * the form validation using form arguments.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->addToCartSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function addToCartForm(array $form, FormStateInterface $form_state, SKU $sku = NULL) {

  }

  /**
   * {@inheritdoc}
   */
  public function addToCartValidate(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function addToCartSubmit(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function processImport(SKUInterface $sku, array $product) {

    return TRUE;

  }

  /**
   * {@inheritdoc}
   */
  public function cartName(SKU $sku, array $cart, $asString = FALSE) {
    // For all configurable products we will have sku of simple variant only
    // in cart so we add a check if parent is available, process cartName of
    // that.
    if ($parent_sku = $this->getParentSku($sku)) {
      $plugin_manager = \Drupal::service('plugin.manager.sku');
      $plugin = $plugin_manager->pluginInstanceFromType($parent_sku->bundle());
      if (method_exists($plugin, 'cartName')) {
        return $plugin->cartName($sku, $cart, $asString);
      }
    }

    $cartName = $sku->label();
    if (!$asString) {
      $display_node = $this->getDisplayNode($sku);
      if ($display_node instanceof Node) {
        $url = $display_node->toUrl();
        $link = Link::fromTextAndUrl($cartName, $url);
        $cartName = $link->toRenderable();
      }
      else {
        \Drupal::logger('acm_sku')->info('Parent product for the sku: @sku seems to be unavailable.', ['@sku' => $sku->getSku()]);
      }
    }
    return $cartName;
  }

  /**
   * Get parent of current product.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   Current product.
   *
   * @return \Drupal\acm_sku\Entity\SKU|null
   *   Parent product or null if not found.
   *
   * @throws \Exception
   */
  public function getParentSku(SKU $sku) {
    $static = &drupal_static(__FUNCTION__, []);

    $langcode = $sku->language()->getId();
    $sku_string = $sku->getSku();

    if (isset($static[$langcode], $static[$langcode][$sku_string])) {
      return $static[$langcode][$sku_string];
    }

    // Initialise with empty value.
    $static[$langcode][$sku_string] = NULL;

    $parent_skus = array_keys($this->getAllParentSkus($sku_string));

    if (empty($parent_skus)) {
      return NULL;
    }

    if (count($parent_skus) > 1) {
      \Drupal::logger('acm_sku')->warning(
        'Multiple parents found for SKU: @sku, parents: @parents',
        [
          '@parents' => implode(',', $parent_skus),
          '@sku' => $sku_string,
        ]
      );
    }

    foreach ($parent_skus as $parent_sku) {
      $parent = SKU::loadFromSku($parent_sku, $langcode);
      if ($parent instanceof SKU) {
        $node = $this->getDisplayNode($parent, FALSE, FALSE);

        if ($node instanceof Node) {
          $static[$langcode][$sku_string] = $parent;
          break;
        }
      }
    }

    return $static[$langcode][$sku_string];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayNode(SKUInterface $sku, $check_parent = TRUE, $create_translation = FALSE) {
    $message = "getDisplayNode for sku " . $sku->getSku() . PHP_EOL;
    if ($check_parent) {
      if ($parent_sku = $this->getParentSku($sku)) {
        $sku = $parent_sku;
        // Why use the parent SKU?
        // SEE OTHER NOTE SOMEWHERE: 'we only ever add simples to the cart'
        // (It's okay, at 20171016 this is only called to generate
        // a link back to the product page,
        // which should indeed be to the parent, notwithstanding
        // you probably want to see the simple you selected for your cart
        // when you get there)
        $message .= "Using parent sku " . $sku->getSku() . PHP_EOL;
      }
    }

    $config = $this->configFactory->get('acm.connector');
    $product_node_type = $config->get('product_node_type') ?: 'acm_product';
    $sku_field_name = $config->get('sku_field_name') ?: 'field_skus';

    $query = \Drupal::entityQuery('node')
      ->condition('type', $product_node_type)
      ->condition($sku_field_name, $sku->getSKU())
      ->addTag('get_display_node_for_sku')
      ->range(0, 1);

    $result = $query->execute();

    if (empty($result)) {
      return NULL;
    }

    $nid = reset($result);
    $node = Node::load($nid);

    // Can we recover from a falsey $nid?
    // I suspect not, but try this for now:
    // (for no recovery, how do we handle it gracefully?)
    if (!$nid) {
      $message .= "(no nid => no html)";
      \Drupal::logger('acm_sku')->error($message);
      return NULL;
    }

    // Check language checks if site is in multilingual mode.
    if (\Drupal::languageManager()->isMultilingual()) {
      // If language of SKU and node are the same, we return the node.
      if ($node->language()->getId() == $sku->language()->getId()) {
        return $node;
      }

      // If node has translation, we return the translation.
      if ($node->hasTranslation($sku->language()->getId())) {
        return $node->getTranslation($sku->language()->getId());
      }

      // If translation not available and create_translation flag is true.
      if ($create_translation) {
        return $node->addTranslation($sku->language()->getId());
      }

      throw new \Exception(new FormattableMarkup('Node translation not found of @sku for @langcode', [
        '@sku' => $sku->id(),
        '@langcode' => $sku->language()->getId(),
      ]), 404);
    }

    if ($config->get('debug') == TRUE) {
      \Drupal::logger('acm_sku')->debug($message);
    }

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function decorateSettingsForm(array $form, FormStateInterface $form_state, SKUTypeInterface $sku_type) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function saveSettingsForm(array $form, FormStateInterface $form_state, SKUTypeInterface $sku_type) {
  }

  /**
   * {@inheritdoc}
   */
  public function isProductInStock(SKU $sku) {
    $static = &drupal_static(self::class . '_' . __FUNCTION__, []);

    $sku_string = $sku->getSku();

    if (isset($static[$sku_string])) {
      return $static[$sku_string];
    }

    $static[$sku_string] = $this->getStockManager()->isProductInStock($sku);

    return $static[$sku_string];
  }

  /**
   * {@inheritdoc}
   */
  public function getStock($sku) {
    $static = &drupal_static(self::class . '_' . __FUNCTION__, []);

    $sku_string = ($sku instanceof SKU) ? $sku->getSku() : $sku;

    if (isset($static[$sku_string])) {
      return $static[$sku_string];
    }

    $static[$sku_string] = $this->getStockManager()->getStockQuantity($sku_string);

    return $static[$sku_string];
  }

  /**
   * {@inheritdoc}
   */
  public function refreshStock(SKU $sku) {
    $this->getStockManager()->refreshStock($sku->getSku());
  }

  /**
   * Get stock manager service instance.
   *
   * @return \Drupal\acm_sku\StockManager
   *   Stock Manager service.
   */
  protected function getStockManager() {
    static $manager;

    if (!isset($manager)) {
      /** @var \Drupal\acm_sku\StockManager $manager */
      $manager = \Drupal::service('acm_sku.stock_manager');
    }

    return $manager;
  }

  /**
   * Get all parent skus of a given sku.
   *
   * @param string $sku
   *   Sku string.
   *
   * @return array
   *   All parent skus with sku as key and id as value.
   */
  public function getAllParentSkus(string $sku) {
    $static = &drupal_static(__FUNCTION__, []);

    if (isset($static[$sku])) {
      return $static[$sku];
    }

    $query = $this->connection->select('acm_sku_field_data', 'acm_sku');
    $query->addField('acm_sku', 'sku');
    $query->addField('acm_sku', 'id');
    $query->join('acm_sku__field_configured_skus', 'child_sku', 'acm_sku.id = child_sku.entity_id');
    $query->condition('child_sku.field_configured_skus_value', $sku);
    $static[$sku] = $query->execute()->fetchAllKeyed(0, 1) ?? [];

    return $static[$sku];
  }

}
