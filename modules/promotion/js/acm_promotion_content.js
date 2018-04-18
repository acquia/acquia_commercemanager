/**
 * @file
 * Handles content display for the promotion module.
 *
 * Looks for promotion containers and embeds promotion content within them.
 */

(function ($, Drupal) {
  Drupal.behaviors.acm_promotion_content = {
    /**
     * Loads promotion containers and adds content to them.
     */
    attach: function (context, settings) {
      $('body', context).once('acm_promotion_content').each(function (){
        Drupal.behaviors.acm_promotion_content.init(context, settings);
      });
    },

    /**
     * Loads promotion containers and adds content to them.
     *
     * Loads promotion containers and embeds related promotion content within
     * them.
     *
     * @param {object} context
     *   The context of the attachment.
     *
     * @param {object} settings
     *   This is the drupalSettings object.
     */
    init: function (context, settings) {
      // No need to continue if the there are no promotion containers.
      var containers = $('.acm-promo-container', context);
      if (containers.length < 1) {
        return;
      }

      containers.each(function (index, element) {
        var container = $(element);
        Drupal.behaviors.acm_promotion_content.handlePromotion(container, settings);
      });
    },

    /**
     * Handles display of the promotion content.
     *
     * Loads the 'mode' data object and calls the associated promotion handler.
     *
     * @param {object} context
     *   The context of the attachment.
     *
     * @param {object} settings
     *   This is the drupalSettings object.
     */
    handlePromotion: function (container, settings) {
      var validModes = Object.keys(Drupal.behaviors.acm_promotion_content.promotionHandlers);

      var mode = container.data('mode');
      if (validModes.indexOf(mode) < 0) {
        throw 'Invalid mode [' + mode + '].';
      }

      // Drupal.behaviors.acm_promotion_content.promotionHandlers is an object
      // that contains  multiple handler methods that are keyed by the 'mode'
      // of the block. The line below selects the correct mode to render the
      // content.
      Drupal.behaviors.acm_promotion_content.promotionHandlers[mode](container, settings);
    },

    /**
     * This is an object that contains multiple types of promotion handlers.
     *
     * Promotion handlers are added by key. The key in this case is the 'mode'
     * attribute on the promotion container.
     */
    promotionHandlers: {
      /**
       * Handles 'sku' display mode for promotions.
       *
       * This method looks up promotions based on the sku found in the 'arg'
       * data attribute. If the loaded promotion is found in session, it will
       * place the rendered promotion in the container. It checks the 'display'
       * data attribute to find which node display mode should be used.
       *
       * @param {object} context
       *   The context of the attachment.
       *
       * @param {object} settings
       *   This is the drupalSettings object.
       */
      sku: function (container, settings) {
        var sku = container.data('arg');

        // No need to continue if there is no promotion for the sku.
        if (typeof settings.acm_promotion.sku_to_promo_map[sku] === 'undefined') {
          return;
        }

        var display = container.data('display');

        var container_promos = settings.acm_promotion.sku_to_promo_map[sku];
        var promos = Drupal.behaviors.acm_promotion.loadPromotionData();

        for (var i = 0; i < container_promos.length; i++) {
          var promo = container_promos[i];

          if (promos.indexOf(promo) >= 0) {
            if (typeof settings.acm_promotion.promo_display_map[promo] === 'undefined') {
              continue;
            }

            if (typeof settings.acm_promotion.promo_display_map[promo][display] === 'undefined') {
              continue;
            }

            container.append(settings.acm_promotion.promo_display_map[promo][display]);
          }
        }
      },

      /**
       * Handles 'always_on' display mode for promotions.
       *
       * This method checks the 'display' data attribute to find which node
       * display mode should be used. Then it places all promotions maked as
       * 'always on' in to the container using that display mode.
       *
       * @param {object} context
       *   The context of the attachment.
       *
       * @param {object} settings
       *   This is the drupalSettings object.
       */
      always_on: function (container, settings) {
        // No need to continue if there is no promotion for the sku.
        if (typeof settings.acm_promotion.always_on === 'undefined') {
          return;
        }

        var display = container.data('display');
        var always_on_promos = settings.acm_promotion.always_on;
        var promos = Drupal.behaviors.acm_promotion.loadPromotionData();

        for (var i = 0; i < always_on_promos.length; i++) {
          var promo = always_on_promos[i];

          if (promos.indexOf(promo) >= 0) {
            if (typeof settings.acm_promotion.promo_display_map[promo] === 'undefined') {
              continue;
            }

            if (typeof settings.acm_promotion.promo_display_map[promo][display] === 'undefined') {
              continue;
            }

            container.append(settings.acm_promotion.promo_display_map[promo][display]);
          }
        }
      },

      /**
       * Handles 'promotion' display mode for promotions.
       *
       * This method checks the 'display' data attribute to find which node
       * display mode should be used. Then it checks the 'arg' data attribute
       * to grab the promotion code. The promotion node is then rendered in the
       * container using the configured display mode.
       *
       * @param {object} context
       *   The context of the attachment.
       *
       * @param {object} settings
       *   This is the drupalSettings object.
       */
      promotion: function (container, settings) {
        var promo = container.data('arg');

        // No need to continue if there is no promotion for the sku.
        if (typeof settings.acm_promotion.promo_display_map[promo] === 'undefined') {
          return;
        }

        var display = container.data('display');

        if (typeof settings.acm_promotion.promo_display_map[promo][display] === 'undefined') {
          return;
        }

        container.append(settings.acm_promotion.promo_display_map[promo][display]);
      }
    }
  };
})(jQuery, Drupal);
