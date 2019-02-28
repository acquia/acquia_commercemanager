/**
 * @file
 * Renders a ProfilePageView.
 */

(function($, _, Backbone, Drupal) {
  'use strict';

  Drupal.acm_customer = Drupal.acm_customer || {};
  Drupal.acm_customer.Views = Drupal.acm_customer.Views || {};

  Drupal.acm_customer.Views.ProfilePageView = Drupal.acm_customer.Views.BaseCustomerPageView.extend(/** @lends Drupal.acm_customer.Views.ProfilePageView# */{

    /**
     * @type {jQuery}
     */
    el: $('.customer-profile-form-wrapper'),

  });

}(jQuery, _, Backbone, Drupal));
