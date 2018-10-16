/**
 * @file
 * Adds promotions found in drupalSettings.
 *
 * Checks for promotions that were forcefully added (ie from a cart response)
 * and adds them to the session.
 */

(function ($, Drupal) {
  Drupal.behaviors.acm_promotion_add_promotions = {
    /**
     * Checks for forcefully added promotions.
     */
    attach: function (context, settings) {
      $('body', context).once('acm_promotion_add_promotions').each(function (){
        Drupal.behaviors.acm_promotion_add_promotions.init(settings);
      });
    },

    /**
     * Checks for forcefully added promotions.
     *
     * Checks for a promotion in the drupalSettings object and will save it
     * to sessionStorage.
     *
     * @param {object} settings
     *   This is the drupalSettings object.
     */
    init: function (settings) {
      // No need to continue if no promotions.
      if (typeof settings.acm_promotion.add_promos === 'undefined') {
        return;
      }

      var promos = settings.acm_promotion.add_promos;
      Drupal.behaviors.acm_promotion.addPromotionData(promos);
    }
  };
})(jQuery, Drupal);
