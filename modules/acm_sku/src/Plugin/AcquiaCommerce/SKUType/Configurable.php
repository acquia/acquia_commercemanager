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

    $form_state->set('tree_sku', $sku->getSku());

    $form['ajax'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['configurable_ajax'],
      ],
    ];

    $form['ajax']['configurables'] = [
      '#tree' => TRUE,
    ];

    $configurables = self::getSortedConfigurableAttributes($sku);

    /** @var \Drupal\acm_sku\CartFormHelper $helper */
    $helper = \Drupal::service('acm_sku.cart_form_helper');

    foreach ($configurables as $configurable) {
      $attribute_code = $configurable['code'];

      $options = [];

      foreach ($configurable['values'] as $value) {
        $options[$value['value_id']] = $value['label'];
      }

      // Sort the options.
      if (!empty($options)) {
        $sorted_options = $options;

        // Sort config options before pushing them to the select list based on
        // the config.
        if ($helper->isAttributeSortable($attribute_code)) {
          $sorted_options = self::sortConfigOptions($options, $attribute_code);
        }

        $form['ajax']['configurables'][$attribute_code] = [
          '#type' => 'select',
          '#title' => $configurable['label'],
          '#options' => $sorted_options,
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
    $sku = SKU::loadFromSku($form_state->get('tree_sku'));
    $tree = self::deriveProductTree($sku);

    $configurable_codes = array_keys($tree['configurables']);
    $combination = self::getSelectedCombination($configurables, $configurable_codes);

    $tree_pointer = NULL;
    if (!empty($tree['combinations']['by_attribute'][$combination])) {
      $tree_pointer = SKU::loadFromSku($tree['combinations']['by_attribute'][$combination]);
    }

    if ($tree_pointer instanceof SKU) {
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('acm_sku');

      $view = $view_builder->view($tree_pointer);

      // Block add to cart render because Form API won't allow AJAX Formception.
      $view['#no_add_to_cart'] = TRUE;

      $dynamic_parts['add_to_cart'] = [
        'entity_render' => ['#markup' => render($view)],
      ];

      $form_state->set('variant_sku', $tree_pointer->getSku());
    }
    else {
      $available_config = $tree_pointer['#available_config'];

      /** @var \Drupal\acm_sku\CartFormHelper $helper */
      $helper = \Drupal::service('acm_sku.cart_form_helper');

      foreach ($available_config as $key => $config) {
        $options = [
          '' => $dynamic_parts['configurables'][$key]['#options'][''],
        ];

        foreach ($config['values'] as $value) {
          $options[$value['value_id']] = $value['label'];
        }

        // Use this in case the attribute is not sortable as per the config.
        $sorted_options = $options;

        // Sort config options before pushing them to the select list based on
        // the config.
        if ($helper->isAttributeSortable($key)) {
          // Make sure the first element in the list is the empty option.
          $sorted_options = [
            '' => $dynamic_parts['configurables'][$key]['#options'][''],
          ];
          $sorted_options += self::sortConfigOptions($options, $key);
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

    $sku = SKU::loadFromSku($form_state->get('tree_sku'));
    $tree = self::deriveProductTree($sku);

    $configurable_codes = array_keys($tree['configurables']);
    $combination = self::getSelectedCombination($configurables, $configurable_codes);

    $tree_pointer = NULL;
    if (!empty($tree['combinations']['by_attribute'][$combination])) {
      $tree_pointer = SKU::loadFromSku($tree['combinations']['by_attribute'][$combination]);
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
          ])
        );

      // Allow other modules to update the options info sent to ACM.
      \Drupal::moduleHandler()->alter('acm_sku_configurable_cart_options', $options, $sku);

      // Check if item already in cart.
      // @TODO: This needs to be fixed further to handle multiple parent
      // products for a child SKU. To be done as part of CORE-7003.
      if ($cart->hasItem($tree_pointer->getSku())) {
        $cart->addItemToCart($tree_pointer->getSku(), $quantity);
      }
      else {
        $cart->addRawItemToCart([
          'name' => $label,
          'sku' => $tree['parent']->getSKU(),
          'qty' => $quantity,
          'options' => [
            'configurable_item_options' => $options,
          ],
        ]);
      }

      // Add child SKU to form state to allow other modules to use it.
      $form_state->setTemporaryValue('child_sku', $tree_pointer->getSKU());

      try {
        // Add child SKU to form state to allow other modules to use it.
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
    $configuredSkuEntity->get('field_configurable_attributes')
      ->setValue(serialize($product['extension']['configurable_product_options']));

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

      $price = (float) $simpleSkuEntity->get('price')->getString();

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

    $configuredSkuEntity->get('price')->setValue($price);
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
  public static function deriveProductTree(SKU $sku) {
    static $cache = [];

    if (isset($cache[$sku->language()->getId()], $cache[$sku->language()->getId()][$sku->id()])) {
      return $cache[$sku->language()->getId()][$sku->id()];
    }

    $tree = [
      'parent' => $sku,
      'products' => self::getChildren($sku),
      'combinations' => [],
      'configurables' => [],
    ];

    $combinations =& $tree['combinations'];

    $configurables = self::getSortedConfigurableAttributes($sku);

    foreach ($configurables ?? [] as $configurable) {
      $tree['configurables'][$configurable['code']] = $configurable;
    }

    $configurable_codes = array_keys($tree['configurables']);

    foreach ($tree['products'] ?? [] as $sku_code => $sku_entity) {
      $attributes = $sku_entity->get('attributes')->getValue();
      $attributes = array_column($attributes, 'value', 'key');
      foreach ($configurable_codes as $code) {
        $value = $attributes[$code] ?? '';

        if (empty($value)) {
          // Ignore variants with empty value in configurable options.
          unset($tree['products'][$sku_code]);
          continue;
        }

        $combinations['by_sku'][$sku_code][$code] = $value;
        $combinations['attribute_sku'][$code][$value][] = $sku_code;
      }
    }

    /** @var \Drupal\acm_sku\CartFormHelper $helper */
    $helper = \Drupal::service('acm_sku.cart_form_helper');

    // Sort the values in attribute_sku so we can use it later.
    foreach ($combinations['attribute_sku'] ?? [] as $code => $values) {
      if ($helper->isAttributeSortable($code)) {
        $combinations['attribute_sku'][$code]
          = Configurable::sortConfigOptions($values, $code);
      }
      else {
        // Sort from field_configurable_attributes.
        $configurable_attribute = [];
        foreach ($configurables as $configurable) {
          if ($configurable['code'] === $code) {
            $configurable_attribute = $configurable['values'];
            break;
          }
        }

        if ($configurable_attribute) {
          $configurable_attribute_weights = array_flip(array_column($configurable_attribute, 'value_id'));

          uksort($combinations['attribute_sku'][$code],
            function ($a, $b) use ($configurable_attribute_weights) {
              return $configurable_attribute_weights[$a]
                - $configurable_attribute_weights[$b];
            });
        }
      }
    }

    // Prepare combinations array grouped by attributes to check later which
    // combination is possible using isset().
    foreach ($combinations['by_sku'] ?? [] as $sku_string => $combination) {
      $combination_string = self::getSelectedCombination($combination);
      $combinations['by_attribute'][$combination_string] = $sku_string;
    }

    $cache[$sku->language()->getId()][$sku->id()] = $tree;

    return $cache[$sku->language()->getId()][$sku->id()];
  }

  /**
   * Get combination for selected configurable values.
   *
   * @param array $configurables
   *   Configurable values to build the combination string from.
   * @param array $configurable_codes
   *   Codes to use for sorting the values array.
   *
   * @return string
   *   Combination string.
   */
  public static function getSelectedCombination(array $configurables, array $configurable_codes = []) {
    if ($configurable_codes) {
      $selected = [];
      foreach ($configurable_codes as $code) {
        if (isset($configurables[$code])) {
          $selected[$code] = $configurables[$code];
        }
      }
      $configurables = $selected;
    }

    $combination = '';

    foreach ($configurables as $key => $value) {
      if (empty($value)) {
        continue;
      }
      $combination .= $key . '|' . $value . '||';
    }

    return $combination;
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
  public static function sortConfigOptions(array $options, $attribute_code) {
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

    foreach ($tids as $values) {
      $sorted_options[$values->field_sku_option_id_value] = $options[$values->field_sku_option_id_value];
    }

    return $sorted_options ?: $options;
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
    if ($sku->bundle() != 'configurable') {
      return [];
    }

    $static = &drupal_static(__METHOD__, []);
    $langcode = $sku->language()->getId();
    $sku_string = $sku->getSku();
    if (isset($static[$langcode][$sku_string])) {
      return $static[$langcode][$sku_string];
    }

    $children = [];

    foreach (self::getChildSkus($sku) as $child) {
      $child_sku = SKU::loadFromSku($child);
      if ($child_sku instanceof SKU) {
        $children[$child_sku->getSku()] = $child_sku;
      }
    }

    // Allow other modules to add/remove variants.
    \Drupal::moduleHandler()->alter('acm_sku_configurable_variants', $children, $sku);

    $static[$langcode][$sku_string] = $children;
    return $children;
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
   * Get sorted configurable options.
   *
   * @param \Drupal\acq_commerce\SKUInterface $sku
   *   SKU Entity.
   *
   * @return array
   *   Sorted configurable options.
   */
  public static function getSortedConfigurableAttributes(SKUInterface $sku): array {
    $static = &drupal_static(__FUNCTION__, []);

    $langcode = $sku->language()->getId();
    $sku_code = $sku->getSku();

    // Do not process the same thing again and again.
    if (isset($static[$langcode][$sku_code])) {
      return $static[$langcode][$sku_code];
    }

    $configurables = unserialize($sku->get('field_configurable_attributes')->getString());

    if (empty($configurables) || !is_array($configurables)) {
      return [];
    }

    $configurations = [];
    foreach ($configurables as $configuration) {
      $configurations[$configuration['code']] = $configuration;
    }

    \Drupal::moduleHandler()->alter('acm_sku_configurable_product_configurations', $configurations, $sku);

    /** @var \Drupal\acq_sku\CartFormHelper $helper */
    $helper = \Drupal::service('acm_sku.cart_form_helper');

    $configurable_weights = $helper->getConfigurableAttributeWeights(
      $sku->get('attribute_set')->getString()
    );

    // Sort configurations based on the config.
    uasort($configurations, function ($a, $b) use ($configurable_weights) {
      // We may keep getting new configurable options not defined in config.
      // Use default values for them and keep their sequence as is.
      // Still move the ones defined in our config as per weight in config.
      $a = $configurable_weights[$a['code']] ?? -50;
      $b = $configurable_weights[$b['code']] ?? 50;
      return $a - $b;
    });

    $static[$langcode][$sku_code] = $configurations;

    return $configurations;
  }

  /**
   * Wrapper function to get child skus as string array for configurable.
   *
   * @param \Drupal\acm_sku\Entity\SKU $sku
   *   Configurable SKU.
   *
   * @return array
   *   Child skus as string array.
   */
  public static function getChildSkus(SKU $sku) {
    return array_filter(array_map('trim', explode(',', $sku->get('field_configured_skus')->getString())));
  }

}
