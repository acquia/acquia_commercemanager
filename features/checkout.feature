@javascript @checkout @english @eng_checkout @mmcpa-1930 @manual
Feature: Test Checkout feature
  Background:
    Given I am on a configurable product
    And I wait for the page to load
    When I select a size for the product
    And I wait for AJAX to finish
    When I press "Add to basket"
    And I wait for AJAX to finish
    And I go to "/en/cart"
    And I wait for the page to load
    And I press "checkout securely"
    And I wait for the page to load
    When I follow "edit-checkout-guest-checkout-as-guest"
    And I wait for the page to load

  @cod @hd
  Scenario: As a Guest,
  I should be able to checkout using COD
    And I should not see the link "create an account"
    And I fill in "edit-guest-delivery-home-address-shipping-given-name" with "Test"
    And I fill in "edit-guest-delivery-home-address-shipping-family-name" with "Test"
    When I enter a valid Email ID in field "edit-guest-delivery-home-address-shipping-organization"
    And I fill in "edit-guest-delivery-home-address-shipping-mobile-number-mobile" with "55004455"
    And I select "Kuwait International Airport" from "edit-guest-delivery-home-address-shipping-administrative-area"
    And I fill in "edit-guest-delivery-home-address-shipping-locality" with "Block A"
    And I fill in "edit-guest-delivery-home-address-shipping-address-line1" with "Street B"
    And I fill in "edit-guest-delivery-home-address-shipping-dependent-locality" with "Builing C"
    When I fill in "edit-guest-delivery-home-address-shipping-address-line2" with "2"
    And I press "deliver to this address"
    And I wait for AJAX to finish
    When I check the "member_delivery_home[address][shipping_methods]" radio button with "Standard Delivery" value
    And I wait for AJAX to finish
    And I press "proceed to payment"
    And I wait for the page to load
    When I select a payment option "payment_method_title_cashondelivery"
    And I wait for AJAX to finish
    And I accept terms and conditions
    And I press "place order"
    And I wait for the page to load
    Then I should see text matching "Thank you for shopping online with us, Test Test"
    And I should see text matching "Your order number is "
    #checking product attributes on order confirmation
    And I wait 10 seconds
    When I click on ".product--count td" element
    And I wait for AJAX to finish
    Then I should see "size:"
    And I should see "Item code:"
    And I should see "Quantity:"