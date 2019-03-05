/**
 * @file
 * Renders a AddressesPageView.
 */

(function($, _, Backbone, Drupal) {
  'use strict';

  Drupal.acm_customer = Drupal.acm_customer || {};
  Drupal.acm_customer.Views = Drupal.acm_customer.Views || {};

  Drupal.acm_customer.Views.AddressesPageView = Drupal.acm_customer.Views.BaseCustomerPageView.extend(/** @lends Drupal.acm_customer.Views.AddressesPageView# */{

    /**
     * @type {jQuery}
     */
    el: $('.customer-addresses-form-wrapper'),

    /**
     * @type {object}
     */
    additionalEvents: {
      'click .customer-address a': 'redirectToPage',
    }

  });

}(jQuery, _, Backbone, Drupal));
