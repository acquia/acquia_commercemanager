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

  @changebillingaddressinvalidemail
  Scenario: As a Guest,
  I should be able to change billing address and checkout using COD
    And I enter an invalid Email ID in field "edit-billing-information-address-email"
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
    Then I should see "Invalid email address" in the "content"

  @changebillingaddressafterproceedingtoshipping
  Scenario: As a Guest,
  I should be able to change billing address after visting the shipping page and still be able to checkout using COD
    Given I enter a valid Email ID in field "edit-billing-information-address-email"
    And I select "Mr" from "edit-billing-information-address-address-fields-title"
    #And I fill in "edit-billing-information-address-address-fields-firstname" with "Dries"
    And I fill in "edit-billing-information-address-address-fields-lastname" with "Buytart"
    And I fill in "edit-billing-information-address-address-fields-telephone" with "55004455"
    And I fill in "edit-billing-information-address-address-fields-street" with "State street 1"
    And I fill in "edit-billing-information-address-address-fields-city" with "Boston"
    And I select "MA" from "edit-billing-information-address-address-fields-region"
    And I fill in "edit-billing-information-address-address-fields-postcode" with "12345"
    When I press "Continue to shipping"
    Then I should see "Please fill out this field" in the "body"

    When I fill in "edit-billing-information-address-address-fields-firstname" with "Dries"
    And I press "Continue to shipping"
    And I wait for the page to load
    Then I should not see "There was an error retrieving your account. Please try again."

    When I press "Return to billing"
    And I wait for the page to load
    Then I should see "Dries" in the "edit-billing-information-address-address-fields-firstname"

    When I fill in "edit-billing-information-address-address-fields-postcode" with "54321"
    And I press "Continue to shipping"
    And I wait for the page to load
    And I check the box "edit-shipping-information-address-use-billing-address"
    And I wait for AJAX to finish
    Then I should see "54321" in the "edit-shipping-information-address-address-fields-postcode--*"

    When I press "Estimate Shipping"
    And I wait for AJAX to finish
    And I select "Best Way — Table Rate (15)" from "shipping_methods_wrapper"
    And I wait 1 seconds
    And I press "Continue to payment"
    And I wait for the page to load
    And I select the radio button "Cash on delivery"
    And I wait for AJAX to finish
    And I press "Continue to review"
    And I wait for the page to load
    And I press "Pay and complete purchase"
    And I wait for the page to load
    Then I should see "Your order has been submitted" in the "content"


  @changebillingaddressafterproceedingtopayments
  Scenario: As a Guest,
  I should be able to change billing address after visting the shipping page and still be able to checkout using COD
    Given I enter a valid Email ID in field "edit-billing-information-address-email"
    And I select "Mr" from "edit-billing-information-address-address-fields-title"
    And I fill in "edit-billing-information-address-address-fields-firstname" with "Dries"
    And I fill in "edit-billing-information-address-address-fields-lastname" with "Buytart"
    And I fill in "edit-billing-information-address-address-fields-telephone" with "55004455"
    And I fill in "edit-billing-information-address-address-fields-street" with "State street 1"
    And I fill in "edit-billing-information-address-address-fields-city" with "Boston"
    And I select "MA" from "edit-billing-information-address-address-fields-region"
    And I fill in "edit-billing-information-address-address-fields-postcode" with "12345"
    And I press "Continue to shipping"
    And I wait for the page to load
    Then I should not see "There was an error retrieving your account. Please try again."

    When I press "Estimate Shipping"
    And I wait for AJAX to finish
    And I select "Best Way — Table Rate (15)" from "shipping_methods_wrapper"
    And I wait 1 seconds
    And I press "Continue to payment"
    And I wait for the page to load
    And I select the radio button "Cash on delivery"
    And I wait for AJAX to finish
    And I press "Continue to review"
    And I wait for the page to load

    When I press "Return to payment"
    And I press "Return to shipping"
    And I press "Return to billing"
    And I wait for the page to load
    Then I should see "Dries" in the "edit-billing-information-address-address-fields-firstname"

    When I fill in "edit-billing-information-address-address-fields-postcode" with "54321"
    And I press "Continue to shipping"
    Then I should see "12345" the old post code in "edit-shipping-information-address-address-fields-postcode"
    # AND I expect my shipping-method choice to be remembered. Please make it so
    When I press "Estimate Shipping"
    And I wait for AJAX to finish
    And I select "Best Way — Table Rate (15)" from "shipping_methods_wrapper"
    And I wait 1 seconds
    And I press "Continue to payment"
    And I wait for the page to load
    # AND I expect my payment choice to be remembered. Please make it so
    But I select the radio button "Cash on delivery"
    And I wait for AJAX to finish
    And I press "Continue to review"
    And I wait for the page to load
    Then I should see "54321" within "edit-review-billing-information"
    And I should see "12345" within "edit-review-shipping-information"

    When I press Edit inside billing
    And I wait for the page to load
    And I select "KG" from "edit-billing-information-address-address-fields-country-id"
    And I press "Continue to shipping"
    Then I should see "12345" the shipping address post code in "edit-shipping-information-address-address-fields-postcode"
    And I should see "US" selected as the shipping address country in "edit-shipping-information-address-address-fields-country-id"
    # AND I expect my shipping-method choice to be remembered. Please make it so
    When I press "Estimate Shipping"
    And I wait for AJAX to finish
    And I select "Best Way — Table Rate (15)" from "shipping_methods_wrapper"
    And I wait 1 seconds
    And I press "Continue to payment"
    And I wait for the page to load
    # AND I expect my payment choice to be remembered. Please make it so
    But I select the radio button "Cash on delivery"
    And I wait for AJAX to finish
    And I press "Continue to review"
    And I wait for the page to load
    Then I should see "United States" within "edit-review-billing-information"
    And I should see "Kyrgyzstan" within  "edit-review-shipping-information"

    When I press "Pay and complete purchase"
    And I wait for the page to load
    Then I should see "Your order has been submitted" in the "content"

