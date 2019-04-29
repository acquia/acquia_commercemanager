@javascript @promotions @orca_ignore
Feature: Test various scenarios for promotions

  Scenario: As a guest
  I should be able to avail a discount if the subtotal total is greater than or equal to a value
  by applying a coupon code
    Given I am an anonymous user
    And I am on a simple product page
    And I wait for the page to load
    And I press "Add to cart"
    And I wait for the page to load
    And I go to "/cart"
    And I wait for the page to load
    And I fill in "acm-promotion-coupon" with "incorrect-coupon-code"
    And I press "Update"
    And I wait for the page to load
    Then I should see "Coupon code is not valid" in the "highlighted"
    And I should see "Your cart has been updated." in the "highlighted"

    When I fill in "acm-promotion-coupon" with "demo"
    And I press "Update"
    And I wait for the page to load
    Then I should see "Coupon code is not valid" in the "highlighted"
    And I should see "Your cart has been updated." in the "highlighted"

    When I fill in "edit-cart-24-mb01-quantity" with "3"
    And I fill in "acm-promotion-coupon" with "demo"
    And I press "Update"
    And I wait for the page to load
    Then I should see "Your cart has been updated." in the "highlighted"
    And I should see the discounted price for the product

    When I fill in "edit-cart-24-mb01-quantity" with "4"
    And I press "Update"
    And I wait for the page to load
    Then I should see "Your cart has been updated." in the "highlighted"
    And I should see the discounted price for four products

    When I fill in "edit-cart-24-mb01-quantity" with "2"
    And I press "Update"
    And I wait for the page to load
    Then I should see "Coupon code is not valid" in the "highlighted"
    And I should see "Your cart has been updated." in the "highlighted"
    And I should see the discounted price for four products

    When I press "Remove coupon code"
    And I wait for the page to load
    Then I should see "Your cart has been updated." in the "highlighted"
    And I should see the price doubled for the product
