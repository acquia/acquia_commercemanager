<?php

namespace Drupal\Tests\acm\Unit;

use Drupal\acm\PhpSessionStore;
use Drupal\Tests\UnitTestCase;

if (!defined('REQUEST_TIME')) {
  define('REQUEST_TIME', (int) $_SERVER['REQUEST_TIME']);
}

/**
 * @coversDefaultClass \Drupal\acm\PhpSessionStore
 * @group acm
 */
class PhpSessionStoreTest extends UnitTestCase {

  /**
   * The mock session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $session;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
    $this->tempStore = new PhpSessionStore($this->session);
  }

  /**
   * Tests the get() method.
   *
   * @covers ::get
   */
  public function testGet() {
    $this->session->expects($this->at(0))
      ->method('get')
      ->with('test');

    $this->tempStore->get('test');
  }

  /**
   * Tests the get() method.
   *
   * @covers ::get
   */
  public function testGetWithExpiredValue() {
    $this->session->expects($this->at(0))
      ->method('get')
      ->with('test')
      ->willReturn([
        'data' => 'test_data',
        'expire' => REQUEST_TIME - 1000,
      ]);

    $this->assertNull($this->tempStore->get('test'));
  }

  /**
   * Tests the get() method.
   *
   * @covers ::get
   */
  public function testGetWithNonExpiredValue() {
    $this->session->expects($this->at(0))
      ->method('get')
      ->with('test')
      ->willReturn([
        'data' => 'test_data',
        'expire' => REQUEST_TIME + 10000,
      ]);

    $this->assertSame('test_data', $this->tempStore->get('test'));
  }

  /**
   * Tests the get() method.
   *
   * @covers ::get
   */
  public function testGetWithDefault() {
    $this->session->expects($this->at(0))
      ->method('get')
      ->with('test')
      ->willReturn([
        'data' => 'test_data',
        'expire' => REQUEST_TIME - 1000,
      ]);

    $this->assertNull($this->tempStore->get('test'));
    $this->assertSame('default_value', $this->tempStore->get('test', 'default_value'));
  }

  /**
   * Tests the set() method.
   *
   * @covers ::set
   */
  public function testSetWithDefaultExpire() {
    $this->session->expects($this->once())
      ->method('set')
      ->with('test', ['data' => 'test_data', 'expire' => 0]);

    $this->tempStore->set('test', 'test_data');
  }

  /**
   * Tests the set() method.
   *
   * @covers ::set
   */
  public function testSetWithCustomExpire() {
    $this->session->expects($this->once())
      ->method('set')
      ->with('test', ['data' => 'test_data', 'expire' => REQUEST_TIME + 12345]);

    $this->tempStore->set('test', 'test_data', 12345);
  }

}
