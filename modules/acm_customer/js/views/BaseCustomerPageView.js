/**
 * @file
 * Renders a BaseCustomerPageView.
 */

(function($, _, Backbone, Drupal) {
  'use strict';

  Drupal.acm_customer = Drupal.acm_customer || {};
  Drupal.acm_customer.Views = Drupal.acm_customer.Views || {};

  Drupal.acm_customer.Views.BaseCustomerPageView = Backbone.View.extend(/** @lends Drupal.acm_customer.Views.BaseCustomerPageView# */{

    /**
     * @type {jQuery}
     */
    $content: false,

    /**
     * @type {object}
     */
    originalEvents: {
      'click .form-item__buttons a': 'redirectToPage',
      'click .form-actions a': 'redirectToPage'
    },

    /**
     * @type {object}
     */
    additionalEvents: {
    },

    //Override this event hash in
    //a child view
    events : function() {
      return _.extend({}, this.originalEvents, this.additionalEvents);
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

      var self = this;
      // Create a Drupal.ajax instance to load the form.
      var formLoaderAjax = Drupal.ajax(this.ajaxOptions);

      // Customer form responsed, render it.
      formLoaderAjax.commands.acmCustomerForm = function(ajax, response, status) {
        self.render(response.data, ajax);
        Drupal.ajax.instances[this.instanceIndex] = null;
      };

      // Customer form redirected, so go to that form instead.
      formLoaderAjax.commands.acmCustomerFormRedirect = function(ajax, response, status) {
        self.customerFormRedirect(ajax, response, status);
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
      // Add the intiial form element variables. These can be overridden in the
      // renderForm method if something extends this view.
      this.$form = $(form);

      // Add the form to the page.
      this.renderForm();

      var $ajaxSubmit = $('.js-form-submit', this.$form);

      // If no submit buttons, don't continue.
      if (!$ajaxSubmit.length) {
        return;
      }

      var self = this;
      // AJAXify the form submit button.
      this.formSaveAjax = this.ajaxifySaving(this.ajaxOptions, $ajaxSubmit);

      // AJAX command called when form is saved.
      this.formSaveAjax.commands.acmCustomerFormSaved = function(ajax, response, status) {
        self.customerFormSaved(ajax, response, status);
      };

      // AJAX command called when form fails validation and sends the status
      // messages.
      this.formSaveAjax.commands.acmCustomerFormValidationErrors = function(ajax, response, status) {
        self.customerFormValidationErrors(ajax, response, status);
      };

      // AJAX command called when form fails validation and sends the array of
      // fields that failed validation.
      this.formSaveAjax.commands.acmCustomerFormValidationErrorsFields = function(ajax, response, status) {
        self.customerFormValidationErrorsFields(ajax, response, status);
      };

      // AJAX command that can be used to display messages form the server
      // before the page has been reloaded.
      this.formSaveAjax.commands.acmCustomerFormMessage = function(ajax, response, status) {
        self.messages = response;
      };

      Drupal.acm_customer.Router.on('route', function(route, params) {
        // This triggers before the route has completely changed, so we add a
        // small delay to the function call so we can ensure that we have
        // navigate to the next route in the app.
        setTimeout(function() {
          self.customerFormMessage({}, self.messages, 200);
          self.messages = null;
        }, 100);
      });

      // AJAX command called when the ACMCheckoutFlow plugin issues a redirect.
      this.formSaveAjax.commands.acmCustomerFormRedirect = function(ajax, response, status) {
        self.customerFormRedirect(ajax, response, status);
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
    customerFormSaved: function(ajax, response, status) {
      // Change to the next form page.
      Drupal.acm_customer.Router.navigate(response.data, true);
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
    customerFormValidationErrors: function(ajax, response, status) {
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
    customerFormValidationErrorsFields: function(ajax, response, status) {
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
    customerFormRedirect: function(ajax, response, status) {
      Drupal.acm_customer.Router.navigate(response.data, true);
    },

    /**
     * AJAX command called to present messages on the current page.
     *
     * @param {Drupal.Ajax} ajax
     *   {@link Drupal.Ajax} object create by {@link Drupal.ajax}.
     * @param {object} response
     *   The response from the Ajax request.
     * @param status
     */
    customerFormMessage: function(ajax, response, status) {
      $('.messages', this.$content).remove();
      window.x = this.$content;
      window.y = response.data;
      this.$content.prepend($('<div class="messages" />').html(response.data));
      this.scrollTo(this.$content);
    },

    /**
     * AJAXifies a submit element.
     *
     * @param {object} options
     *   The ajax options.
     * @param {jQuery} $el
     *   The element being AJAXified.
     */
    ajaxifySaving: function(options, $el) {
      var url = $el.closest('form').attr('action');

      if ($el.attr('href')) {
        url = $el.attr('href');

        // Make sure we're using the ajax path.
        if (url.indexOf('ajax/account') === -1) {
          url = url.replace('account', 'ajax/account');
        }
      }

      var settings = {
        url: url,
        setClick: true,
        event: 'click.acm_customer',
        progress: true,
        submit: options.submit,
        base: $el.attr('id'),
        element: $el[0]
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
      var $el = $(e.currentTarget);

      // Don't handle links that bypass js.
      if ($el.hasClass('bypass-js')) {
        return;
      }

      e.preventDefault();
      var href = $el.attr('href');
      Drupal.acm_customer.Router.navigate(href, true);
      return false;
    }

  });

}(jQuery, _, Backbone, Drupal));
