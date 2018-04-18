<?php

namespace Drupal\acm_sku\Plugin\AcquiaCommerce\SKUType;

use Drupal\acm\Connector\APIWrapper;
use Drupal\acm_sku\AcquiaCommerce\SKUPluginBase;
use Drupal\acm_sku\Entity\SKUInterface;
use Drupal\acm_sku\ProductOptionsManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\acm_sku\Entity\SKU;
use Drupal\Core\Link;
use Drupal\acm_sku\AddToCartErrorEvent;

/**
 * Defines the configurable SKU type.
 *
 * @SKUType(
 *   id = "configurable",
 *   label = @Translation("Configurable SKU"),
 *   description = @Translation("Configurable SKU for picking out a product."),
 * )
 */
class Configurable extends SKUPluginBase {

  /**
   * {@inheritdoc}
   */
  public function addToCartForm(array $form, FormStateInterface $form_state, SKU $sku = NULL) {
    if (empty($sku)) {
      return $form;
    }

    $form_state->set('tree', $this->deriveProductTree($sku));

    $form['ajax'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['configurable_ajax'],
      ],
    ];

    $form['ajax']['configurables'] = [
      '#tree' => TRUE,
    ];

    $configurables = unserialize($sku->field_configurable_attributes->getString());
    $configurable_form_settings = \Drupal::service('config.factory')->get('acm_sku.configurable_form_settings');
    $configurable_weights = $configurable_form_settings->get('attribute_weights');

    foreach ($configurables as $configurable) {
      $attribute_code = $configurable['code'];

      $options = [];

      foreach ($configurable['values'] as $value) {
        $options[$value['value_id']] = $value['label'];
      }

      // Sort the options.
      if (!empty($options)) {

        $hasSortableOptions = (
          $configurable_form_settings->get('sortable_options')
          && in_array($attribute_code, $configurable_form_settings->get('sortable_options'))
        );

        // Sort config options before pushing them to the select list based on
        // the config.
        if ($hasSortableOptions) {
          $sorted_options = $this->sortConfigOptions($options, $attribute_code);
        }
        else {
          // Use this in case the attribute is not sortable as per the config.
          $sorted_options = $options;
        }

        $form['ajax']['configurables'][$attribute_code] = [
          '#type' => 'select',
          '#title' => $configurable['label'],
          '#options' => $sorted_options,
          '#weight' => $configurable_weights[$attribute_code],
          '#required' => TRUE,
          '#ajax' => [
            'callback' => [$this, 'configurableAjaxCallback'],
            'progress' => [
              'type' => 'throbber',
              'message' => NULL,
            ],
            'wrapper' => 'configurable_ajax',
          ],
        ];
      }
      else {
        \Drupal::logger('acm_sku')->info('Product with sku: @sku seems to be configurable without any config options.', ['@sku' => $sku->getSku()]);
      }
    }

    $form['sku_id'] = [
      '#type' => 'hidden',
      '#value' => $sku->id(),
    ];

    $form['quantity'] = [
      '#title' => t('Quantity'),
      '#type' => 'number',
      '#default_value' => 1,
      '#required' => TRUE,
      '#access' => $configurable_form_settings->get('show_quantity'),
      '#size' => 2,
      '#attributes' => [
        'min' => '0',
      ],
    ];

    $form['add_to_cart'] = [
      '#type' => 'submit',
      '#value' => t('Add to cart'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberFormattedPrice(SKU $sku, $fetchOriginalPrice = FALSE) {
    // Fetch the config.
    $config = $this->configFactory->get('acm.currency');

    $configuredSkus = $sku->get('field_configured_skus')->getValue();

    $priceField = "price";
    if ($fetchOriginalPrice) {
      $priceField = "price_original";
    }

    $price = NULL;
    $max_price = 0;
    $min_price = NULL;
    foreach ($configuredSkus as $configuredSkuCode) {
      // Load configured SKU entity.
      $configuredSku = SKU::loadFromSku($configuredSkuCode['value']);

      $price = $configuredSku->get($priceField)->value;
      if ($price < $min_price || $min_price === NULL) {
        $min_price = $price;
      }
      if ($price > $max_price) {
        $max_price = $price;
      }
    }

    if ($max_price != $min_price) {
      $formattedMinPrice = \Drupal::service('acm.i18n_helper')->formatPrice($min_price);
      $formattedMaxPrice = \Drupal::service('acm.i18n_helper')->formatPrice($max_price);
      $formattedPrice = [
        'min_price' => $formattedMinPrice,
        'max_price' => $formattedMaxPrice,
      ];
    }
    else {
      // It isn't a price range.
      $formattedPrice = \Drupal::service('acm.i18n_helper')->formatPrice($max_price);
    }

    return $formattedPrice;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminGridDisplayFormattedPrice(SKU $sku, $returnOriginal = FALSE) {
    $prices = $this->getNumberFormattedPrice($sku);
    if (is_array($prices)) {
      $config = $this->configFactory->get('acm.currency');
      $formatString = $config->get('price_range_format_string');
      $tokenizedPrices = [
        '@min' => $prices['min_price'],
        '@max' => $prices['max_price'],
      ];
      // @TODO(mirom): Rebuild using tokens.
      $formattedPrice = str_replace(array_keys($tokenizedPrices), $tokenizedPrices, $formatString);
    }
    else {
      $formattedPrice = $prices;
    }
    return $formattedPrice;
  }

  /**
   * Updates the form based on selections.
   *
   * @param array $form
   *   Array of form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Values of form.
   *
   * @return array
   *   Array with dynamic parts of the form.
   */
  public static function configurableAjaxCallback(array $form, FormStateInterface $form_state) {
    $dynamic_parts = &$form['ajax'];

    $configurables = $form_state->getValue('configurables');
    $configurable_form_settings = \Drupal::service('config.factory')->get('acm_sku.configurable_form_settings');
    $tree = $form_state->get('tree');
    $tree_pointer = &$tree['options'];

    foreach ($configurables as $key => $value) {
      if (empty($value)) {
        continue;
      }

      // Move the tree pointer if the selection is valid.
      if (isset($tree_pointer["$key:$value"])) {
        $tree_pointer = &$tree_pointer["$key:$value"];
      }
    }

    if ($tree_pointer instanceof SKU) {
      $plugin = $tree_pointer->getPluginInstance();

      $view_builder = \Drupal::entityTypeManager()
        ->getViewBuilder('acm_sku');

      $view = $view_builder
        ->view($tree_pointer);

      // Block add to cart render because Form API won't allow AJAX Formception.
      $view['#no_add_to_cart'] = TRUE;

      $dynamic_parts['add_to_cart'] = [
        'entity_render' => ['#markup' => render($view)],
      ];
    }
    else {
      $available_config = $tree_pointer['#available_config'];

      foreach ($available_config as $key => $config) {
        $options = [
          '' => $dynamic_parts['configurables']['color']['#options'][''],
        ];

        foreach ($config['values'] as $value) {
          $options[$value['value_id']] = $value['label'];
        }

        $hasSortableOptions = (
          $configurable_form_settings->get('sortable_options')
          && in_array($key, $configurable_form_settings->get('sortable_options'))
        );

        // Sort config options before pushing them to the select list based on
        // the config.
        if ($hasSortableOptions) {
          // Make sure the first element in the list is the empty option.
          $sorted_options = [
            '' => $dynamic_parts['configurables'][$key]['#options'][''],
          ];
          $sorted_options += self::sortConfigOptions($options, $key);
        }
        else {
          // Use this in case the attribute is not sortable as per the config.
          $sorted_options = $options;
        }

        $dynamic_parts['configurables'][$key]['#options'] = $sorted_options;
      }
    }

    return $dynamic_parts;
  }

  /**
   * {@inheritdoc}
   */
  public function addToCartSubmit(array &$form, FormStateInterface $form_state) {
    $quantity = $form_state->getValue('quantity');
    $configurables = $form_state->getValue('configurables');
    $tree = $form_state->get('tree');
    $tree_pointer = &$tree['options'];

    foreach ($configurables as $key => $value) {
      if (empty($value)) {
        continue;
      }

      // Move the tree pointer if the selection is valid.
      if (isset($tree_pointer["$key:$value"])) {
        $tree_pointer = &$tree_pointer["$key:$value"];
      }
    }

    if ($tree_pointer instanceof SKU) {

      /* @var \Drupal\acm_cart\CartStorageInterface */
      $cartStorage = \Drupal::service('acm_cart.cart_storage');

      /* @var \Drupal\acm_cart\CartInterface */
      $cart = $cartStorage->loadCart(TRUE);

      // Cart here can be empty only if APIs aren't working.
      // Call above is to create cart if empty, we except a new or old cart here
      // and it can be empty if server is not working or in maintenance mode.
      if (empty($cart)) {
        drupal_set_message(t('An error occured, please try again.'), 'error');
        $e = new \Exception(acm_api_down_global_error_message(), APIWrapper::API_DOWN_ERROR_CODE);
        // Dispatch event so action can be taken.
        $dispatcher = \Drupal::service('event_dispatcher');
        $event = new AddToCartErrorEvent($e);
        $dispatcher->dispatch(AddToCartErrorEvent::SUBMIT, $event);

        return;
      }
      $options = [];
      $label_parts = [];
      $configurables_form = $form['ajax']['configurables'];

      foreach ($configurables as $option_name => $option_value) {
        $options[] = [
          'option_id' => $tree['configurables'][$option_name]['attribute_id'],
          'option_value' => $option_value,
        ];

        $label_parts[] = sprintf(
          '%s: %s',
          $tree['configurables'][$option_name]['label'],
          $configurables_form[$option_name]['#options'][$option_value]
        );
      }

      $label = sprintf(
        '%s (%s)',
        $tree['parent']->label(),
        implode(', ', $label_parts)
      );

      drupal_set_message(
        t('Added @quantity of @name to the cart.',
          [
            '@quantity' => $quantity,
            '@name' => $label,
          ]
        ));
      try {
        $cartStorage->addRawItemToCart([
          'name' => $label,
          'sku' => $tree['parent']->getSKU(),
          'qty' => $quantity,
          'options' => [
            'configurable_item_options' => $options,
          ],
        ]);

        // Add child SKU to form state to allow other modules to use it.
        $form_state->setTemporaryValue('child_sku', $tree_pointer->getSKU());
        $cartStorage->updateCart();
      }
      catch (\Exception $e) {
        if (acm_is_exception_api_down_exception($e)) {
          // Remove item from cart (because we can't restore the cart
          // if the Commerce Connector is unavailable)
          $cartStorage->removeItemFromCart($tree_pointer->getSku());
        }

        // Clear stock cache.
        $tree_pointer->clearStockCache();

        // Dispatch event so action can be taken.
        $dispatcher = \Drupal::service('event_dispatcher');
        $event = new AddToCartErrorEvent($e);
        $dispatcher->dispatch(AddToCartErrorEvent::SUBMIT, $event);
      }
    }
    else {
      drupal_set_message(t('The current selection does not appear to be valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processImport(SKUInterface $configuredSkuEntity, array $product) {
    $configuredSkuEntity->field_configurable_attributes->value =
      serialize($product['extension']['configurable_product_options']);

    $this->extractConfigurableOptions($product['extension']['configurable_product_options']);

    $simpleSkuValues = [];
    $skippedAtLeastOneSimple = FALSE;

    $price = NULL;
    $max_price = 0;
    $min_price = NULL;

    foreach ($product['extension']['configurable_product_links'] as $product) {

      $simpleSkuEntity = SKU::loadFromSku($product['sku']);

      if ($simpleSkuEntity === NULL) {
        $skippedAtLeastOneSimple = TRUE;
        $message = "Configured product " . $configuredSkuEntity->name->value . " (" . $configuredSkuEntity->getSku() . ") was imported before its underlying simple, " . $product['sku'] . ", had been created. Please try manually synchronizing the products again assuming that the simple product was created later in this run.";
        \Drupal::logger('acm_sku')->error($message);
        continue;
      }

      // Set the simple SKU only if it exists in the database
      // (otherwise, much later, the product won't work on the front end).
      $simpleSkuValues[] = ['value' => $product['sku']];

      $price = (float) $simpleSkuEntity->price->first()->value;

      if ($price < $min_price || $min_price === NULL) {
        $min_price = $price;
      }

      if ($price > $max_price) {
        $max_price = $price;
      }
    }

    if (count($simpleSkuValues) == 0) {
      // We might return false here (not completely processed)
      // but it is safe to continue.
      // Price will be null and configured skus will be empty.
    }

    if ($price === NULL) {
      // Then it wasn't set because the simples aren't in the Drupal DB yet.
      // In this case we save price = NULL to the DB (is that allowed?).
    }
    else {
      if ($max_price != $min_price) {
        // Price formatting is done later.
        // See for example /modules/acm_cart/acm_cart.module::acm_cart_theme()
        // TODO add instructions to the theming guide.
        $price = t("From @min to @max", ['@min' => $min_price, '@max' => $max_price]);
      }
      else {
        $price = $max_price;
      }
    }

    $configuredSkuEntity->price->value = $price;
    $configuredSkuEntity->get('field_configured_skus')->setValue($simpleSkuValues);

    if ($skippedAtLeastOneSimple) {
      // Indicate this configurable was not fully processed.
      return FALSE;
    }
    else {
      // Indicate this configurable was fully processed.
      return TRUE;
    }

  }

  /**
   * Builds a display tree.
   *
   * Helps to determine which products belong to which combination of
   * configurables.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   Object of SKU.
   *
   * @return array
   *   Configurables tree.
   */
  public function deriveProductTree(SKU $sku) {
    static $cache = [];

    if (isset($cache[$sku->language()->getId()], $cache[$sku->language()->getId()][$sku->id()])) {
      return $cache[$sku->language()->getId()][$sku->id()];
    }

    $tree = ['parent' => $sku];

    foreach ($sku->field_configured_skus as $child_sku) {
      $child_sku = SKU::loadFromSku($child_sku->getString());
      if ($child_sku) {
        $tree['products'][$child_sku->getSku()] = $child_sku;
      }
    }

    $configurables = unserialize(
      $sku->field_configurable_attributes->getString()
    );

    $tree['configurables'] = [];
    foreach ($configurables as $configurable) {
      $tree['configurables'][$configurable['code']] = $configurable;
    }

    $configurable_weights = \Drupal::service('config.factory')->get('acm_sku.configurable_form_settings')->get('attribute_weights');

    // Sort configurables based on the config.
    uasort($tree['configurables'], function ($a, $b) use ($configurable_weights) {
      return $configurable_weights[$a['code']] - $configurable_weights[$b['code']];
    });

    $tree['options'] = Configurable::recursiveConfigurableTree(
      $tree,
      $tree['configurables']
    );

    $cache[$sku->language()->getId()][$sku->id()] = $tree;

    return $tree;
  }

  /**
   * Creates subtrees based on available config.
   *
   * @param array $tree
   *   Tree of products.
   * @param array $available_config
   *   Available configs.
   * @param array $current_config
   *   Config of current product.
   *
   * @return array
   *   Subtree.
   */
  public static function recursiveConfigurableTree(array &$tree, array $available_config, array $current_config = []) {
    $subtree = ['#available_config' => $available_config];

    foreach ($available_config as $id => $config) {
      $subtree_available_config = $available_config;
      unset($subtree_available_config[$id]);

      foreach ($config['values'] as $option) {
        $value = $option['value_id'];
        $subtree_current_config = array_merge($current_config, [$id => $value]);

        if (count($subtree_available_config) > 0) {
          $subtree["$id:$value"] = Configurable::recursiveConfigurableTree(
            $tree,
            $subtree_available_config,
            $subtree_current_config
          );
        }
        else {
          $subtree["$id:$value"] = Configurable::findProductInTreeWithConfig(
            $tree,
            $subtree_current_config
          );
        }
      }
    }

    return $subtree;
  }

  /**
   * Finds product in tree base on config.
   *
   * @param array $tree
   *   The whole configurable tree.
   * @param array $config
   *   Config for the product.
   *
   * @return \Drupal\acm_sku\Entity\SKU
   *   Reference to SKU in existing tree.
   */
  public static function &findProductInTreeWithConfig(array &$tree, array $config) {
    $response = NULL;
    if (isset($tree['products'])) {
      $child_skus = array_keys($tree['products']);
      $query = \Drupal::database()->select('acm_sku_field_data', 'acm_sku');
      $query->addField('acm_sku', 'sku');
      if (!empty($child_skus)) {
        $query->condition('sku', $child_skus, 'IN');
      }
      foreach ($config as $key => $value) {
        $query->join('acm_sku__attributes', $key, "acm_sku.id = $key.entity_id");
        $query->condition("$key.attributes_key", $key);
        $query->condition("$key.attributes_value", $value);
      }
      $sku = $query->execute()->fetchField();
      return $tree['products'][$sku];
    }
    else {
      return $response;
    }
  }

  /**
   * Get attribute value from key-value field.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   The object of product.
   * @param string $key
   *   Name of attribute.
   *
   * @return string|null
   *   Value of field or null if empty.
   */
  public function getAttributeValue(SKU $sku, $key) {
    $query = \Drupal::database()->select('acm_sku__attributes', 'acm_sku__attributes');
    $query->addField('acm_sku__attributes', 'attributes_value');
    $query->condition("acm_sku__attributes.entity_id", $sku->id());
    $query->condition("acm_sku__attributes.attributes_key", $key);
    return $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function cartName(SKU $sku, array $cart, $asString = FALSE) {
    $parent_sku = $this->getParentSku($sku);
    if (empty($parent_sku)) {
      return $sku->label();
    }

    $configurables = unserialize(
      $parent_sku->field_configurable_attributes->getString()
    );

    $label_parts = [];
    foreach ($configurables as $configurable) {
      $key = $configurable['code'];
      $attribute_value = $this->getAttributeValue($sku, $key);
      $label = $configurable['label'];

      foreach ($configurable['values'] as $value) {
        if ($attribute_value == $value['value_id']) {
          $label_parts[] = sprintf(
            '%s: %s',
            $label,
            $value['label']
          );
        }
      }
    }

    // Create name from label parts.
    $cartName = sprintf(
      '%s (%s)',
      $cart['name'],
      implode(', ', $label_parts)
    );

    if (!$asString) {
      $display_node = $this->getDisplayNode($parent_sku);
      $url = $display_node->toUrl();
      $link = Link::fromTextAndUrl($cartName, $url);
      $cartName = $link->toRenderable();
    }

    return $cartName;
  }

  /**
   * Extract configurable options.
   *
   * Extract new configurable options during import and store them.
   *
   * @param array $configurable_options
   *   Array with configurable options.
   */
  protected function extractConfigurableOptions(array $configurable_options) {
    // Load existing options.
    $existing_options = \Drupal::configFactory()
      ->get('acm_sku.configurable_form_settings')
      ->get('attribute_weights');
    // Transform incoming options.
    foreach ($configurable_options as $configurable) {
      $existing_options[$configurable['code']] = $configurable['position'];
    }
    // Save options.
    \Drupal::configFactory()
      ->getEditable('acm_sku.configurable_form_settings')
      ->set('attribute_weights', $existing_options)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessedStock(SKU $sku, $reset = FALSE) {
    $stock = &drupal_static('stock_static_cache', []);

    if (!$reset && isset($stock[$sku->getSku()])) {
      return $stock[$sku->getSku()];
    }

    $quantities = [];

    foreach ($sku->get('field_configured_skus') as $child_sku) {
      try {
        $child_sku_entity = SKU::loadFromSku($child_sku->getString());

        if ($child_sku_entity instanceof SKU) {
          $child_stock = (int) $this->getStock($child_sku_entity, $reset);

          $quantities[$child_sku_entity->getSku()] = $child_stock;
        }
      }
      catch (\Exception $e) {
        // Child SKU might be deleted or translation not available.
        // Log messages are already set in previous functions.
      }
    }

    $stock[$sku->getSku()] = empty($quantities) ? 0 : max($quantities);

    return $stock[$sku->getSku()];
  }

  /**
   * Helper function to sort config options based on taxonomy term weight.
   *
   * @param array $options
   *   Option values keyed by option id.
   * @param string $attribute_code
   *   Attribute name.
   *
   * @return array
   *   Array of options sorted based on term weight.
   */
  public static function sortConfigOptions(array &$options, $attribute_code) {
    $sorted_options = [];

    $query = \Drupal::database()->select('taxonomy_term_field_data', 'ttfd');
    $query->fields('ttfd', ['tid', 'weight']);
    $query->join('taxonomy_term__field_sku_attribute_code', 'ttfsac', 'ttfsac.entity_id = ttfd.tid');
    $query->join('taxonomy_term__field_sku_option_id', 'ttfsoi', 'ttfsoi.entity_id = ttfd.tid');
    $query->fields('ttfsoi', ['field_sku_option_id_value']);
    $query->condition('ttfd.vid', ProductOptionsManager::PRODUCT_OPTIONS_VOCABULARY);
    $query->condition('ttfsac.field_sku_attribute_code_value', $attribute_code);
    $query->condition('ttfsoi.field_sku_option_id_value', array_keys($options), 'IN');
    $query->distinct();
    $query->orderBy('weight', 'ASC');
    $tids = $query->execute()->fetchAllAssoc('tid');

    foreach ($tids as $tid => $values) {
      $sorted_options[$values->field_sku_option_id_value] = $options[$values->field_sku_option_id_value];
    }

    return $sorted_options;
  }

}
