/**
 * @file
 * Provides the marketing link feature for the promotion module.
 *
 * Checks for promo codes provided as a url parameter as well as redirects the
 * browser if attempting to load a promotion node page.
 */

(function ($, Drupal) {
  Drupal.behaviors.acm_promotion_marketing_link = {
    /**
     * Checks for query parameters and redirects.
     */
    attach: function (context, settings) {
      $('body', context).once('acm_promotion_marketing_link').each(function (){
        Drupal.behaviors.acm_promotion_marketing_link.init(settings);
      });
    },

    /**
     * Checks for query parameters and redirects.
     *
     * Checks for a promotion in the url parameter and will save it to
     * sessionStorage. Provides redirect if attempting to load a promotion node
     * page.
     *
     * @param {object} settings
     *   This is the drupalSettings object.
     */
    init: function (settings) {
      // Add promos found in query string.
      var coupon = Drupal.behaviors.acm_promotion_marketing_link.getQueryParam('promo');
      if (coupon !== "") {
        Drupal.behaviors.acm_promotion.setCoupon(coupon);
      }

      // No need to continue if there is no data to work with.
      if (typeof settings.acm_promotion.marketing_link === 'undefined') {
        return;
      }

      var url = settings.acm_promotion.marketing_link.callback;
      var promo = settings.acm_promotion.marketing_link.promo;

      Drupal.behaviors.acm_promotion.addPromotionData([promo]);

      window.location.href = url;
    },

    /**
     * Gets url parameters by name.
     *
     * @param {string} name
     *   Name of the query parameter to be retrieved.
     *
     * @return
     *   String value of the named parameter.
     */
    getQueryParam: function (name) {
      var url = window.location.href;
      name = name.replace(/[\[\]]/g, "\\$&");
      var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)");
      var results = regex.exec(url);

      if (!results) {
        return "";
      }

      if (!results[2]) {
        return "";
      }

      return decodeURIComponent(results[2].replace(/\+/g, " "));
    }
  };
})(jQuery, Drupal);
