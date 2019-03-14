<?php

namespace Drupal\Tests\acm_customer\Unit;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Tests\UnitTestCase;
use Drupal\acm_customer\Plugin\rest\resource\CustomerDeleteResource;

/**
 * @coversDefaultClass \Drupal\acm_customer\Plugin\rest\resource\CustomerDeleteResource
 * @group acm_cart
 *
 * @todo Test with short sessions to make sure they expire properly.
 */
class CustomerDeleteResourceTest extends UnitTestCase {

  /**
   * The mock logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

  /**
   * The customer delete resource.
   *
   * @var \Drupal\acm_customer\Plugin\rest\resource|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $customerDeleteResource;

  /**
   * A user object.
   *
   * @var \Drupal\user\Entity\User|\PHPUnit_Framework_MockObject_MockObject
   */
  public $userMock;

  /**
   * A response object.
   *
   * @var \Drupal\rest\ModifiedResourceResponse
   */
  protected $response;

  /**
   * Generate a User mock.
   */
  private function createUserMock() {
    $userMock = $this->createMock('Drupal\user\Entity\User');
    $userMock->method('delete')
      ->willReturn(TRUE);
    return $userMock;
  }

  /**
   * Generate a User mock with an error on the delete method.
   */
  private function createUserErrorMock() {
    $userMock = $this->createMock('Drupal\user\Entity\User');
    $userMock->method('delete')
      ->will($this->throwException(new EntityStorageException('error during delete')));
    return $userMock;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create Mock of logger.
    $this->logger = $this->createMock('Psr\Log\LoggerInterface');

    // Create Mock of CustomerDeleteResource.
    $configuration = [];
    $plugin_id = 'acm_customer_delete';
    $plugin_definition = 'acm_customer_delete';
    $serializer_formats = [];
    $constructorArgs = [
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $this->logger,
    ];

    $customerDeleteResourceStub = $this->getMockBuilder('Drupal\acm_customer\Plugin\rest\resource\CustomerDeleteResource')
      ->setConstructorArgs($constructorArgs)
      ->setMethods(['myUserLoadByMail'])
      ->getMock();
    $returnValueMap = [
        ['badUser@bad.com', $this->createUserErrorMock()],
        ['validEmail@valid.com', $this->createUserMock()],
        ['invalidEmail@invalid.com', ''],
    ];
    $customerDeleteResourceStub->method('myUserLoadByMail')
      ->will($this->returnValueMap($returnValueMap));

    $this->customerDeleteResource = $customerDeleteResourceStub;
  }

  /**
   * Tests the post() method with no data passed.
   *
   * @covers ::post
   */
  public function testPostNoData() {
    $response = $this->customerDeleteResource->post([]);
    $content = $response->getResponseData();
    $this->assertSame(FALSE, $content['success']);
  }

  /**
   * Tests the post() method with empty email passed.
   *
   * @covers ::post
   */
  public function testPostEmptyEmail() {
    $response = $this->customerDeleteResource->post(['email' => '']);
    $content = $response->getResponseData();
    $this->assertSame(TRUE, $content['success']);
  }

  /**
   * Tests an invalid email on the post() method.
   *
   * @covers ::post
   */
  public function testPostInvalidEmail() {
    $response = $this->customerDeleteResource->post(['email' => 'invalidEmail@invalid.com']);
    $content = $response->getResponseData();
    $this->assertSame(TRUE, $content['success']);
  }

  /**
   * Tests an error on the user delete in the post() method.
   *
   * @covers ::post
   */
  public function testErrorOnDelete() {
    $response = $this->customerDeleteResource->post(['email' => 'badUser@bad.com']);
    $content = $response->getResponseData();
    $this->assertSame(TRUE, $content['success']);
  }

  /**
   * Tests a successful delete on the post() method.
   *
   * @covers ::post
   */
  public function testSuccessfulDelete() {
    $response = $this->customerDeleteResource->post(['email' => 'validEmail@valid.com']);
    $content = $response->getResponseData();
    $this->assertSame(TRUE, $content['success']);
  }

}
