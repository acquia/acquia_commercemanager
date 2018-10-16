<?php

namespace Drupal\Tests\acm\Unit;

use Drupal\acm\Access\VersionAccessCheck;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\acm\Access\VersionAccessCheck
 * @group acm
 */
class VersionAccessCheckTest extends UnitTestCase {

  /**
   * Tests passing case.
   *
   * @covers ::access
   */
  public function testExpectPass() {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('access commerce administration pages')
      ->willReturn(TRUE);

    $configFactory = $this->getConfigFactoryStub([
      'acm.connector' => [
        'api_version' => 'v2',
      ],
    ]);

    $checker = new VersionAccessCheck($account->reveal(), $configFactory);
    $checkAccess = $checker->access();

    $this->assertEquals($checkAccess, AccessResult::allowed());
  }

  /**
   * Tests fails due to incorrect version.
   *
   * @covers ::access
   */
  public function testFailBecauseVersion() {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('access commerce administration pages')
      ->willReturn(TRUE);

    $configFactory = $this->getConfigFactoryStub([
      'acm.connector' => [
        'api_version' => 'v1',
      ],
    ]);

    $checker = new VersionAccessCheck($account->reveal(), $configFactory);
    $checkAccess = $checker->access();

    $this->assertEquals($checkAccess, AccessResult::forbidden());
  }

  /**
   * Tests fail due to missing permissions.
   *
   * @covers ::access
   */
  public function testFailBecausePermissions() {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('access commerce administration pages')
      ->willReturn(FALSE);

    $configFactory = $this->getConfigFactoryStub([
      'acm.connector' => [
        'api_version' => 'v2',
      ],
    ]);

    $checker = new VersionAccessCheck($account->reveal(), $configFactory);
    $checkAccess = $checker->access();

    $this->assertEquals($checkAccess, AccessResult::forbidden());
  }

  /**
   * Tests fail due to missing permissions and incorrect version.
   *
   * @covers ::access
   */
  public function testCompleteFail() {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('access commerce administration pages')
      ->willReturn(FALSE);

    $configFactory = $this->getConfigFactoryStub([
      'acm.connector' => [
        'api_version' => 'v1',
      ],
    ]);

    $checker = new VersionAccessCheck($account->reveal(), $configFactory);
    $checkAccess = $checker->access();

    $this->assertEquals($checkAccess, AccessResult::forbidden());
  }

}
