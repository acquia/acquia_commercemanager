<?php

namespace Drupal\Tests\acm_cart\Unit;

use Drupal\Tests\acm\Unit\Connector\MockAPIWrapper;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @coversDefaultClass \Drupal\acm_cart\CartStorage
 * @group acm_cart
 *
 * @todo Test with short sessions to make sure they expire properly.
 */
class CartStorageTest extends UnitTestCase {

  /**
   * The mock session storage.
   *
   * @var \Drupal\acm\SessionStoreInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $session;

  /**
   * The mock api wrapper.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $apiWrapper;

  /**
   * The mock logger.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The mock logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

  /**
   * The cart storage.
   *
   * @var \Drupal\acm_cart\CartStorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $cartStorage;

  /**
   * A cart object.
   *
   * @var \stdClass
   */
  protected $cartObject;

  /**
   * The key the cart gets stored to.
   *
   * @var string
   */
  protected $storageKey = 'acm_cart';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->session = $this->getMock('Drupal\acm\SessionStoreInterface');
    $this->logger = $this->getMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $this->eventDispatcher = new EventDispatcher();
    $this->apiWrapper = new MockAPIWrapper();

    $this->cartStorage = new MockCartStorage($this->session, $this->apiWrapper, $this->eventDispatcher, $this->logger);

    $cart = (object) [
      'shippable' => TRUE,
      'cart_id' => 123,
      'store_id' => 987,
      'customer_id' => 1,
      'customer_email' => 'test@test.com',
      'totals' => 999.99,
    ];

    // Temporary override, we need to fix CartInterface in near future.
    $interface = 'Drupal\acm_cart\CartInterface';
    $methods = array_map(function (\ReflectionMethod $m) {
      return $m->getName();
    }, (new \ReflectionClass($interface))->getMethods());
    $methods[] = 'getCartItemsCount';
    $mock_cart = $this->getMock($interface, $methods);
    $mock_cart->cart = $cart;
    $mock_cart->expects($this->any())
      ->method('id')
      ->will($this->returnValue($cart->cart_id));

    $mock_cart->expects($this->any())
      ->method('getCart')
      ->will($this->returnValue($cart));

    $mock_cart->expects($this->any())
      ->method('getCartItemsCount')
      ->will($this->returnValue(0));

    $this->cartObject = $mock_cart;
  }

  /**
   * Tests the getCartId() method.
   *
   * @covers ::getCartId
   */
  public function testGetCartId() {
    $this->assertSame(1, $this->cartStorage->getCartId());
  }

  /**
   * Tests the storeCart() method.
   *
   * @covers ::storeCart
   */
  public function testAddCart() {
    $this->session->expects($this->once())
      ->method('set');

    $this->cartStorage->storeCart($this->cartObject);

    $this->assertSame(
      [
        'Drupal_visitor_acm_cart_id' => $this->cartObject->id(),
        'Drupal_visitor_acm_cart_count' => 0,
      ],
      $this->cartStorage->getCookies()
    );
  }

  /**
   * Tests the restoreCart() method.
   *
   * @covers ::restoreCart
   */
  public function testRestoreCart() {
    $cart_id = $this->cartObject->id();

    $this->session->expects($this->once())
      ->method('set');

    $this->cartStorage->restoreCart($cart_id);

    $this->assertSame(
      [
        'Drupal_visitor_acm_cart_id' => $cart_id,
        'Drupal_visitor_acm_cart_count' => 0,
      ],
      $this->cartStorage->getCookies()
    );
  }

  /**
   * Tests the clearCart() method.
   *
   * @covers ::clearCart
   */
  public function testClearCart() {
    $this->session->expects($this->once())
      ->method('remove');

    $this->cartStorage->clearCart();

    $this->assertSame(
      [
        'Drupal_visitor_acm_cart_id' => NULL,
        'Drupal_visitor_acm_cart_count' => NULL,
      ],
      $this->cartStorage->getCookies()
    );
  }

  /**
   * Tests the loadCart() method with a cart in session.
   *
   * @covers ::loadCart
   */
  public function testLoadCartWithCartInSession() {
    $session = $this->getMock('Drupal\acm\SessionStoreInterface');

    $session->expects($this->at(0))
      ->method('get')
      ->with($this->storageKey)
      ->will($this->returnValue($this->cartObject));

    $session->expects($this->exactly(1))
      ->method('get')
      ->with($this->storageKey);

    $cartStorage = new MockCartStorage($session, $this->apiWrapper, $this->eventDispatcher, $this->logger);
    $cart = $cartStorage->loadCart();

    $this->assertSame($this->cartObject->id(), $cart->id());
  }

  /**
   * Tests the loadCart() method with no cart in session.
   *
   * @covers ::loadCart
   */
  public function testLoadCartNoCartInSession() {
    $session = $this->getMock('Drupal\acm\SessionStoreInterface');

    $session->expects($this->exactly(1))
      ->method('get')
      ->with($this->storageKey);

    $cartStorage = new MockCartStorage($session, $this->apiWrapper, $this->eventDispatcher, $this->logger);

    // This is the first time loadCart is being called with no param or TRUE
    // as param, so we expect 1.
    $expected_cart_id = 1;
    $new_cart = $cartStorage->loadCart();

    $this->assertSame($expected_cart_id, $new_cart->id());

    $this->assertSame(
      [
        'Drupal_visitor_acm_cart_id' => $expected_cart_id,
        'Drupal_visitor_acm_cart_count' => 0,
      ],
      $cartStorage->getCookies()
    );
  }

  /**
   * Tests the updateCart() method.
   *
   * @covers ::updateCart
   */
  public function testUpdateCart() {
    $session = $this->getMock('Drupal\acm\SessionStoreInterface');

    $session->expects($this->exactly(1))
      ->method('get')
      ->with($this->storageKey);

    $cartStorage = new MockCartStorage($session, $this->apiWrapper, $this->eventDispatcher, $this->logger);

    // Create a new cart.
    $cart1 = $cartStorage->updateCart();
    // Make sure same cart is returned.
    $cart2 = $cartStorage->updateCart();

    $this->assertEquals($cart1, $cart2);

    $this->assertSame(
      [
        'Drupal_visitor_acm_cart_id' => $cart1->id(),
        'Drupal_visitor_acm_cart_count' => 0,
      ],
      $cartStorage->getCookies()
    );
  }

  /**
   * Tests the createCart() method.
   *
   * @covers ::createCart
   */
  public function testCreateCart() {
    // Cart in session.
    $this->session->expects($this->once())
      ->method('set')
      ->with($this->storageKey);

    $cart = $this->cartStorage->createCart();

    $this->assertSame(
      [
        'Drupal_visitor_acm_cart_id' => $cart->id(),
        'Drupal_visitor_acm_cart_count' => 0,
      ],
      $this->cartStorage->getCookies()
    );
  }

  /**
   * Tests the associateCart() method.
   *
   * @covers ::associateCart
   */
  public function testAssociateCart() {
    $test_customer_id = 999999;

    $this->session->expects($this->exactly(3))
      ->method('set')
      ->with($this->storageKey);

    // No cart in session.
    $this->cartStorage->associateCart($test_customer_id);

    // Load cart in session.
    $this->cartStorage->loadCart();

    // Cart in session.
    $this->cartStorage->associateCart($test_customer_id);
  }

}
