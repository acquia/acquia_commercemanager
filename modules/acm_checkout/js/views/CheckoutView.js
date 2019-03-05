/**
 * @file
 * Renders a checkout page.
 */

(function($, _, Backbone, Drupal) {
  'use strict';

  /**
   * @namespace
   */
  Drupal.acm_checkout = Drupal.acm_checkout || {};

  /**
   * @namespace
   */
  Drupal.acm_checkout.Views = Drupal.acm_checkout.Views || {};

  Drupal.acm_checkout.Views.CheckoutView = Backbone.View.extend(/** @lends Drupal.acm_checkout.Views.CheckoutView# */{
    /**
     * @constructs
     *
     * @augments Backbone.View
     */
    initialize: function() {
      this.initializeRouter();
    },

    /**
     * Initializes the router.
     */
    initializeRouter: function() {
      this.router = new Drupal.acm_checkout.CheckoutRouter();
      Backbone.history.start({
        pushState: true, root: '/'
      });
    }

  });

  // Load the view in document.ready to give a chance to extend/override the
  // BasePanesView in other modules/themes.
  $(document).ready(function() {
    var view = new Drupal.acm_checkout.Views.CheckoutView();
    Drupal.acm_checkout.Router = view.router;
  });

}(jQuery, _, Backbone, Drupal));
