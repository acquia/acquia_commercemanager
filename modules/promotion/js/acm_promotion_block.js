/**
 * @file
 * Handles display logic for the promotion block.
 *
 * Adds all active promotions that are also found in the session into the
 * promotion block.
 */

(function ($, Drupal) {
  Drupal.behaviors.acm_promotion_block = {
    /**
     * Adds all active promotions that are also found in the session into the
     * promotion block.
     */
    attach: function (context, settings) {
      $('body', context).once('acm_promotion_block').each(function (){
        Drupal.behaviors.acm_promotion_block.init(context, settings);
      });
    },

    /**
     * Adds all active promotions that are also found in the session into the
     * promotion block.
     *
     * @param {object} context
     *   The context of the attachment.
     *
     * @param {object} settings
     *   This is the drupalSettings object.
     */
    init: function (context, settings) {
      var container = $('.acm-promotion-block');

      if (container.length < 1) {
        return;
      }

      if (typeof settings.acm_promotion === 'undefined') {
        return;
      }

      var selectors = Object.keys(settings.acm_promotion);
      var active_promos = settings.acm_promotion;

      $.each(selectors, function (index, selector) {
        $.each(promos, function (index, promo) {
          if (typeof active_promos[selector] !== 'undefined') {
            var block = $(selector, context);
            var html = active_promos[selector][promo];
            block.append(html);
          }
        });
      });
    }
  };
})(jQuery, Drupal);
