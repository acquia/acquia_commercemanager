@javascript @orca_ignore
Feature: Test basket page

  Background:
    Given I am an anonymous user
    And I am on a simple product page
    And I wait for the page to load
    Then I should see "Joust Duffle Bag" in the "content"
    When I press "Add to cart"
    And I wait for the page to load

  Scenario:  As a Guest
  I should be able to see the products added to basket
    When I go to "/cart"
    And I wait for the page to load
    And I should see the link for simple product
    Then I should see the button "Checkout"
    Then I should see "Product"
    And I should see "Quantity"
    And I should see "Subtotal"
    Then I should see "Grand total"
    And I should see "Coupon code"

  Scenario: As a Guest
  I should be able to add more quantity
  and remove products from the basket
    When I go to "/cart"
    And I wait for the page to load
    When I enter 2 for "edit-cart-24-mb01-quantity"
    And I press "Update"
    And I wait for the page to load
    Then I should see the price doubled for the product
    When I enter 0 for "edit-cart-24-mb01-quantity"
    And I press "Update"
    And I wait for the page to load
    Then I should see "There are no products in your cart yet."