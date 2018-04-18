<?php

namespace Drupal\Tests\acm\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Testing \Drupal\acm\I18nHelper.
 *
 * @group acm
 */
class I18nHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'acm',
    'language',
    'i18n_helper_configs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['i18n_helper_configs']);
    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Tests single language store language mapping.
   *
   * @covers \Drupal\acm\I18nHelper::getStoreLanguageMapping
   */
  public function testMultiStoreLanguageMappings() {
    $result = \Drupal::service('acm.i18n_helper')->getStoreLanguageMapping();
    $expectedResult = [
      'en' => '1',
      'fr' => '2',
    ];
    $this->assertEquals($expectedResult, $result);
  }

  /**
   * Tests resolving store ID from langcode.
   *
   * @covers \Drupal\acm\I18nHelper::getStoreIdFromLangcode
   */
  public function testGetStoreIdFromLangcode() {
    $result = \Drupal::service('acm.i18n_helper')->getStoreIdFromLangcode();
    $expectedResult = '1';
    $this->assertEquals($expectedResult, $result);

    $result = \Drupal::service('acm.i18n_helper')->getStoreIdFromLangcode('en');
    $expectedResult = '1';
    $this->assertEquals($expectedResult, $result);

    $result = \Drupal::service('acm.i18n_helper')->getStoreIdFromLangcode('fr');
    $expectedResult = '2';
    $this->assertEquals($expectedResult, $result);
  }

  /**
   * Tests resolving langcode from store ID.
   *
   * @covers \Drupal\acm\I18nHelper::getLangcodeFromStoreId
   */
  public function testGetLangcodeFromStoreId() {
    $result = \Drupal::service('acm.i18n_helper')->getLangcodeFromStoreId('1');
    $expectedResult = 'en';
    $this->assertEquals($expectedResult, $result);

    $result = \Drupal::service('acm.i18n_helper')->getLangcodeFromStoreId('2');
    $expectedResult = 'fr';
    $this->assertEquals($expectedResult, $result);
  }

}
