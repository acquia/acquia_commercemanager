<?php

namespace Drupal\Tests\acm\Unit;

use Drupal\Tests\Core\TempStore\PrivateTempStoreTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\acm\DatabaseSessionStore
 * @group acm
 */
class DatabaseSessionStoreTest extends PrivateTempStoreTest {

  /**
   * The mock key value expirable backend.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $keyValue;

  /**
   * The mock lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $lock;

  /**
   * The user temp store.
   *
   * @var \Drupal\user\DatabaseSessionStore
   */
  protected $tempStore;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * A tempstore object belonging to the owner.
   *
   * @var \stdClass
   */
  protected $ownObject;

  /**
   * A tempstore object not belonging to the owner.
   *
   * @var \stdClass
   */
  protected $otherObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->keyValue = $this->getMock('Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface');
    $this->lock = $this->getMock('Drupal\Core\Lock\LockBackendInterface');
    $this->currentUser = $this->getMock('Drupal\acm\User\AccountProxyInterface');
    $this->currentUser->expects($this->any())
      ->method('id')
      ->willReturn(1);

    $this->requestStack = new RequestStack();
    $request = Request::createFromGlobals();
    $this->requestStack->push($request);

    $this->tempStore = new MockDatabaseSessionStore($this->keyValue, $this->lock, $this->currentUser, $this->requestStack);

    $this->ownObject = (object) [
      'data' => 'test_data',
      'owner' => $this->currentUser->id(),
      'updated' => (int) $request->server->get('REQUEST_TIME'),
    ];

    // Clone the object but change the owner.
    $this->otherObject = clone $this->ownObject;
    $this->otherObject->owner = 2;
  }

  /**
   * Tests the get() method.
   *
   * @covers ::get
   */
  public function testGetWithDefault() {
    $this->keyValue->expects($this->at(0))
      ->method('get')
      ->with('1:test')
      ->willReturn(NULL);

    $current_user = $this->getMock('Drupal\acm\User\AccountProxyInterface');
    $tempStore = new MockDatabaseSessionStore($this->keyValue, $this->lock, $current_user, $this->requestStack);

    $this->assertNull($tempStore->get('test'));
    $this->assertSame('default_value', $tempStore->get('test', 'default_value'));
  }

  /**
   * Tests the get() method.
   *
   * @covers ::get
   */
  public function testGetWithNoCurrentUser() {
    $current_user = $this->getMock('Drupal\acm\User\AccountProxyInterface');

    $current_user->expects($this->any())
      ->method('getAccount')
      ->willReturn(NULL);

    $this->keyValue->expects($this->at(0))
      ->method('get')
      ->with('1:test');

    $tempStore = new MockDatabaseSessionStore($this->keyValue, $this->lock, $current_user, $this->requestStack);

    $tempStore->get('test');
  }

  /**
   * Tests the get() method.
   *
   * @covers ::get
   */
  public function testGetWithCurrentUserWithNoPreviousAnonSession() {
    $current_user = $this->getMock('Drupal\acm\User\AccountProxyInterface');
    $current_uid = 3;

    $current_user->expects($this->any())
      ->method('id')
      ->willReturn($current_uid);
    $current_user->expects($this->any())
      ->method('getAccount')
      ->willReturn($current_user);

    // First checks if there is an owner reference.
    $this->keyValue->expects($this->at(0))
      ->method('get')
      ->with($current_uid . ':' . MockDatabaseSessionStore::OWNER_REFERENCE_NAMESPACE)
      ->will($this->returnValue(NULL));

    // Then looks up the key with the current owner since owner reference
    // returned null.
    $this->keyValue->expects($this->at(1))
      ->method('get')
      ->with($current_uid . ':test')
      ->will($this->returnValue(NULL));

    $tempStore = new MockDatabaseSessionStore($this->keyValue, $this->lock, $current_user, $this->requestStack);

    $tempStore->get('test');
  }

  /**
   * Tests the get() method.
   *
   * @covers ::get
   */
  public function testGetWithCurrentUserWithPreviousAnonSession() {
    $current_user = $this->getMock('Drupal\acm\User\AccountProxyInterface');
    $current_uid = 3;

    $current_user->expects($this->any())
      ->method('id')
      ->willReturn($current_uid);
    $current_user->expects($this->any())
      ->method('getAccount')
      ->willReturn($current_user);

    $request = Request::createFromGlobals();
    $owner_reference = (object) [
      'data' => 'orig-sess-id',
      'owner' => $current_uid,
      'updated' => (int) $request->server->get('REQUEST_TIME'),
    ];

    // Returns the original owner reference.
    $this->keyValue->expects($this->at(0))
      ->method('get')
      ->with($current_uid . ':' . MockDatabaseSessionStore::OWNER_REFERENCE_NAMESPACE)
      ->will($this->returnValue($owner_reference));

    // Instead of using the current uid as the owner, it should use the owner
    // reference.
    $this->keyValue->expects($this->at(1))
      ->method('get')
      ->with('orig-sess-id:test')
      ->will($this->returnValue(NULL));

    $tempStore = new MockDatabaseSessionStore($this->keyValue, $this->lock, $current_user, $this->requestStack);

    $tempStore->get('test');
  }

  /**
   * Tests the get() method.
   *
   * @covers ::get
   */
  public function testGetWithCurrentUserWithPreviousAnonSessionAndNoReference() {
    $current_user = $this->getMock('Drupal\acm\User\AccountProxyInterface');
    $current_uid = 3;

    $this->lock->expects($this->once())
      ->method('acquire')
      ->with($current_uid . ':' . MockDatabaseSessionStore::OWNER_REFERENCE_NAMESPACE)
      ->will($this->returnValue(TRUE));
    $this->lock->expects($this->never())
      ->method('wait');
    $this->lock->expects($this->once())
      ->method('release')
      ->with($current_uid . ':' . MockDatabaseSessionStore::OWNER_REFERENCE_NAMESPACE);

    $current_user->expects($this->any())
      ->method('id')
      ->willReturn($current_uid);
    $current_user->expects($this->any())
      ->method('getAccount')
      ->willReturn($current_user);

    // No owner reference yet, but check that it was looked up.
    $this->keyValue->expects($this->at(0))
      ->method('get')
      ->with($current_uid . ':' . MockDatabaseSessionStore::OWNER_REFERENCE_NAMESPACE)
      ->willReturn(NULL);

    // Check that owner reference was created.
    $request = Request::createFromGlobals();
    $owner_reference = (object) [
      'data' => 1,
      'owner' => $current_uid,
      'updated' => (int) $request->server->get('REQUEST_TIME'),
    ];
    $this->keyValue->expects($this->at(1))
      ->method('setWithExpire')
      ->with($current_uid . ':' . MockDatabaseSessionStore::OWNER_REFERENCE_NAMESPACE, $owner_reference);

    // Should still use session id since no owner reference originally.
    $this->keyValue->expects($this->at(2))
      ->method('get')
      ->with('1:test')
      ->willReturn(NULL);

    $tempStore = new MockDatabaseSessionStore($this->keyValue, $this->lock, $current_user, $this->requestStack);

    $tempStore->setCookie(MockDatabaseSessionStore::SESSION_ID_COOKIE, '1');
    $tempStore->get('test');
  }

  /**
   * Tests the set() method.
   *
   * @covers ::set
   */
  public function testSetWithDefaultExpire() {
    $this->lock->expects($this->once())
      ->method('acquire')
      ->with('1:test')
      ->will($this->returnValue(TRUE));
    $this->lock->expects($this->never())
      ->method('wait');
    $this->lock->expects($this->once())
      ->method('release')
      ->with('1:test');

    $this->keyValue->expects($this->once())
      ->method('setWithExpire')
      ->with('1:test', $this->ownObject, 604800);

    $this->tempStore->set('test', 'test_data');
  }

  /**
   * Tests the set() method.
   *
   * @covers ::set
   */
  public function testSetWithCustomExpire() {
    $this->lock->expects($this->once())
      ->method('acquire')
      ->with('1:test')
      ->will($this->returnValue(TRUE));
    $this->lock->expects($this->never())
      ->method('wait');
    $this->lock->expects($this->once())
      ->method('release')
      ->with('1:test');

    $this->keyValue->expects($this->once())
      ->method('setWithExpire')
      ->with('1:test', $this->ownObject, 12345);

    $this->tempStore->set('test', 'test_data', 12345);
  }

}
