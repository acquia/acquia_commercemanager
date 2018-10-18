/**
 * @file
 * Builds the Customer Router.
 */

(function($, _, Backbone, Drupal, drupalSettings) {
  'use strict';

  var routes = {};
  var settings = drupalSettings.acm_customer || {};
  var customerPagesPath = settings.customerPagesPath;

  if (customerPagesPath.charAt(0) === '/') {
    customerPagesPath = customerPagesPath.slice(1);
  }

  customerPagesPath += '(/:page)(/:action)(/:id)(/)';
  routes[customerPagesPath] = 'loadForm';

  /**
   * @namespace
   */
  Drupal.acm_customer = Drupal.acm_customer || {};

  Drupal.acm_customer.CustomerRouter = Backbone.Router.extend(/** @lends Drupal.acm_customer.Router# */{

    /**
     * @type {object}
     */
    routes: routes,

    /**
     * Renders a view depending on the route.
     *
     * @param {string} page
     *   The current customer page.
     * @param {string} action
     *   The current customer page action.
     * @param {string} id
     *   The current customer page id.
     */
    loadForm: function(page, action, id){
      var ajaxPath = settings.ajaxCustomerPagesPath;

      if (!page) {
        page = 'profile';
      }

      ajaxPath += '/' + page;

      if (action) {
        ajaxPath += '/' + action;
      }

      if (id) {
        ajaxPath += '/' + id;
      }

      var viewName = page.charAt(0).toUpperCase() + page.slice(1) + 'PageView';

      if (Drupal.acm_customer.Views[viewName]) {
        new Drupal.acm_customer.Views[viewName](ajaxPath);
      }
    }
  });

}(jQuery, _, Backbone, Drupal, drupalSettings));
