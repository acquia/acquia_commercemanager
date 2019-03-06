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
use Drupal\node\Entity\Node;

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

    /** @var \Drupal\acm_sku\CartFormHelper $helper */
    $helper = \Drupal::service('acm_sku.cart_form_helper');

    $configurable_weights = $helper->getConfigurableAttributeWeights(
      $sku->get('attribute_set')->getString()
    );

    foreach ($configurables as $configurable) {
      $attribute_code = $configurable['code'];

      $options = [];

      foreach ($configurable['values'] as $value) {
        $options[$value['value_id']] = $value['label'];
      }

      // Sort the options.
      if (!empty($options)) {

        // Sort config options before pushing them to the select list based on
        // the config.
        if ($helper->isAttributeSortable($attribute_code)) {
          $sorted_options = self::sortConfigOptions($options, $attribute_code);
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
            'callback' => [get_class($this), 'configurableAjaxCallback'],
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
      '#access' => $helper->showQuantity(),
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

    /** @var \Drupal\acm_sku\CartFormHelper $helper */
    $helper = \Drupal::service('acm_sku.cart_form_helper');

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
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('acm_sku');

      $view = $view_builder->view($tree_pointer);

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

        // Sort config options before pushing them to the select list based on
        // the config.
        if ($helper->isAttributeSortable($key)) {
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
        $this->refreshStock($tree_pointer);

        // Dispatch event so action can be taken.
        $dispatcher = \Drupal::service('event_dispatcher');
        $event = new AddToCartErrorEvent($e);
        $dispatcher->dispatch(AddToCartErrorEvent::SUBMIT, $event);
      }
    }
    else {
      $message = t('The current selection does not appear to be valid.');
      \Drupal::messenger($message);
      // Dispatch event so action can be taken.
      $dispatcher = \Drupal::service('event_dispatcher');
      $exception = new \Exception($message);
      $event = new AddToCartErrorEvent($exception);
      $dispatcher->dispatch(AddToCartErrorEvent::SUBMIT, $event);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processImport(SKUInterface $configuredSkuEntity, array $product) {
    $configuredSkuEntity->field_configurable_attributes->value =
      serialize($product['extension']['configurable_product_options']);

    $this->extractConfigurableOptions(
      $configuredSkuEntity->get('attribute_set')->getString(),
      $product['extension']['configurable_product_options']
    );

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

    $tree = [
      'parent' => $sku,
      'products' => self::getChildren($sku),
      'combinations' => [],
    ];

    $configurables = unserialize(
      $sku->get('field_configurable_attributes')->getString()
    );

    $tree['configurables'] = [];
    foreach ($configurables as $configurable) {
      $tree['configurables'][$configurable['code']] = $configurable;
    }

    $configurable_codes = array_keys($tree['configurables']);

    foreach ($tree['products'] ?? [] as $sku_code => $sku_entity) {
      $attributes = $sku_entity->get('attributes')->getValue();
      $attributes = array_column($attributes, 'value', 'key');
      foreach ($configurable_codes as $code) {
        $value = $attributes[$code] ?? '';

        if (empty($value)) {
          continue;
        }

        $tree['combinations']['by_sku'][$sku_code][$code] = $value;
      }
    }

    /** @var \Drupal\acm_sku\CartFormHelper $helper */
    $helper = \Drupal::service('acm_sku.cart_form_helper');

    $configurable_weights = $helper->getConfigurableAttributeWeights(
      $sku->get('attribute_set')->getString()
    );

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
  public static function findProductInTreeWithConfig(array $tree, array $config) {
    if (isset($tree['products'])) {
      $attributes = [];
      foreach ($config as $key => $value) {
        $attributes[$key] = $value;
      }

      foreach ($tree['combinations']['by_sku'] ?? [] as $sku => $sku_attributes) {
        if (count(array_intersect_assoc($sku_attributes, $attributes)) === count($sku_attributes)) {
          return $tree['products'][$sku];
        }
      }
    }

    return NULL;
  }

  /**
   * Get attribute value from key-value field.
   *
   * @param int|\Drupal\acm_sku\Entity\SKUInterface $sku
   *   Entity id of the SKU.
   * @param string $key
   *   Name of attribute.
   *
   * @return string|null
   *   Value of field or null if empty.
   */
  public function getAttributeValue($sku, $key) {
    $id = $sku instanceof SKUInterface ? $sku->id() : $sku;

    $query = \Drupal::database()->select('acm_sku__attributes', 'acm_sku__attributes');
    $query->addField('acm_sku__attributes', 'attributes_value');
    $query->condition("acm_sku__attributes.entity_id", $id);
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
    foreach ($configurables ?? [] as $configurable) {
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

    // If the cart name has already been constructed and is rendered as a link,
    // use the title directly.
    if (!empty($cart['name']['#title'])) {
      $cartName = $cart['name']['#title'];
    }
    else {
      // Create name from label parts.
      $cartName = sprintf(
        '%s (%s)',
        $cart['name'],
        implode(', ', $label_parts)
      );
    }

    if (!$asString) {
      $display_node = $this->getDisplayNode($parent_sku);
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
   * Extract configurable options.
   *
   * Extract new configurable options during import and store them.
   *
   * @param string $attribute_set
   *   Attribute set.
   * @param array $configurable_options
   *   Array with configurable options.
   */
  protected function extractConfigurableOptions($attribute_set, array $configurable_options) {
    /** @var \Drupal\acm_sku\CartFormHelper $helper */
    $helper = \Drupal::service('acm_sku.cart_form_helper');

    // Load existing options.
    $existing_options = $helper->getConfigurableAttributeWeights($attribute_set);

    // Transform incoming options.
    foreach ($configurable_options as $configurable) {
      $existing_options[$configurable['code']] = $configurable['position'];
    }

    // Save options.
    $helper->setConfigurableAttributeWeights($attribute_set, $existing_options);
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
        $child_sku = $child_sku->getString();
        $child_stock = (int) $this->getStock($child_sku, $reset);
        $quantities[$child_sku] = $child_stock;
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

  /**
   * Wrapper function to get available children for a configurable SKU.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   Configurable SKU.
   *
   * @return array
   *   Full loaded child SKUs.
   */
  public static function getChildren(SKU $sku) {
    $children = [];

    foreach ($sku->get('field_configured_skus')->getValue() as $child) {
      if (empty($child['value'])) {
        continue;
      }

      $child_sku = SKU::loadFromSku($child['value']);
      if ($child_sku instanceof SKU) {
        $children[$child_sku->getSKU()] = $child_sku;
      }
    }

    return $children;
  }

}
