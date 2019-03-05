/**
 * @file
 * Renders a customer page.
 */

(function($, _, Backbone, Drupal) {
  'use strict';

  /**
   * @namespace
   */
  Drupal.acm_customer = Drupal.acm_customer || {};

  /**
   * @namespace
   */
  Drupal.acm_customer.Views = Drupal.acm_customer.Views || {};

  Drupal.acm_customer.Views.CustomerView = Backbone.View.extend(/** @lends Drupal.acm_customer.Views.CustomerView# */{

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
      this.router = new Drupal.acm_customer.CustomerRouter();
      Backbone.history.start({
        pushState: true, root: '/'
      });
    }

  });

  // Load the view in document.ready to give a chance to extend/override the
  // BasePanesView in other modules/themes.
  $(document).ready(function() {
    var view = new Drupal.acm_customer.Views.CustomerView();
    Drupal.acm_customer.Router = view.router;
  });

}(jQuery, _, Backbone, Drupal));
