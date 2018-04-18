/**
 * @file
 * Renders a BasePanesView.
 */

(function($, _, Backbone, Drupal) {
  'use strict';

  Drupal.acm_checkout = Drupal.acm_checkout || {};
  Drupal.acm_checkout.Views = Drupal.acm_checkout.Views || {};

  Drupal.acm_checkout.Views.BasePanesView = Backbone.View.extend(/** @lends Drupal.acm_checkout.Views.BasePanesView# */{
    /**
     * @type {jQuery}
     */
    el: $('#acm_checkout_wrapper'),

    /**
     * @type {object}
     */
    events: {
      'click .form-actions a': 'redirectToPage'
    },

    /**
     * @type {object}
     */
    ajaxOptions: {
      submit: {
        nocssjs: true,
        reset: false,
      }
    },

    /**
     * @constructs
     *
     * @augments Backbone.View
     *
     * @param {string} url
     *   The url to load the form from.
     */
    initialize: function(url) {
      this.ajaxOptions.url = url;

      // Create a Drupal.ajax instance to load the form.
      var formLoaderAjax = Drupal.ajax(this.ajaxOptions);

      // Implement a scoped acm_checkoutPaneForm AJAX command that calls the
      // View formLoad method.
      var self = this;

      // Checkout step responsed, render it.
      formLoaderAjax.commands.acm_checkoutPaneForm = function(ajax, response, status) {
        self.render(response.data, ajax);
        Drupal.ajax.instances[this.instanceIndex] = null;
      };

      // Checkout step redirected, so go to that step instead.
      formLoaderAjax.commands.acm_checkoutPaneFormRedirect = function(ajax, response, status) {
        self.paneFormRedirect(ajax, response, status);
      };

      // This will ensure our scoped AJAX command gets called.
      formLoaderAjax.execute();
    },

    /**
     * Renders the view.
     *
     * @param {string} form
     *   The form html.
     * @param {Drupal.Ajax} ajax
     *   A {@link Drupal.Ajax} instance.
     */
    render: function(form, ajax) {
      var self = this;

      // Add the intiial form element variables. These can be overridden in the
      // renderForm method if something extends this view.
      this.$form = $(form);
      this.$submit = $('.js-form-submit', this.$form);

      // Add the form to the page.
      this.renderForm();

      // AJAXify the form submit button.
      this.formSaveAjax = this.ajaxifySaving(this.ajaxOptions, this.$submit);

      // AJAX command called when form is saved.
      this.formSaveAjax.commands.acm_checkoutPaneFormSaved = function(ajax, response, status) {
        self.paneFormSaved(ajax, response, status);
      };

      // AJAX command called when form fails validation and sends the status
      // messages.
      this.formSaveAjax.commands.acm_checkoutPaneFormValidationErrors = function(ajax, response, status) {
        self.paneFormValidationErrors(ajax, response, status);
      };

      // AJAX command called when form fails validation and sends the array of
      // fields that failed validation.
      this.formSaveAjax.commands.acm_checkoutPaneFormValidationErrorsFields = function(ajax, response, status) {
        self.paneFormValidationErrorsFields(ajax, response, status);
      };

      // AJAX command called when the ACMCheckoutFlow plugin issues a redirect.
      this.formSaveAjax.commands.acm_checkoutPaneFormRedirect = function(ajax, response, status) {
        self.paneFormRedirect(ajax, response, status);
      };
    },

    /**
     * Renders the pane form.
     */
    renderForm: function() {
      // Add the form to the page and set the jQuery object to a variable
      // to be used in ajax callbacks.
      this.$content = this.$el.html(this.$form);

      // Reattach behaviors and scroll to the top of the form.
      Drupal.attachBehaviors(this.el);
      this.scrollTo(this.$content);
    },

    /**
     * AJAX command called after the form is saved.
     *
     * @param {Drupal.Ajax} ajax
     *   {@link Drupal.Ajax} object created by {@link Drupal.ajax}.
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.data
     *   The data to use with the jQuery method.
     * @param {string} [response.method]
     *   The jQuery DOM manipulation method to be used.
     * @param {string} [response.selector]
     *   A optional jQuery selector string.
     * @param {object} [response.settings]
     *   An optional array of settings that will be used.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    paneFormSaved: function(ajax, response, status) {
      // Change to the next form page.
      Drupal.acm_checkout.Router.navigate(response.data, true);
    },

    /**
     * AJAX command called after the form fails validation.
     *
     * @param {Drupal.Ajax} ajax
     *   {@link Drupal.Ajax} object created by {@link Drupal.ajax}.
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.data
     *   The data to use with the jQuery method.
     * @param {string} [response.method]
     *   The jQuery DOM manipulation method to be used.
     * @param {string} [response.selector]
     *   A optional jQuery selector string.
     * @param {object} [response.settings]
     *   An optional array of settings that will be used.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    paneFormValidationErrors: function(ajax, response, status) {
      // Empty previous messages.
      $('.validation', this.$content).remove();
      // Add messages above the form.
      this.$content
        .prepend($('<div class="validation" />').html(response.data));
      // Scroll to the added messages.
      this.scrollTo(this.$content);
    },

    /**
     * AJAX command called after the form fails validation and passes the
     * fields array.
     *
     * @param {Drupal.Ajax} ajax
     *   {@link Drupal.Ajax} object created by {@link Drupal.ajax}.
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.data
     *   The data to use with the jQuery method.
     * @param {string} [response.method]
     *   The jQuery DOM manipulation method to be used.
     * @param {string} [response.selector]
     *   A optional jQuery selector string.
     * @param {object} [response.settings]
     *   An optional array of settings that will be used.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    paneFormValidationErrorsFields: function(ajax, response, status) {
      var fields = response.data;
      fields.forEach(function(field) {
        var fieldName = '';
        var parts = field.split('][');
        parts.forEach(function(part, i) {
          if (i === 0) {
            fieldName = part;
          }
          else {
            fieldName += '[' + part + ']';
          }
        });

        $('[name="' + fieldName + '"]').addClass('required error');
      });
    },

    /**
     * AJAX command called after the ACMCheckoutFlow plugin issues a redirect.
     *
     * @param {Drupal.Ajax} ajax
     *   {@link Drupal.Ajax} object created by {@link Drupal.ajax}.
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.data
     *   The data to use with the jQuery method.
     * @param {string} [response.method]
     *   The jQuery DOM manipulation method to be used.
     * @param {string} [response.selector]
     *   A optional jQuery selector string.
     * @param {object} [response.settings]
     *   An optional array of settings that will be used.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    paneFormRedirect: function(ajax, response, status) {
      Drupal.acm_checkout.Router.navigate(response.data, true);
    },

    /**
     * AJAXifies a submit element.
     *
     * @param {object} options
     *   The ajax options.
     * @param {jQuery} $submit
     *   The submit element.
     */
    ajaxifySaving: function(options, $submit) {
      // Re-wire the form to handle submit.
      var settings = {
        url: $submit.closest('form').attr('action'),
        setClick: true,
        event: 'click.acm_checkout',
        progress: false,
        submit: {
          nocssjs: options.nocssjs
        },
        base: $submit.attr('id'),
        element: $submit[0]
      };

      return Drupal.ajax(settings);
    },

    /**
     * Scrolls to an element.
     *
     * @param {jQuery} $el
     *   The element to scroll to.
     */
    scrollTo: function($el) {
      $('html, body').animate({
        scrollTop: $el.offset().top - 100
      }, 300, 'linear');
    },

    /**
     * Event listener to redirect to a route.
     *
     * @param {object} e
     *   The event object.
     */
    redirectToPage: function(e) {
      e.preventDefault();
      var href = $(e.currentTarget).attr('href');
      Drupal.acm_checkout.Router.navigate(href, true);
      return false;
    }

  });

}(jQuery, _, Backbone, Drupal));
