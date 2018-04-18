/**
 * @file
 * Handles form updates for the promotion module.
 *
 * Checks for hidden promotion element and adds promotion data as the value of
 * that element to be sent to the backend for processing.
 */

(function ($, Drupal) {
  Drupal.behaviors.acm_promotion_form = {
    /**
     * Adds promotion data to promotion form element.
     */
    attach: function (context, settings) {
      $('body', context).once('acm_promotion_form').each(function (){
        Drupal.behaviors.acm_promotion_form.init(context);
      });
    },

    /**
     * Adds promotion data to promotion form element.
     *
     * Loads promotion data from sessionStorage and places data in a hidden
     * element to send session data to Drupal backend for processing.
     *
     * @param {object} context
     *   The context of the attachment.
     */
    init: function (context) {
      var coupon = Drupal.behaviors.acm_promotion.getCoupon();
      if (coupon === "") {
        return;
      }

      // No need to continue if the form element does not exist.
      var input = $('input#acm-promotion-coupon', context);
      if (input.length < 1) {
        return;
      }

      input.val(coupon);
    }
  };
})(jQuery, Drupal);
