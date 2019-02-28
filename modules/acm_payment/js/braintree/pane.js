/**
 * @file
 * Attaches behaviors for the Braintree pane.
 */

(function($, Drupal, braintree) {
  'use strict';

  Drupal.behaviors.braintree = {
    attach: function(context, settings) {
      var $form = $('#multistep-default');
      var $pane = $('#braintree_pane');
      var $nonceField = $('input[name="payment_methods[payment_details][payload_nonce]"]');
      var $cardImage = $('.cardinfo__card-image');
      var submit = $('input[type="submit"]', $form).get(0);
      var braintreeSettings = settings.braintree || {};
      var authorizationToken = braintreeSettings.authorizationToken;

      if (!$pane.length || typeof authorizationToken === 'undefined') {
        return;
      }

      braintree.client.create({
        authorization: authorizationToken,
      }, function(err, clientInstance) {
        if (err) {
          return;
        }

        // Create input fields and add text styles.
        braintree.hostedFields.create({
          client: clientInstance,
          styles: {
            'input': {
              'color': '#282c37',
              'font-size': '16px',
              'transition': 'color 0.1s',
              'line-height': '3'
            },
            // Style the text of an invalid input.
            'input.invalid': {
              'color': '#E53A40'
            },
            // Placeholder styles need to be individually adjusted.
            '::-webkit-input-placeholder': {
              'color': 'rgba(0,0,0,0.6)'
            },
            ':-moz-placeholder': {
              'color': 'rgba(0,0,0,0.6)'
            },
            '::-moz-placeholder': {
              'color': 'rgba(0,0,0,0.6)'
            },
            ':-ms-input-placeholder': {
              'color': 'rgba(0,0,0,0.6)'
            }
          },
          fields: {
            number: {
              selector: '#cc-card-number',
              placeholder: '1111 1111 1111 1111'
            },
            cvv: {
              selector: '#cc-cvv',
              placeholder: '123'
            },
            expirationDate: {
              selector: '#cc-expiration-date',
              placeholder: '10 / 2019'
            }
          }
        }, function(err, hostedFieldsInstance) {
          if (err) {
            return;
          }

          hostedFieldsInstance.on('empty', function(event) {
            $cardImage.removeClass();
            $pane.removeClass();
          });

          hostedFieldsInstance.on('cardTypeChange', function(event) {
            // Change card bg depending on card type.
            if (!event.cards.length) {
              hostedFieldsInstance.setPlaceholder('cvv', '123');
            }
            else {
              $pane.removeClass().addClass(event.cards[0].type);
              $cardImage.removeClass().addClass(event.cards[0].type);

              // Change the CVV length for AmericanExpress cards.
              if (event.cards[0].code.size === 4) {
                hostedFieldsInstance.setPlaceholder('cvv', '1234');
              }
            }
          });

          submit.addEventListener('click', function(event) {
            event.preventDefault();

            hostedFieldsInstance.tokenize(function(err, payload) {
              if (err) {
                // @TODO: Add front-end error messages if this fails.
                return;
              }

              $nonceField.val(payload.nonce);
              $form.submit();
            });
          }, false);
        });
      });
    }
  };

})(jQuery, Drupal, braintree);
