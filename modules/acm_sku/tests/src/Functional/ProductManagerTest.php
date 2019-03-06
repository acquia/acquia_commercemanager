<?php

namespace Drupal\Tests\acm_sku\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\taxonomy\Entity\Term;

/**
 * @coversDefaultClass \Drupal\acm_sku\ProductManager
 *
 * @group acm_sku
 */
class ProductManagerTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'config',
    'field',
    'filter',
    'text',
    'file',
    'image',
    'options',
    'key_value_field',
    'node',
    'user',
    'taxonomy',
    'language',
    'rest',
    'simple_oauth',
    'simple_oauth_extras',
    'serialization',
    'acm',
    'acm_sku',
    'acm_product',
    'pcb',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    Term::create([
      'vid' => 'acm_product_category',
      'name' => 'Cat1',
      'middleware_id' => 1,
    ])->save();

    // Load categories data.
    module_load_include('data', 'acm_sku', 'tests/data/products');
  }

  /**
   * Tests the synchronizeProducts method with no data passed.
   *
   * @covers ::synchronizeProducts
   */
  public function testSynchronizeNoProducts() {
    $result = $this->container->get('acm_sku.product_manager')->synchronizeProducts();
    $processed = $result['created'] + $result['updated'] + $result['failed'] + $result['ignored'] + $result['deleted'];
    $this->assertSame($processed, 0);
  }

  /**
   * Tests the synchronizeProducts method with simple product data passed.
   *
   * @covers ::synchronizeProducts
   */
  public function testSynchronizeSimpleProducts() {
    global $_acm_commerce_simple_products;

    $result = $this->container->get('acm_sku.product_manager')
      ->synchronizeProducts($_acm_commerce_simple_products);

    $this->assertSame($result["success"], TRUE);
    // 2 nodes and 2 skus created.
    $this->assertSame($result["created"], 4);

    $nodes = $this->container->get('entity_type.manager')
      ->getStorage("node")
      ->loadByProperties(["title" => "Sac de marin Joute"]);
    // Fetch the first element of the array $nodes.
    $node = reset($nodes);
    $this->assertNotNull($node);
    $this->assertSame($node->get("title")->value, "Sac de marin Joute");
    $this->assertSame($node->get("field_skus")->value, "24-MB01");
    $nodes = NULL;
    $node = NULL;

    $nodes = $this->container->get('entity_type.manager')
      ->getStorage("node")
      ->loadByProperties(["title" => "Sac de champ"]);
    $node = reset($nodes);
    $this->assertNotNull($node);
    $this->assertSame($node->get("title")->value, "Sac de champ");
    $this->assertSame($node->get("field_skus")->value, "24-MB06");
    $nodes = NULL;
    $node = NULL;

    $skus = $this->container->get('entity_type.manager')
      ->getStorage("acm_sku")
      ->loadByProperties(["sku" => "24-MB01"]);
    // Fetch the first element of the array $skus.
    $sku = reset($skus);
    $this->assertNotNull($sku);
    $this->assertSame($sku->get("sku")->value, "24-MB01");
    $skus = NULL;
    $sku = NULL;

    $skus = $this->container->get('entity_type.manager')
      ->getStorage("acm_sku")
      ->loadByProperties(["sku" => "24-MB06"]);
    $sku = reset($skus);
    $this->assertNotNull($sku);
    $this->assertSame($sku->get("sku")->value, "24-MB06");
    $skus = NULL;
    $sku = NULL;
  }

  /**
   * Tests the synchronizeProducts method with simple product data passed.
   *
   * Here we add categories and extension attributes into the data passed in.
   *
   * @covers ::synchronizeProducts
   */
  public function testSynchronizeSimpleProductsWithMoreData() {
    global $_acm_commerce_simple_products_more_data;

    $result = $this->container->get('acm_sku.product_manager')
      ->synchronizeProducts($_acm_commerce_simple_products_more_data);
    $this->assertSame($result["success"], TRUE);
    // 2 nodes and 2 skus created.
    $this->assertSame($result["created"], 4);

    $nodes = $this->container->get('entity_type.manager')
      ->getStorage("node")
      ->loadByProperties(["title" => "Joust Duffle Bag"]);
    // Fetch the first element of the array $nodes.
    $node = reset($nodes);
    $this->assertNotNull($node);
    $this->assertSame($node->get("title")->value, "Joust Duffle Bag");
    $this->assertSame($node->get("field_skus")->value, "24-MB02");
    $nodes = NULL;
    $node = NULL;

    $nodes = $this->container->get('entity_type.manager')
      ->getStorage("node")
      ->loadByProperties(["title" => "Field Messenger"]);
    // Fetch the first element of the array $nodes.
    $node = reset($nodes);
    $this->assertNotNull($node);
    $this->assertSame($node->get("title")->value, "Field Messenger");
    $this->assertSame($node->get("field_skus")->value, "24-MB07");
    $nodes = NULL;
    $node = NULL;

    $skus = $this->container->get('entity_type.manager')
      ->getStorage("acm_sku")
      ->loadByProperties(["sku" => "24-MB02"]);
    // Fetch the first element of the array $skus.
    $sku = reset($skus);
    $this->assertNotNull($sku);
    $this->assertSame($sku->get("sku")->value, "24-MB02");
    $skus = NULL;
    $sku = NULL;

    $skus = $this->container->get('entity_type.manager')
      ->getStorage("acm_sku")
      ->loadByProperties(["sku" => "24-MB07"]);
    $sku = reset($skus);
    $this->assertNotNull($sku);
    $this->assertSame($sku->get("sku")->value, "24-MB07");
    $skus = NULL;
    $sku = NULL;
  }

}
