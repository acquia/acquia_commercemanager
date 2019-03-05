/**
 * @file
 * Adds functionality to the Variant product "addToCart" form.
 */

(function($, Drupal) {
  'use strict';

  Drupal.behaviors.acmSkuVariant = {
    attach: function(context, settings) {
      var $form = $('.sku-base-form', context);

      if (!$form.length) {
        return;
      }

      $form.each(function() {
        var $select = $('.acm-sku__variant-group', $form);

        if (!$select.length) {
          return;
        }

        var skuId = $form.data('acm-sku-id');
        var $pricesWrapper = $form.parent().find('.sku-pricing-section');
        var acmSkuSettings = settings.acm_sku || {};
        var variantTree = acmSkuSettings[skuId].variant_tree || {};
        var variantPrices = acmSkuSettings[skuId].variant_prices || {};

        // Determines the price to display based on the configured attributes.
        var setDisplayPrices = function() {
          // Build array of selected values and the attribute it maps to.
          var selections = $select.map(function(delta, select) {
            var $el = $(select);
            var value = $el.val();
            if (!value) {
              return;
            }

            return {
              name: $el.data('product-attribute'),
              value: value,
            }
          }).filter(function(delta, value) {
            if (value) {
              return value;
            }
          });

          // If we don't have as many values as select boxes, don't change the
          // price yet.
          if (selections.length !== $select.length) {
            return;
          }

          var skusByVariant = {};
          // Build the "skus by selected variant" object.
          for (var i = 0; i < selections.length; i++) {
            var attribute = selections[i].name;
            var option = selections[i].value;
            skusByVariant[attribute] = variantTree[attribute][option];
          }

          var selectedSku = null;
          // Iterate through each available variant sku and check if it's
          // contained in all of the groups of skus for each selected variant.
          for (var sku in variantPrices) {
            if (variantPrices.hasOwnProperty(sku)) {
              var matches = true;
              for (var variant in skusByVariant) {
                if (skusByVariant.hasOwnProperty(variant)) {
                  var skus = skusByVariant[variant];
                  // If not contained in group of skus, this sku is not the one
                  // we're looking for.
                  if (skus.indexOf(sku) === -1) {
                    matches = false;
                  }
                }
              }

              // If matches is TRUE, this is the sku we're looking for. If it's
              // FALSE it's because the sku wasn't in all of the variant groups.
              if (matches) {
                selectedSku = sku;
                break;
              }
            }
          }

          var price = variantPrices[selectedSku];
          $pricesWrapper.html(price);
        };

        // Determines which attributes in each group are available based on the
        // selected options.
        var determineApplicableOptions = function(el, applicableSkus) {
          $select.each(function() {
            var $this = $(this);

            if (el === this) {
              return;
            }

            $this.find('option').removeAttr('disabled');

            var attribute = $this.data('product-attribute');
            var options = variantTree[attribute] || {};

            for (var option in options) {
              if (options.hasOwnProperty(option)) {
                var optionSkus = options[option];
                var optionAvailable = false;

                applicableSkus.forEach(function(sku) {
                  if (~optionSkus.indexOf(sku)) {
                    optionAvailable = true;
                  }
                });

                if (!optionAvailable) {
                  $this.find('option[value="' + option + '"]').attr('disabled', 'disabled');
                }
              }
            }
          });
        };

        // Updates attribute groups based on an elements value.
        var updateAttributeGroups = function(el) {
          var $this = $(el);
          var attribute = $this.data('product-attribute');
          if (!attribute) {
            return;
          }

          var value = $this.val();
          var options = variantTree[attribute] || {};
          var applicableSkus = options[value] || [];
          determineApplicableOptions(el, applicableSkus);
          setDisplayPrices();
        };

        // Set initial display price based on default options.
        $select.each(function() {
          updateAttributeGroups(this);
        });

        // Update attributes when a value is changed.
        $select.on('change', function() {
          updateAttributeGroups(this);
        });
      });
    }
  };

}(jQuery, Drupal));
