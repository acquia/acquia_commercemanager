@javascript @manual @promotions
Feature: Test various scenarios for promotions

  Scenario: As a guest
  I should be able to get discount on total cart value
  by applying a coupon code
    Given I am on a simple product page
    And I wait for the page to load
    When I select "2" quantity
    And I press "Add to basket"
    When I wait for AJAX to finish
    When I go to "/cart"
    And I wait for the page to load
    When I click the label for "#ui-id-2"
    And I wait 2 seconds
    When I fill in "edit-coupon" with "FIXED"
    And I click the label for "#coupon-button"
    When I wait for the page to load
    Then I should see "Promotional code applied successfully"
    And the order total price should be reflected as per the coupon discount of "10.000" KWD

  Scenario: As a guest
  I should be able to get 2 products free on buying 5
  after applying a coupon code
    Given I am on a simple product page
    And I wait for the page to load
    When I select "5" quantity
    And I press "Add to basket"
    When I wait for AJAX to finish
    When I go to "/cart"
    And I wait for the page to load
    When I click the label for "#ui-id-2"
    And I wait 2 seconds
    When I fill in "edit-coupon" with "ZZZ345"
    And I click the label for "#coupon-button"
    When I wait for the page to load
    Then I should see "Promotional code applied successfully"
    Then I should get "2" products free on buying "5"

  Scenario: As a guest
  I should be able to avail a discount if the subtotal total is greater than or equal to a value
  by applying a coupon code
    Given I am on a simple product page
    And I wait for the page to load
    When I select "5" quantity
    And I press "Add to basket"
    When I wait for AJAX to finish
    When I go to "/cart"
    And I wait for the page to load
    When I click the label for "#ui-id-2"
    And I wait 2 seconds
    When I fill in "edit-coupon" with "ZZZ456"
    And I click the label for "#coupon-button"
    When I wait for the page to load
    Then I should see "Promotional code applied successfully"
    Then I should get a discount of "6" KWD when the cart subtotal is greater than or equal to "15" KWD

  @arabic
  Scenario: As a guest
  I should be able to get discount on total cart value
  by applying a coupon code
    Given I am on a simple product page
    And I wait for the page to load
    When I select "2" quantity
    And I press "Add to basket"
    When I wait for AJAX to finish
    When I go to "/cart"
    And I wait for the page to load
    When I follow "عربية"
    And I wait for the page to load
    When I click the label for "#ui-id-2"
    And I wait 2 seconds
    When I fill in "edit-coupon" with "FIXED"
    And I click the label for "#coupon-button"
    When I wait for the page to load
    Then I should see "Promotional code applied successfully"
    And the order total price should be reflected as per the coupon discount of "10.000" KWD