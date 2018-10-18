<?php

namespace Drupal\Tests\acm\Unit\Connector;

use Drupal\acm\Connector\APIWrapper;
use Drupal\acm\APIHelper;
use Drupal\acm\I18nHelper;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\acm\Connector\ClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\acm\Access\VersionAccessCheck
 * @group acm
 */
class APIWrapperTest extends UnitTestCase {

  public $guzzleHttpResponseMock;
  public $clientMock;
  public $clientFactoryMock;
  public $configMock;
  public $configFactoryMock;
  public $loggerChannelMock;
  public $loggerChannelFactoryMock;
  public $i18nHelperMock;
  public $helperMock;
  public $storeId;
  public $apiVersion;

  /**
   * The system under test.
   *
   * @var \Drupal\acm\Connector\APIWrapper|\PHPUnit_Framework_MockObject_MockObject
   */
  public $model;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Define some things.
    // Arbitrary.
    $this->storeId = '3';
    // The current version.
    $this->apiVersion = 'v2';

    // Mock a response so we can stub response->getBody() and
    // response->getStatusCode() in each test.
    $this->guzzleHttpResponseMock = $this->getMockBuilder(Response::class)
      ->disableOriginalConstructor()
      ->setMethods(['getBody', 'getStatusCode'])
      ->getMock();

    // Mock the constructor-injected classes
    // a) clientFactory
    // Mock the client so we can stub $client->post() and $client->get()
    $this->clientMock = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->setMethods(['post', 'get'])
      ->getMock();

    // Mock the client factory.
    $this->clientFactoryMock = $this->getMockBuilder(ClientFactory::class)
      ->disableOriginalConstructor()
      ->setMethods(['createClient'])
      ->getMock();
    // Make the factory return the mocked client.
    $this->clientFactoryMock->expects($this->once())
      ->method("createClient")
      ->withAnyParameters()
      ->willReturn($this->clientMock);

    // b) Drupal\Core\Config\ConfigFactoryInterface
    // Make a mock config factory.
    $this->configMock = [
      'acm.connector' => ['api_version' => $this->apiVersion],
    ];
    $this->configFactoryMock = $this->getConfigFactoryStub($this->configMock);

    // c) Drupal\Core\Logger\LoggerChannelFactory
    // Make a mock logger channel.
    $this->loggerChannelMock = $this->getMockBuilder(LoggerChannel::class)
      ->disableOriginalConstructor()
      ->setMethods([])
      ->getMock();
    // Make a factory to return it.
    $this->loggerChannelFactoryMock = $this->getMockBuilder(LoggerChannelFactory::class)
      ->disableOriginalConstructor()
      ->setMethods(['get'])
      ->getMock();
    $this->loggerChannelFactoryMock->expects($this->any())
      ->method('get')
      ->with('acm_sku')
      ->willReturn($this->loggerChannelMock);

    // d) Drupal\acm\I18nHelper.
    $this->i18nHelperMock = $this->getMockBuilder(I18nHelper::class)
      ->disableOriginalConstructor()
      ->setMethods(['getStoreIdFromLangcode'])
      ->getMock();
    $this->i18nHelperMock->expects($this->any())
      ->method('getStoreIdFromLangcode')
      ->withAnyParameters()
      ->willReturn($this->storeId);

    // e) Drupal\acm\APIHelper.
    $this->helperMock = $this->getMockBuilder(APIHelper::class)
      ->disableOriginalConstructor()
      ->setMethods([])
      ->getMock();

    // Generate the class under test
    // We use a mock so that we can stub the trait $this->tryAgentRequest()
    // We inject the mocked constructor classes.
    $this->createThisModel();
  }

  /**
   * Call this function if you change one fo the constructors.
   */
  public function createThisModel() {
    // Generate the class under test
    // We use a mock so that we can stub the trait $this->tryAgentRequest()
    // We inject the mocked constructor classes.
    $this->model = $this->getMockBuilder(APIWrapper::class)
      ->enableOriginalConstructor()
      ->setConstructorArgs(
        [
          $this->clientFactoryMock,
          $this->configFactoryMock,
          $this->loggerChannelFactoryMock,
          $this->i18nHelperMock,
          $this->helperMock,
        ]
      )
      ->setMethods(NULL)
      ->getMock();
  }

  /**
   * An example of how to test an endpoint for v1.
   */
  public function testCreateCartV1() {
    // Set the version. But now we need a new configFactoryMock too.
    $this->apiVersion = 'v1';

    // Make the new mock config factory.
    $this->configMock = [
      'acm.connector' => ['api_version' => $this->apiVersion],
    ];
    $this->configFactoryMock = $this->getConfigFactoryStub($this->configMock);
    // And now we need to make a new class to test.
    $this->createThisModel();

    $customer_id = 17;
    $expectedEndpoint = $this->apiVersion . "/agent/cart/create";
    // V1 options format.
    $expectedOptions = [];
    $expectedOptions['form_params'] = ['customer_id' => (string) $customer_id];
    $expectedOptions['query'] = ['store_id' => $this->storeId];
    // A V1 throw-back:
    $expectedResultKey = 'cart';

    $mockedResponseBody = json_encode([
      $expectedResultKey => [
        "dummy" => "Any valid JSON string",
        "cart_id" => "For example, being specific to this test",
      ],
      "success" => TRUE,
    ]);

    // Set up the response mock to return some things to test
    // The expectations will change if you test failed routes.
    // Currently, failure can't be tested because *at least* the
    // class new RouteException needs a Drupal container
    // but we aren't testing failed responses here (yet).
    // So valid (mocked) responses only call these once.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->post().
    $this->clientMock->expects($this->once())
      ->method('post')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->createCart($customer_id);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);

  }

  /**
   * Tests cart creation interface.
   */
  public function testCreateCart() {
    $customer_id = 17;
    $expectedEndpoint = $this->apiVersion . "/agent/cart/create";

    $expectedOptions = [];
    $expectedOptions['json'] = ['customer_id' => (string) $customer_id];
    $expectedOptions['query'] = ['store_id' => $this->storeId];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    // The expectations will change if you test failed routes.
    // Currently, failure can't be tested because *at least* the
    // class new RouteException needs a Drupal container
    // but we aren't testing failed responses here (yet).
    // So valid (mocked) responses only call these functions once.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->post().
    $this->clientMock->expects($this->once())
      ->method('post')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->createCart($customer_id);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests stock check for sku.
   */
  public function testSkuStockCheck() {
    $sku = "MB01-24W";
    $expectedEndpoint = $this->apiVersion . "/agent/stock/" . $sku;

    $expectedOptions = [];
    $expectedOptions['query'] = ['store_id' => $this->storeId];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->skuStockCheck($sku);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests getting cart.
   */
  public function testGetCart() {
    $cart_id = "4";
    $customer_id = "17";
    $expectedEndpoint = $this->apiVersion . "/agent/cart/" . $cart_id;

    $expectedOptions = [];
    $expectedOptions['query'] = [
      'customer_id' => (string) $customer_id,
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->getCart($cart_id, $customer_id);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests deleting customer address.
   */
  public function testDeleteCustomerAddress() {
    $customer_id = "17";
    $address_id = "4";
    $expectedEndpoint = $this->apiVersion . "/agent/customer/address/delete";

    $expectedOptions = [];
    $expectedOptions['json'] = [
      'customer_id' => (string) $customer_id,
      'address_id' => $address_id,
    ];
    $expectedOptions['query'] = [
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
      "deleted" => TRUE,
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('post')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->deleteCustomerAddress($customer_id, $address_id);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals(TRUE, $dummyResult);
  }

  /**
   * Tests resetting customer password.
   *
   * Please note this is not a V2 endpoint.
   */
  public function testResetCustomerPassword() {
    $email = "mickey@mouse.com";
    $expectedEndpoint = $this->apiVersion . "/agent/customer/resetpass/get";

    $expectedOptions = [];
    $expectedOptions['json'] = [
      'email' => $email,
    ];
    $expectedOptions['query'] = [
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
      "success" => TRUE,
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('post')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->resetCustomerPassword($email);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals(TRUE, $dummyResult);
  }

  /**
   * Tests authenticating customer.
   */
  public function testAuthenticateCustomer() {
    $email = "mickey@mouse.com";
    $password = "pluto";
    $expectedEndpoint = $this->apiVersion . "/agent/customer/$email";

    $expectedOptions = [];
    $expectedOptions['json'] = [
      'password' => $password,
    ];
    $expectedOptions['query'] = [
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('post')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->authenticateCustomer($email, $password);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests getting customer token.
   */
  public function testGetCustomerToken() {
    $email = "mickey@mouse.com";
    $password = "pluto";
    $expectedEndpoint = $this->apiVersion . "/agent/customer/token/get";

    $expectedOptions = [];
    $expectedOptions['json'] = [
      'email' => $email,
      'password' => $password,
    ];
    $expectedOptions['query'] = [
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('post')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->getCustomerToken($email, $password);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests getting current customer.
   */
  public function testGetCurrentCustomer() {
    $token = "some_token";
    $expectedEndpoint = $this->apiVersion . "/agent/customer-by-token";

    $expectedOptions = [];
    $expectedOptions['json'] = [
      'token' => $token,
    ];
    $expectedOptions['query'] = [
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('post')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->getCurrentCustomer($token);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests getting list of categories.
   */
  public function testGetCategories() {
    $expectedEndpoint = $this->apiVersion . "/agent/categories";

    $expectedOptions = [];
    $expectedOptions['query'] = [
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->getCategories();

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests getting product options.
   */
  public function testGetProductOptions() {
    $expectedEndpoint = $this->apiVersion . "/agent/product/options";

    $expectedOptions = [];
    $expectedOptions['query'] = [
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->getProductOptions();

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Off.
   */
  public function offTestGetPromotionsReturnsEmptyArrayIfTypeIsNotRecognized() {
    $incorrectType = "incorrect";

    // WHY DOES THIS NOT WORK????
    // Reset the expects-count on the client factory mock (just for this test)
    $this->clientFactoryMock->expects($this->never())
      ->method("createClient")
      ->withAnyParameters()
      ->willReturn($this->clientMock);
    // And inject it into a new model...
    $this->createThisModel();

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->never())
      ->method('getBody');
    $this->guzzleHttpResponseMock->expects($this->never())
      ->method('getStatusCode');

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->never())
      ->method('get');

    // It should return an empty array if the type is not cart nor category.
    $dummyResult = $this->model->getPromotions($incorrectType);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals([], $dummyResult);
  }

  /**
   * Tests getting promotions without valid type.
   */
  public function testGetPromotionsGivenNoType() {
    $defaultType = "category";
    $expectedEndpoint = $this->apiVersion . "/agent/promotions/$defaultType";

    $expectedOptions = [];
    $expectedOptions['query'] = [
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->getPromotions($defaultType);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests getting promotions with valid type.
   */
  public function testGetPromotionsGivenValidType() {
    $validType = "cart";
    $expectedEndpoint = $this->apiVersion . "/agent/promotions/$validType";

    $expectedOptions = [];
    $expectedOptions['query'] = [
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->getPromotions($validType);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests getting products changed since provided updated date.
   */
  public function testGetProductsByUpdatedDate() {
    $date_time = new \DateTime();
    $expectedEndpoint = $this->apiVersion . "/agent/products";

    $expectedOptions = [];
    $expectedOptions['query']['updated'] = $date_time->format('Y-m-d H:i:s');
    $expectedOptions['query']['store_id'] = $this->storeId;

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->GetProductsByUpdatedDate($date_time);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests getting products with default page size.
   */
  public function testGetProductsDefaultPageSize() {
    $defaultCount = 100;
    $expectedEndpoint = $this->apiVersion . "/agent/products";

    $expectedOptions = [];
    $expectedOptions['query'] = [
      'page_size' => $defaultCount,
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->getProducts();

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests getting specific amount of products.
   */
  public function testGetProductsGivenCount() {
    $count = 50;
    $expectedEndpoint = $this->apiVersion . "/agent/products";

    $expectedOptions = [];
    $expectedOptions['query'] = [
      'page_size' => $count,
      'store_id' => $this->storeId,
    ];

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->getProducts($count);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

  /**
   * Tests product full sync request.
   */
  public function testProductFullSync() {
    $skus = '1,2,3,4,5';
    // page_size is unused. What is the specification for it?
    $page_size = 0;
    $categoryId = "27";
    $expectedEndpoint = $this->apiVersion . "/agent/products";

    $expectedOptions = [];
    // $expectedOptions['query']['category_id'] = $category_id;.
    $expectedOptions['query'] = [
      // 'category_id' => $category_id,.
      'skus' => $skus,
      'store_id' => $this->storeId,
    ];

    ksort($expectedOptions['query']);

    $mockedResponseBody = json_encode([
      "dummy" => "Any valid JSON string",
    ]);

    // Set up the response mock to return some things to test.
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getBody')
      ->willReturn($mockedResponseBody);
    $this->guzzleHttpResponseMock->expects($this->exactly(1))
      ->method('getStatusCode')
      ->willReturn(200);

    // This *is* the test. Set expectations on $client->get().
    $this->clientMock->expects($this->once())
      ->method('get')
      ->with($expectedEndpoint, $expectedOptions)
      ->willReturn($this->guzzleHttpResponseMock);

    // Do it.
    $dummyResult = $this->model->productFullSync($skus);

    // Check it (this is not really the test).
    // Trivially check that tryAgentRequest() returns the dummy JSON body as
    // an array.
    $this->assertEquals("Any valid JSON string", $dummyResult['dummy']);
  }

}
