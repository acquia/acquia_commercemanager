<?php
// @codingStandardsIgnoreFile
// phpcs:ignore

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Context\Context;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Exception\ElementNotFoundException;

define("ORDER_ASC", 1);
define("ORDER_DSC", 0);

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDrupalContext implements SnippetAcceptingContext {

  private $simple_product;

  private $simple_url;

  private $simple_title;

  private $simple_price;

  private $simple_doubled_price;

  private $simple_price_four;

  private $simple_discounted_price;

  private $simple_discounted_price_four;


  /**
   * Every scenario gets its own context instance.
   *
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct($parameters) {
    $this->simple_url = $parameters['simpleurl'];
    $this->simple_title = $parameters['simpletitle'];
    $this->simple_price = $parameters['simpleprice'];
    $this->simple_doubled_price = $parameters['simpledoupbledprice'];
    $this->simple_price_four = $parameters['simplepricefour'];
    $this->simple_discounted_price = $parameters['simplediscountedprice'];
    $this->simple_discounted_price_four = $parameters['simplediscountedpricefour'];
  }

  /**
   * @AfterStep
   */
  public function takeScreenshotAfterFailedStep($event)
  {
    if ($event->getTestResult()
        ->getResultCode() === \Behat\Testwork\Tester\Result\TestResult::FAILED
    ) {
      $driver = $this->getSession()->getDriver();
      if ($driver instanceof \Behat\Mink\Driver\Selenium2Driver) {
        $stepText = $event->getStep()->getText();
        $fileName = preg_replace('#[^a-zA-Z0-9\._-]#', '', $stepText) . '-failed.png';
        $filePath = realpath($this->getMinkParameter('files_path'));
        $this->saveScreenshot($fileName, $filePath);
      }
    }
  }

  /**
   * @Given /^I am on a simple product page$/
   */
  public function iAmOnASimpleProductPage()
  {
    $this->visitPath($this->simple_url);
  }

  /**
   * @Then /^I should see the link for simple product$/
   */
  public function iShouldSeeTheLinkForSimpleProduct()
  {
    $page = $this->getSession()->getPage();
    $this->simple_product = $this->simple_title;
    $link = $page->findLink($this->simple_product);
    if (!$link) {
      throw new \Exception('Link for simple product not found');
    }
  }

  /**
   * @Given /^I wait for the page to load$/
   */
  public function iWaitForThePageToLoad()
  {
    $this->getSession()->wait(10000, "document.readyState === 'complete'");
  }

  /**
   * @When /^I enter a valid Email ID in field "([^"]*)"$/
   */
  public function iEnterAValidEmailID($field)
  {
    $randomString = 'randemail' . rand(2, getrandmax());
    $email_id = $randomString . '@gmail.com';
    $this->getSession()->getPage()->fillField($field, $email_id);
  }

  /**
   * @Given /^I enter an invalid Email ID in field "([^"]*)"$/
   */
  public function iEnterAnInvalidEmailID($field)
  {
    $randomString = 'randemail' . rand(2, getrandmax());
    $email_id = $randomString . '@gmailcom';
    $this->getSession()->getPage()->fillField($field, $email_id);
  }

  /**
   * @Given /^I wait (\d+) seconds$/
   */
  public function iWaitSeconds($seconds)
  {
    sleep($seconds);
  }

  /**
   * @Then /^I should see the price doubled for the product$/
   */
  public function iShouldSeeThePriceDoubledForTheProduct()
  {
    $page = $this->getSession()->getPage();
    $expected_price = $page->find('css', '[data-drupal-selector="edit-totals-grand"] td:last-child')->getText();
    if ($expected_price != $this->simple_doubled_price) {
      throw new \Exception('Price did not get updated after adding the quantity');
    }
  }

  /**
   * @Then /^I should see the discounted price for the product$/
   */
  public function iShouldSeeTheDiscountedPriceForTheProduct()
  {
    $page = $this->getSession()->getPage();
    $expected_price = $page->find('css', '[data-drupal-selector="edit-totals-grand"] td:last-child')->getText();
    if ($expected_price != $this->simple_discounted_price) {
      throw new \Exception('Price did not get updated after adding the coupon');
    }
  }

  /**
   * @Given I should see the discounted price for four products
   * @throws Exception
   */
  public function iShouldSeeTheDiscountedPriceForFourProducts()
  {
    $page = $this->getSession()->getPage();
    $expected_price = $page->find('css', '[data-drupal-selector="edit-totals-grand"] td:last-child')->getText();
    if ($expected_price != $this->simple_discounted_price_four) {
      throw new \Exception('Discounted price is incorrect. Expected '.$expected_price.' to equal '.$this->simple_discounted_price_four);
    }
  }

  /**
   * @Given I should see the price for four products
   * @throws Exception
   */
  public function iShouldSeeThePriceForFourProducts()
  {
    $page = $this->getSession()->getPage();
    $expected_price = $page->find('css', '[data-drupal-selector="edit-totals-grand"] td:last-child')->getText();
    if ($expected_price != $this->simple_price_four) {
      throw new \Exception('Price is incorrect. Expected '.$expected_price.' to equal '.$this->simple_price_four);
    }
  }

  /**
   * @Then /^I should see the link for "([^"]*)"$/
   */
  public function iShouldSeeTheLinkFor($arg1)
  {
    $link = $this->getSession()->getPage()->find('css', $arg1);
    if (!$link) {
      throw new \Exception($arg1 . ' link not found');
    }
  }

  /**
   * @When /^I select "([^"]*)" from dropdown "([^"]*)"$/
   */
  public function iSelectFromDropdown($value, $class)
  {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', $class);
    if ($element !== null) {
      $element->selectOption($value);
    } else {
      echo 'Element not found';
    }
  }

  /**
   * @Then /^the "(?P<field>(?:[^"]|\\")*)" field should have label "(?P<value>(?:[^"]|\\")*)"$/
   */
  public function theFieldShouldHaveLabel($field, $value)
  {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', "[for^='" . $field . "']");
    if ($element->getText() != $value) {
      throw new \Exception("Label is different - " . $element->getText());
    }
  }

  /**
   * Checks, that form field with specified id has specified value
   * Example: Then the "username" ajax field should contain "bwayne"
   * Example: And the "username" ajax field should contain "bwayne"
   *
   * @Then /^the "(?P<field>(?:[^"]|\\")*)" ajax field should contain "(?P<value>(?:[^"]|\\")*)"$/
   */
  public function assertAjaxFieldContains($field, $value)
  {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', "[id^='" . $field . "']");
    if ($element->getValue() != $value) {
      throw new \Exception("Values are not the same.");
    }
  }

  /**
   * Selects option in select field with specified id
   * Example: When I select "Bats" from ajax field "user_fears"
   * Example: And I select "Bats" from ajax field "user_fears"
   *
   * @When /^(?:|I )select "(?P<option>(?:[^"]|\\")*)" from ajax field "(?P<field>(?:[^"]|\\")*)"$/
   */
  public function selectAjaxOption($field, $option)
  {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', "[id^='" . $field . "']");
    $element->selectOption($option);
  }

  /**
   * Fills in form field with specified id
   * Example: When I fill in ajax field "username" with: "bwayne"
   * Example: And I fill in ajax field "bwayne" for "username"
   *
   * @When /^(?:|I )fill in ajax field "(?P<field>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/
   * @When /^(?:|I )fill in ajax field "(?P<field>(?:[^"]|\\")*)" with:$/
   * @When /^(?:|I )fill in ajax field "(?P<value>(?:[^"]|\\")*)" for "(?P<field>(?:[^"]|\\")*)"$/
   */
  public function fillAjaxField($field, $value)
  {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', "[id^='" . $field . "']");
    $element->setValue($value);
  }

  /**
   * Checks checkbox with specified id|name|label|value
   * Example: When I check "Pearl Necklace"
   * Example: And I check "Pearl Necklace"
   *
   * @When /^(?:|I )check the ajax box "(?P<option>(?:[^"]|\\")*)"$/
   */
  public function checkAjaxCheckbox($option)
  {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', "[id^='" . $option . "']");
    $element->check();
  }

  /**
   * Unchecks checkbox with specified id|name|label|value
   * Example: When I uncheck "Broadway Plays"
   * Example: And I uncheck "Broadway Plays"
   *
   * @When /^(?:|I )uncheck the ajax box "(?P<option>(?:[^"]|\\")*)"$/
   */
  public function uncheckAjaxCheckbox($option)
  {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', "[id^='" . $option . "']");
    $element->uncheck();
  }

}
