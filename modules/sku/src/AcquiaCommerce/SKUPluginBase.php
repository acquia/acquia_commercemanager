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
    $query = $this->connection->select('acm_sku_field_data', 'acm_sku');
    $query->addField('acm_sku', 'sku');
    $query->join('acm_sku__field_configured_skus', 'child_sku', 'acm_sku.id = child_sku.entity_id');
    $query->condition('child_sku.field_configured_skus_value', $sku->getSku());

    $parent_sku = $query->execute()->fetchField();

    if (empty($parent_sku)) {
      return NULL;
    }

    return SKU::loadFromSku($parent_sku, $sku->language()->getId());
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
  public function getProcessedStock(SKU $sku, $reset = FALSE) {
    $stock = &drupal_static('stock_static_cache', []);

    if (!$reset && isset($stock[$sku->getSku()])) {
      return $stock[$sku->getSku()];
    }

    $stock[$sku->getSku()] = (int) $this->getStock($sku, $reset);

    return $stock[$sku->getSku()];
  }

  /**
   * Returns the stock for the given sku.
   *
   * @param string $sku
   *   SKU code of the product.
   * @param bool $reset
   *   Flag to mention if we should always try to get fresh value.
   *
   * @return array|mixed
   *   Available stock quantity.
   *
   * @throws \Exception
   */
  protected function getStock($sku, $reset = FALSE) {
    $stock_mode = \Drupal::config('acm_sku.settings')->get('stock_mode');
    $sku_string = ($sku instanceof SKU) ? $sku->getSku() : $sku;

    if (!$reset) {
      // Return from Entity field in push mode.
      if ($stock_mode == 'push') {
        if ($sku instanceof SKU) {
          $stock = $sku->get('stock')->getString();
        }
        else {
          $stock = $this->connection->select('acm_sku_field_data', 'asfd')
            ->fields('asfd', ['stock'])
            ->condition('asfd.sku', $sku_string)
            ->execute()
            ->fetchField();
        }

        // Fallback to pull mode if no value available for the SKU.
        if (!($stock === '' || $stock === NULL)) {
          return (int) $stock;
        }
      }
      // Return from Cache in Pull mode.
      else {
        // Cache id.
        $cid = 'stock:' . $sku_string;

        $cache = \Drupal::cache('stock')->get($cid);

        if (!empty($cache)) {
          return (int) $cache->data;
        }
      }
    }

    // Either reset is requested or we dont have value in attribute or we dont
    // have value in cache, we will use the API to get fresh value now.
    $stock = NULL;

    /** @var \Drupal\acm\Connector\APIWrapper $api_wrapper */
    $api_wrapper = \Drupal::service('acm.api');

    try {
      // Get the stock.
      $stock_info = $api_wrapper->skuStockCheck($sku_string);
    }
    catch (\Exception $e) {
      // Log the stock error, do not throw error if stock info is missing.
      \Drupal::logger('acm_sku')->warning('Unable to get the stock for @sku : @message', [
        '@sku' => $sku_string,
        '@message' => $e->getMessage(),
      ]);

      // We will cache this also for sometime to reduce load.
      $stock_info['is_in_stock'] = FALSE;
    }

    // Magento uses additional flag as well for out of stock.
    if (isset($stock_info['is_in_stock']) && empty($stock_info['is_in_stock'])) {
      $stock_info['quantity'] = 0;
    }

    $stock = (int) $stock_info['quantity'];

    // Save the value in SKU if we came here as fallback of push mode.
    if ($stock_mode == 'push') {
      if (!$sku instanceof SKU) {
        $sku = SKU::loadFromSku($sku_string);
      }

      $sku->get('stock')->setValue($stock);
      $sku->save();
    }
    // Save the value in cache if we are in pull mode.
    // If cache multiplier is zero we don't cache the stock.
    elseif ($cache_multiplier = \Drupal::config('acm_sku.settings')->get('stock_cache_multiplier')) {
      $default_cache_lifetime = $stock ? $stock * $cache_multiplier : $cache_multiplier;
      $max_cache_lifetime = \Drupal::config('acm_sku.settings')->get('stock_cache_max_lifetime');

      // Calculate the timestamp when we want the cache to expire.
      $stock_cache_lifetime = min($default_cache_lifetime, $max_cache_lifetime);
      $expire = $stock_cache_lifetime + \Drupal::time()->getRequestTime();

      // Set the stock in cache.
      \Drupal::cache('stock')->set($cid, $stock, $expire);
    }

    return $stock;
  }

}
