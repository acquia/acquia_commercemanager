/**
 * @file
 * Builds the Checkout Router.
 */

(function($, _, Backbone, Drupal, drupalSettings) {
  'use strict';

  var routes = {};
  var settings = drupalSettings.acm_checkout || {};
  var cartPath = settings.cartPath;

  if (cartPath.charAt(0) === '/') {
    cartPath = cartPath.slice(1);
  }

  cartPath += '(/:step)(/)';
  routes[cartPath] = 'loadForm';

  /**
   * @namespace
   */
  Drupal.acm_checkout = Drupal.acm_checkout || {};

  Drupal.acm_checkout.CheckoutRouter = Backbone.Router.extend(/** @lends Drupal.acm_checkout.Router# */{
    /**
     * @type {object}
     */
    routes: routes,

    /**
     * Renders a view depending on the route.
     *
     * @param {string} step
     *   The current checkout step.
     */
    loadForm: function(step){
      if (!step) {
        step = 'billing';
      }

      var viewName = step.charAt(0).toUpperCase() + step.slice(1) + 'PanesView';

      // Fallback to base panes view if no custom one is set.
      if (!Drupal.acm_checkout.Views[viewName]) {
        viewName = 'BasePanesView';
      }

      new Drupal.acm_checkout.Views[viewName](settings.ajaxCartPath + '/' + step);
    }
  });

}(jQuery, _, Backbone, Drupal, drupalSettings));
