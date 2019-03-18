<?php

namespace Drupal\acm_sku\Plugin\AcquiaCommerce\SKUType;

use Drupal\acm_sku\AcquiaCommerce\SKUPluginBase;
use Drupal\acm_sku\AddToCartErrorEvent;
use Drupal\acm_sku\Entity\SKU;
use Drupal\acm_sku\Entity\SKUInterface;
use Drupal\acm_sku\Entity\SKUType;
use Drupal\acm_sku\Entity\SKUTypeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\node\Entity\Node;

/**
 * Defines the Variant SKU type.
 *
 * @SKUType(
 *   id = "variant",
 *   label = @Translation("Variant SKU"),
 *   description = @Translation("Variant SKU for picking out a product based on configurable options."),
 * )
 */
class Variant extends SKUPluginBase {

  /**
   * {@inheritdoc}
   */
  public function decorateSettingsForm(array $form, FormStateInterface $form_state, SKUTypeInterface $sku_type) {
    $groups = $sku_type->getThirdPartySetting('acm_sku', 'attribute_groups', []);

    $connection = \Drupal::database();
    $attributes = $connection->select('acm_sku__attributes', 'sku')
      ->fields('sku', ['attributes_key'])
      ->distinct(TRUE)
      ->execute()
      ->fetchAll();

    $form['attributes'] = [
      '#type' => 'table',
      '#header' => [
        t('Enabled'),
        t('Attribute Name'),
        t('Attribute Label'),
        t('Weight'),
      ],
      '#empty' => t('There are no attributes yet.'),
      '#tree' => TRUE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
    ];

    $weight_delta = round(count($attributes) / 2);

    // Build rows by saved groups.
    foreach ($groups as $key => $group) {
      $row = $this->buildAttributeRow($key, $group);
      if (isset($row['weight'])) {
        $row['weight']['#delta'] = $weight_delta;
      }
      $form['attributes'][$key] = $row;
    }

    // Add any new attributes to the bottom.
    foreach ($attributes as $attribute) {
      $key = $attribute->attributes_key;
      if (isset($form['attributes'][$key])) {
        continue;
      }

      $row = $this->buildAttributeRow($key);
      if (isset($row['weight'])) {
        $row['weight']['#delta'] = $weight_delta;
      }
      $form['attributes'][$key] = $row;
    }

    return $form;
  }

  /**
   * Builds a row for the attributes form.
   *
   * @param string $key
   *   The attribute name/key.
   * @param array $group
   *   The saved attribute values.
   *
   * @return array
   *   A table form row render array.
   */
  public function buildAttributeRow($key, array $group = []) {
    $weight = isset($group['weight']) ? $group['weight'] : 0;
    $row = [];

    $row['enabled'] = [
      '#type' => 'checkbox',
      '#default_value' => isset($group['enabled']) ? $group['enabled'] : 0,
    ];

    $row['name'] = [
      '#plain_text' => $key,
    ];

    $row['label'] = [
      '#type' => 'textfield',
      '#default_value' => isset($group['label']) ? $group['label'] : '',
    ];

    $row['weight'] = [
      '#type' => 'weight',
      '#title' => t('Weight'),
      '#title_display' => 'invisible',
      '#default_value' => $weight,
      '#attributes' => ['class' => ['weight']],
    ];

    $row['#attributes']['class'][] = 'draggable';
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function saveSettingsForm(array $form, FormStateInterface $form_state, SKUTypeInterface $sku_type) {
    $attributes = $form_state->getValue('attributes');
    $sku_type->setThirdPartySetting('acm_sku', 'attribute_groups', $attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function addToCartForm(array $form, FormStateInterface $form_state, SKU $sku = NULL) {
    if (empty($sku)) {
      return $form;
    }

    $sku_type = SKUType::load($this->getPluginDefinition()['id']);
    $groups = $sku_type->getThirdPartySetting('acm_sku', 'attribute_groups', []);

    $form['configurables'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $configured_skus = $sku->field_configured_skus->value;

    if (empty($configured_skus)) {
      return $form;
    }

    $child = SKU::loadFromSku($configured_skus);
    $configured_skus = $sku->field_configured_skus->getValue();

    // Build configurable option groups based on the groups defined on the sku
    // type.
    $group_config = [];
    foreach ($groups as $attribute => $attribute_config) {
      if (empty($attribute_config['enabled'])) {
        continue;
      }
      $group_config[$attribute] = [
        'label' => $attribute_config['label'],
        'options' => [],
      ];
    }

    // Loop through each linked sku to get it's attributes to place in the
    // correct group.
    $variant_tree = [];
    $variant_prices = [];
    $default_options = [];
    $default_selected = FALSE;
    foreach ($configured_skus as $configured_sku) {
      $child = SKU::loadFromSku($configured_sku['value']);

      $variant_prices[$configured_sku['value']] = $child->getNumberFormattedPrice();

      $attributes = $child->attributes->getValue();
      foreach ($attributes as $attribute) {
        foreach ($group_config as $group_attribute => $group_attribute_config) {
          if ($attribute['key'] != $group_attribute) {
            continue;
          }
          $option_name = $attribute['value'];
          $group_config[$group_attribute]['options'][$option_name] = $option_name;

          // Build the attributes tree so we know if a product is still
          // available after an option is selected.
          $variant_tree[$group_attribute][$option_name][] = $configured_sku['value'];

          if (!$default_selected) {
            $default_options[$group_attribute] = $option_name;
          }
        }
      }

      $default_selected = TRUE;
    }

    // Build the configurable selects fields.
    foreach ($group_config as $attribute => $config) {
      $options = $config['options'];
      if (empty($options)) {
        continue;
      }

      // Sort each group's options alphabetically.
      asort($options);

      $form['configurables'][$attribute] = [
        '#type' => 'select',
        '#title' => $config['label'],
        '#options' => $options,
        '#required' => TRUE,
        '#default_value' => isset($default_options[$attribute]) ? $default_options[$attribute] : NULL,
        '#attributes' => [
          'class' => ['acm-sku__variant-group'],
          'data-product-attribute' => $attribute,
        ],
      ];
    }

    $form['quantity'] = [
      '#title' => t('Quantity'),
      '#type' => 'number',
      '#default_value' => 1,
      '#required' => TRUE,
      '#size' => 2,
      '#attributes' => [
        'min' => '0',
      ],
    ];

    $form['add_to_cart'] = [
      '#type' => 'submit',
      '#value' => t('Add to cart'),
    ];

    $form['#attributes']['data-acm-sku-id'] = $sku->id();
    $form['#attached']['library'][] = 'acm_sku/variant';
    $form['#prefix'] = '<div class="acm-sku__variant-prices"></div>';
    $form['#attached']['drupalSettings']['acm_sku'][$sku->id()]['variant_tree'] = $variant_tree;
    $form['#attached']['drupalSettings']['acm_sku'][$sku->id()]['variant_prices'] = $variant_prices;
    $form_state->set('variant_tree', $variant_tree);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function addToCartSubmit(array &$form, FormStateInterface $form_state) {
    $sku_type = SKUType::load($this->getPluginDefinition()['id']);
    $groups = $sku_type->getThirdPartySetting('acm_sku', 'attribute_groups', []);

    $quantity = $form_state->getValue('quantity');
    $configurables = $form_state->getValue('configurables');
    $variant_tree = $form_state->get('variant_tree', []);
    $available_skus = [];
    $skus_by_variant = [];
    $label_parts = [];

    // Get list of skus in each attribute group.
    foreach ($configurables as $configurable => $option) {
      // Build label to use in cart.
      $attribute_label = isset($groups[$configurable]['label']) ? $groups[$configurable]['label'] : $configurable;
      $label_parts[] = sprintf('%s: %s', $attribute_label, $option);

      $variant_options = $variant_tree[$configurable][$option];
      $skus_by_variant[$configurable] = $variant_options;
      foreach ($variant_options as $sku) {
        $available_skus[$sku] = $sku;
      }
    }

    // Determine which option was selected by finding the SKU that exists in
    // all attribute groups.
    $selected_sku = NULL;
    foreach ($available_skus as $sku) {
      $matches = TRUE;
      foreach ($skus_by_variant as $group_skus) {
        if (!in_array($sku, $group_skus)) {
          $matches = FALSE;
        }
      }

      // If matches is TRUE, this is the sku we're looking for. If it's FALSE
      // it's because the sku wasn't in all of the attribute groups.
      if ($matches) {
        $selected_sku = $sku;
        break;
      }
    }

    if (!$selected_sku) {
      drupal_set_message(t('The product you configured is not available.'), 'error');
      return;
    }

    $selected_sku_entity = SKU::loadFromSku($selected_sku);
    $parent = $this->getDisplayNode($selected_sku_entity);
    $label = sprintf(
      '%s (%s)',
      $parent->label(),
      implode(', ', $label_parts)
    );

    $cartStorage = \Drupal::service('acm_cart.cart_storage');

    try {
      $cartStorage->addItemToCart($selected_sku, $quantity);

      drupal_set_message(
        t('Added @quantity of @name to the cart.',
          [
            '@quantity' => $quantity,
            '@name' => $label,
          ]
      ));

      $cartStorage->updateCart();
    }
    catch (\Exception $e) {
      if (acm_is_exception_api_down_exception($e)) {
        // Remove item from cart (because we can't restore the cart
        // if the Commerce Connector is unavailable)
        $cartStorage->removeItemFromCart($sku);
      }
      $this->refreshStock($selected_sku_entity);

      // Clear product and forms related to sku.
      Cache::invalidateTags(['acm_sku:' . $selected_sku_entity->id()]);

      // Dispatch event so action can be taken.
      $dispatcher = \Drupal::service('event_dispatcher');
      $event = new AddToCartErrorEvent($e);
      $dispatcher->dispatch(AddToCartErrorEvent::SUBMIT, $event);
    }
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
   * {@inheritdoc}
   */
  public function processImport(SKUInterface $configuredSkuEntity, array $product) {
    $configuredSkuEntity->field_configurable_attributes->value =
      serialize($product['extension']['configurable_product_options']);

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
   * {@inheritdoc}
   */
  public function cartName(SKU $sku, array $cart, $asString = FALSE) {
    $sku_type = SKUType::load($this->getPluginDefinition()['id']);
    $groups = $sku_type->getThirdPartySetting('acm_sku', 'attribute_groups', []);

    $label_parts = [];
    $attributes = $sku->attributes->getValue();

    foreach ($attributes as $attribute) {
      $key = $attribute['key'];
      if (!isset($groups[$key]) || empty($groups[$key]['enabled'])) {
        continue;
      }

      $label_key = $key;
      $attribute_config = $groups[$key];
      $attribute_label = isset($attribute_config['label']) ? $attribute_config['label'] : $key;
      if (!empty($attribute_config['weight'])) {
        $label_key = $attribute_config['weight'];
      }
      $label_parts[$label_key] = sprintf('%s: %s', $attribute_label, $attribute['value']);
    }

    $cartName = $sku->label();
    $display_node = $this->getDisplayNode($sku);
    if ($display_node instanceof Node) {
      $cartName = sprintf(
        '%s (%s)',
        $display_node->label(),
        implode(', ', $label_parts)
      );

      if (!$asString) {
        $url = $display_node->toUrl();
        $link = Link::fromTextAndUrl($cartName, $url);
        $cartName = $link->toRenderable();
      }
    }
    else {
      \Drupal::logger('acm_sku')->info('Parent product for the sku: @sku seems to be unavailable.', ['@sku' => $sku->getSku()]);
    }

    return $cartName;
  }

  /**
   * Get the configured options for this SKU instance.
   *
   * @return array
   *   An array of the configured attributes for this SKU.
   */
  public function getConfiguredOptions(SKU $child) {
    $sku_type = SKUType::load($this->getPluginDefinition()['id']);
    $groups = $sku_type->getThirdPartySetting('acm_sku__variant', 'attribute_groups', []);
    $configured_options = [];
    $attributes = $child->attributes->getValue();
    foreach ($attributes as $attribute) {
      if (empty($groups[$attribute['key']]['enabled'])) {
        continue;
      }
      $configured_options[] = $attribute['value'];
    }
    return $configured_options;
  }

}
