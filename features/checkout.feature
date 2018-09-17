@javascript
Feature: Test Checkout feature
  Background:
    Given I am an anonymous user
    And I am on a simple product page
    And I wait for the page to load
    When I press "Add to cart"
    And I wait for the page to load
    And I go to "/cart"
    And I wait for the page to load
    And I press "Checkout"
    And I wait for the page to load

  @cod
  Scenario: As a Guest,
  I should be able to checkout using COD
    And I enter a valid Email ID in field "edit-billing-information-address-email"
    And I select "Mr" from "edit-billing-information-address-address-fields-title"
    And I fill in "edit-billing-information-address-address-fields-firstname" with "Dries"
    And I fill in "edit-billing-information-address-address-fields-lastname" with "Buytart"
    And I fill in "edit-billing-information-address-address-fields-telephone" with "55004455"
    And I fill in "edit-billing-information-address-address-fields-street" with "State street 1"
    And I fill in "edit-billing-information-address-address-fields-city" with "Boston"
    And I select "MA" from "edit-billing-information-address-address-fields-region"
    And I fill in "edit-billing-information-address-address-fields-postcode" with "12345"
    When I press "Continue to shipping"
    And I wait for the page to load
    And I check the box "edit-shipping-information-address-use-billing-address"
    When I press "Estimate Shipping"
    And I wait for AJAX to finish
    And I select "Best Way — Table Rate (15)" from "shipping_methods_wrapper"
    And I wait 1 seconds
    When I press "Continue to payment"
    And I wait for the page to load
    Then I select the radio button "Cash on delivery"
    And I wait for AJAX to finish
    When I press "Continue to review"
    And I wait for the page to load
    When I press "Pay and complete purchase"
    And I wait for the page to load
    Then I should see "Your order has been submitted" in the "content"
