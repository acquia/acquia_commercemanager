@javascript
Feature: Test basket page

  Background:
    Given I am on a configurable product
    And I wait for the page to load
    When I select a size for the product
    And I wait for AJAX to finish
    When I press "Add to basket"
    And I wait for AJAX to finish

  Scenario:  As a Guest
  I should be able to see the products added to basket
    When I go to "/en/cart"
    And I wait for the page to load
    And I should see the link for configurable product
    Then I should see the button "checkout securely"
    And I should see "Basket ("
    Then I should see "Product"
    And I should see "Quantity"
    Then I should see "Unit Price"
    And I should see "subtotal"
    Then I should see "Available delivery options"
    Then I should see "Order Total"
    And I should see "(Before Delivery)"
    Then I should see the link "continue shopping" in ".edit-actions.form-actions.js-form-wrapper.form-wrapper" section
    And I should see "Add a promotional code"
    And I should be able to see the footer
    When I click the label for "#edit-continue-shopping-mobile"
    And I wait for the page to load
    Then the url should match "/en"

  Scenario: As a Guest
  I should be able to add more quantity
  and remove products from the basket
    When I go to "/en/cart"
    And I wait for the page to load
    When I select 2 from dropdown
    And I wait for AJAX to finish
    Then I should see the price doubled for the product
    When I follow "remove"
    And I wait for the page to load
    Then I should see "The product has been removed from your basket."