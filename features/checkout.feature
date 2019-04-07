@javascript @orca_ignore
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

  Scenario: As a Guest,
  I should be able to change billing address after visiting the shipping page and still be able to checkout using COD
    Given I enter a valid Email ID in field "edit-billing-information-address-email"
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
    When I click "Return to billing"
    And I wait for the page to load
    Then the "edit-billing-information-address-address-fields-firstname" field should contain "Dries"

    When I fill in "edit-billing-information-address-address-fields-postcode" with "54321"
    And I press "Continue to shipping"
    And I wait for the page to load
    And I check the box "edit-shipping-information-address-use-billing-address"
    And I wait for AJAX to finish
    Then the "Zip code" field should contain "54321"

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

  Scenario: As a Guest,
  I should be able to change billing address after visiting the shipping page and still be able to checkout using COD
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

    When I check the box "edit-shipping-information-address-use-billing-address"
    And I wait for AJAX to finish
    And I press "Estimate Shipping"
    And I wait for AJAX to finish
    And I select "Best Way — Table Rate (15)" from "shipping_methods_wrapper"
    And I wait 1 seconds
    And I press "Continue to payment"
    And I wait for the page to load
    And I select the radio button "Cash on delivery"
    And I wait for AJAX to finish
    And I press "Continue to review"
    And I wait for the page to load

    When I click "Return to payment"
    And I wait for the page to load
    And I click "Return to shipping"
    And I wait for the page to load
    And I click "Return to billing"
    And I wait for the page to load
    Then the "edit-billing-information-address-address-fields-firstname" field should contain "Dries"

    When I fill in "edit-billing-information-address-address-fields-postcode" with "54321"
    And I press "Continue to shipping"
    And I wait for the page to load
    Then the "Zip code" field should contain "12345"
    When I press "Estimate Shipping"
    And I wait for AJAX to finish
    And I select "Best Way — Table Rate (15)" from "shipping_methods_wrapper"
    And I wait 1 seconds
    And I press "Continue to payment"
    And I wait for the page to load

    When I select the radio button "Cash on delivery"
    And I wait for AJAX to finish
    And I press "Continue to review"
    And I wait for the page to load
    Then I should see "12345" in the "#edit-review-shipping-information .postal-code" element
    And I should see "54321" in the "#edit-review-billing-information .postal-code" element

    When I go to "/cart/checkout/billing"
    And I wait for the page to load
    And I select "KG" from "edit-billing-information-address-address-fields-country-id"
    And I press "Continue to shipping"
    Then the "Zip code" field should contain "12345"
    And the "Country" field should contain "US"

    When I press "Estimate Shipping"
    And I wait for AJAX to finish
    And I select "Best Way — Table Rate (15)" from "shipping_methods_wrapper"
    And I wait 1 seconds
    And I press "Continue to payment"
    And I wait for the page to load

    When I select the radio button "Cash on delivery"
    And I wait for AJAX to finish
    And I press "Continue to review"
    And I wait for the page to load
    Then I should see "United States" in the "#edit-review-shipping-information" element
    And I should see "Kyrgyzstan" in the "#edit-review-billing-information" element

    When I press "Pay and complete purchase"
    And I wait for the page to load
    Then I should see "Your order has been submitted" in the "content"

  @shipping
  Scenario: As a Guest,
  I should be able to use different shipping address and checkout using COD.
    Given I enter a valid Email ID in field "edit-billing-information-address-email"
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
    And I select "Mr" from "edit-shipping-information-address-address-fields-title"
    And I fill in "edit-shipping-information-address-address-fields-firstname" with "John"
    And I fill in "edit-shipping-information-address-address-fields-lastname" with "Snow"
    And I fill in "edit-shipping-information-address-address-fields-telephone" with "55224455"
    And I fill in "edit-shipping-information-address-address-fields-street" with "Winterfell Manor"
    And I fill in "edit-shipping-information-address-address-fields-city" with "Winterfell"
    And I select "WY" from "edit-shipping-information-address-address-fields-region"
    And I fill in "edit-shipping-information-address-address-fields-postcode" with "67890"

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
    Then I should see "John Snow" in the "#edit-review-shipping-information" element
    And I should see "Dries Buytart" in the "#edit-review-billing-information" element

    When I press "Pay and complete purchase"
    And I wait for the page to load
    Then I should see "Your order has been submitted" in the "content"

  @shipping
  Scenario: As a Guest,
  I should be able to use same shipping address and then change my mind and use different shipping address
  and checkout using COD.
    Given I enter a valid Email ID in field "edit-billing-information-address-email"
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
    And I wait for AJAX to finish
    Then the "edit-shipping-information-address-address-fields-firstname" ajax field should contain "Dries"

    When I select "Mr" from ajax field "edit-shipping-information-address-address-fields-title"
    And I fill in ajax field "edit-shipping-information-address-address-fields-firstname" with "John"
    And I fill in ajax field "edit-shipping-information-address-address-fields-lastname" with "Snow"
    And I fill in ajax field "edit-shipping-information-address-address-fields-telephone" with "55224455"
    And I fill in ajax field "edit-shipping-information-address-address-fields-street" with "Winterfell Manor"
    And I fill in ajax field "edit-shipping-information-address-address-fields-city" with "Winterfell"
    And I select "WY" from ajax field "edit-shipping-information-address-address-fields-region"
    And I fill in ajax field "edit-shipping-information-address-address-fields-postcode" with "678900"

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
    # you checked reuse, so we don't care about changed values
    Then I should see "Dries Buytart" in the "#edit-review-shipping-information" element
    And I should see "Dries Buytart" in the "#edit-review-billing-information" element

    When I press "Pay and complete purchase"
    And I wait for the page to load
    Then I should see "Your order has been submitted" in the "content"

  @shipping
  Scenario: As a Guest,
  I should be able to use same shipping address and then change my mind and use different shipping address
  and then change my mind again and checkout using COD.
    Given I enter a valid Email ID in field "edit-billing-information-address-email"
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
    And I wait for AJAX to finish
    Then the "edit-shipping-information-address-address-fields-firstname" ajax field should contain "Dries"

    When I select "Mr" from ajax field "edit-shipping-information-address-address-fields-title"
    And I fill in ajax field "edit-shipping-information-address-address-fields-firstname" with "John"
    And I fill in ajax field "edit-shipping-information-address-address-fields-lastname" with "Snow"
    And I fill in ajax field "edit-shipping-information-address-address-fields-telephone" with "55224455"
    And I fill in ajax field "edit-shipping-information-address-address-fields-street" with "Winterfell Manor"
    And I fill in ajax field "edit-shipping-information-address-address-fields-city" with "Winterfell"
    And I select "WY" from ajax field "edit-shipping-information-address-address-fields-region"
    And I fill in ajax field "edit-shipping-information-address-address-fields-postcode" with "678900"

    When I uncheck the ajax box "edit-shipping-information-address-use-billing-address"
    And I wait for AJAX to finish
    And I check the ajax box "edit-shipping-information-address-use-billing-address"
    And I wait for AJAX to finish
    Then the "edit-shipping-information-address-address-fields-firstname" ajax field should contain "Dries"
    And the "edit-shipping-information-address-address-fields-lastname" ajax field should contain "Buytart"
    And the "edit-shipping-information-address-address-fields-city" ajax field should contain "Boston"
    And the "edit-shipping-information-address-address-fields-region" ajax field should contain "MA"

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
    Then I should see "Dries Buytart" in the "#edit-review-shipping-information" element
    And I should see "Dries Buytart" in the "#edit-review-billing-information" element

    When I press "Pay and complete purchase"
    And I wait for the page to load
    Then I should see "Your order has been submitted" in the "content"

  @shipping
  Scenario: As a Guest,
  I should be able to use same shipping address and then change it from review pane and checkout using COD.
    Given I enter a valid Email ID in field "edit-billing-information-address-email"
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
    And I wait for AJAX to finish

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
    Then I should see "Dries Buytart" in the "#edit-review-shipping-information" element
    And I should see "Dries Buytart" in the "#edit-review-billing-information" element

    When I go to "/cart/checkout/shipping"
    And I wait for the page to load
    And I select "KG" from "edit-shipping-information-address-address-fields-country-id"
    And I wait for AJAX to finish
    Then I should not see an "edit-shipping-information-address-address-fields-region" element
    And the "edit-shipping-information-address-address-fields-postcode" field should have label "Postal code"

    When I press "Estimate Shipping"
    And I wait for AJAX to finish
    And I select "Flat Rate — Fixed (5)" from "shipping_methods_wrapper"
    And I wait 1 seconds
    And I press "Continue to payment"
    And I wait for the page to load

    When I select the radio button "Cash on delivery"
    And I wait for AJAX to finish
    And I press "Continue to review"
    And I wait for the page to load
    Then I should see "United States" in the "#edit-review-billing-information" element
    And I should see "Kyrgyzstan" in the "#edit-review-shipping-information" element

    When I press "Pay and complete purchase"
    And I wait for the page to load
    Then I should see "Your order has been submitted" in the "content"