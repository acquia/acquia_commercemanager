/**
 * @file
 * Provides features for the promotion module.
 *
 * Pulls 'always on' promotions and places them sessionStorage while also
 * providing promotion related utility methods for other modules.
 */

(function ($, Drupal) {
  Drupal.behaviors.acm_promotion = {
    /**
     * Adds any 'always on' promotions to the session.
     */
    attach: function (context, settings) {
      $('body', context).once('acm_promotion').each(function () {
        Drupal.behaviors.acm_promotion.init(settings);
      });
    },


    /**
     * Adds any 'always on' promotions to the session.
     *
     * @param {object} settings
     *   This is the drupalSettings object.
     */
    init: function (settings) {
      // No need to continue if promotions are not present.
      if (typeof settings.acm_promotion.always_on === 'undefined') {
        return;
      }

      var always_on_promos = settings.acm_promotion.always_on;
      Drupal.behaviors.acm_promotion.addPromotionData(always_on_promos);
    },

    /**
     * Loads the promotion data from sessionStorage.
     *
     * @return
     *   Array of promotion codes found in sessionStorage.
     */
    loadPromotionData: function () {
      var sessionStorage = window.sessionStorage;
      var promos = sessionStorage.getItem('acm_promotion');

      if (promos === null) {
        promos = '[]';
      }

      return JSON.parse(promos);
    },

    /**
     * Saves promotion data to sessionStorage.
     *
     * @param {array} data
     *   An array of promotions to save into sessionStorage.
     */
    savePromotionData: function (data) {
      var sessionStorage = window.sessionStorage;
      sessionStorage.setItem('acm_promotion', JSON.stringify(data));
    },

    /**
     * Adds promotion data into sessionStorage.
     *
     * Adds promotion data to sessionStorage if the promotion data does not
     * already exist in sessionStorage.
     *
     * @param {array} data
     *   An array of promotions to save into sessionStorage.
     */
    addPromotionData: function(promos) {
      var session_promos = Drupal.behaviors.acm_promotion.loadPromotionData();
      var dirty = false;

      for (var i = 0; i < promos.length; i++) {
        var promo = promos[i];

        if (session_promos.indexOf(promo) < 0) {
          dirty = true;
          session_promos.push(promo);
        }
      }

      if (dirty) {
        Drupal.behaviors.acm_promotion.savePromotionData(session_promos);
      }
    },

    /**
     * Loads the coupon from sessionStorage.
     *
     * @return
     *   String value of the coupon.
     */
    getCoupon: function () {
      var sessionStorage = window.sessionStorage;
      var coupon = sessionStorage.getItem('acm_promotion_coupon');

      if (coupon === null) {
        coupon = '';
      }

      return coupon;
    },

    /**
     * Saves the coupon to sessionStorage.
     *
     * @param {string} coupon
     *   String value of the coupon.
     */
    setCoupon: function (coupon) {
      var sessionStorage = window.sessionStorage;
      sessionStorage.setItem('acm_promotion_coupon', coupon);
    }
  };
})(jQuery, Drupal);
