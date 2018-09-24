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
  }

  /**
   * Tests the synchronizeProducts method with no data passed.
   *
   * @covers ::synchronizeProducts
   */
  public function testSynchronizeNoProducts() {
    $result = $this->container->get('acm_sku.product_manager')
      ->synchronizeProducts();
    $this->assertSame($result['success'], FALSE);
  }

  /**
   * Tests the synchronizeProducts method with simple product data passed.
   *
   * @covers ::synchronizeProducts
   */
  public function testSynchronizeSimpleProducts() {
    $simpleProducts = [
      [
        "product_id" => 1,
        "store_id" => 1,
        "attribute_set_id" => 15,
        "attribute_set_label" => "",
        "sku" => "24-MB01",
        "name" => "Sac de marin Joute",
        "type" => "simple",
        "price" => "59.88",
        "special_price" => "",
        "final_price" => "",
        "msrp" => "",
        "status" => 1,
        "visibility" => 1,
        "attributes" => [
          "category_ids" => "",
          "color" => "49",
          "description" => "<p> Le sac de sport Joust Duffle Bag ne peut pas être battu - pas dans le gymnase, pas sur le carrousel à bagages, pas n'importe où ... Assez grand pour transporter un ballon de basket ou de football et des espadrilles avec beaucoup d'espace, c'est idéal pour les athlètes avec des endroits où aller. <p>\n<ul>\n<li> Deux poignées supérieures. </li>\n<li> Bandoulière réglable. </li>\n<li> Fermeture à glissière pleine longueur. </li>\n<li> L 29 \" x W 13 \" x H 11 \". </li>\n</ul> ",
          "eco_collection" => "0",
          "erin_recommends" => "0",
          "gift_message_available" => "0",
          "gift_wrapping_available" => "0",
          "has_options" => "0",
          "image" => "/m/b/mb01-blue-0.jpg",
          "is_returnable" => "0",
          "msrp_display_actual_price_type" => "0",
          "new" => "0",
          "options_container" => "container1",
          "performance_fabric" => "0",
          "required_options" => "0",
          "sale" => "0",
          "short_description" => "Le sac de sport Joust Duffle Bag ne peut pas être battu - pas dans le gymnase, pas sur le carrousel à bagages",
          "small_image" => "/m/b/mb01-blue-0.jpg",
          "tax_class_id" => "0",
          "thumbnail" => "/m/b/mb01-blue-0.jpg",
          "url_key" => "sac-de-marin-joute",
        ],
        "linked" => [],
        "categories" => NULL,
        "extension" => [],
      ],
      [
        "product_id" => 5,
        "store_id" => 1,
        "attribute_set_id" => 15,
        "attribute_set_label" => "",
        "sku" => "24-MB06",
        "name" => "Sac de champ",
        "type" => "simple",
        "price" => "48.12",
        "special_price" => "",
        "final_price" => "",
        "msrp" => "",
        "status" => 1,
        "visibility" => 1,
        "attributes" => [
          "activity" => "22,23",
          "category_ids" => "",
          "color" => "49",
          "description" => "<p> Le Rival Field Messenger réunit tous vos essentiels du campus, du studio ou de la piste dans un design unique de cuir souple et texturé, avec deux poches extérieures qui gardent tous vos objets plus petits à portée de main. plus d'espace. </p>\n<ul>\n<li> Construction en cuir. </li>\n<li> Sangle de transport en tissu réglable. </li>\n<li> Dimensions: 18 \" x 10 \" x 4 \". </li>\n</ul> ",
          "eco_collection" => "0",
          "erin_recommends" => "0",
          "features_bags" => "73,75,78",
          "gift_message_available" => "0",
          "gift_wrapping_available" => "0",
          "has_options" => "0",
          "image" => "/m/b/mb06-gray-0.jpg",
          "is_returnable" => "0",
          "material" => "35,37,41",
          "msrp_display_actual_price_type" => "0",
          "new" => "1",
          "options_container" => "container2",
          "performance_fabric" => "0",
          "required_options" => "0",
          "sale" => "0",
          "short_description" => "Le Rival Field Messenger réunit tous vos essentiels du campus, du studio ou de la piste dans un design unique de cuir souple et texturé",
          "small_image" => "/m/b/mb06-gray-0.jpg",
          "strap_bags" => "61,62,66,67",
          "style_bags" => "27,28,29",
          "tax_class_id" => "2",
          "thumbnail" => "/m/b/mb06-gray-0.jpg",
          "url_key" => "sac-de-champ",
        ],
        "linked" => [],
        "categories" => NULL,
        "extension" => [],
      ],
    ];
    $result = $this->container->get('acm_sku.product_manager')
      ->synchronizeProducts($simpleProducts);

    $this->assertSame($result["success"], TRUE);
    // 2 skus created.
    $this->assertSame($result["created"], 2);

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
    $simpleProductsMoreData = [
      [
        "product_id" => 1,
        "store_id" => 0,
        "attribute_set_id" => 15,
        "attribute_set_label" => "",
        "sku" => "24-MB02",
        "name" => "Joust Duffle Bag",
        "type" => "simple",
        "price" => "59.88",
        "special_price" => "",
        "final_price" => "",
        "msrp" => "",
        "status" => 1,
        "visibility" => 1,
        "attributes" => [
          "category_ids" => "1",
          "color" => "49",
          "description" => "<p> Le sac de sport Joust Duffle Bag ne peut pas être battu - pas dans le gymnase, pas sur le carrousel à bagages, pas n'importe où ... Assez grand pour transporter un ballon de basket ou de football et des espadrilles avec beaucoup d'espace, c'est idéal pour les athlètes avec des endroits où aller. <p>\n<ul>\n<li> Deux poignées supérieures. </ li>\n<li> Bandoulière réglable. </ li>\n<li> Fermeture à glissière pleine longueur. </ li>\n<li> L 29 \" x W 13 \" x H 11 \". </ li>\n</ ul> ",
          "eco_collection" => "0",
          "erin_recommends" => "0",
          "gift_message_available" => "0",
          "gift_wrapping_available" => "0",
          "has_options" => "0",
          "image" => "/m/b/mb01-blue-0.jpg",
          "is_returnable" => "0",
          "msrp_display_actual_price_type" => "0",
          "new" => "0",
          "options_container" => "container1",
          "performance_fabric" => "0",
          "required_options" => "0",
          "sale" => "0",
          "short_description" => "Le sac de sport Joust Duffle Bag ne peut pas être battu - pas dans le gymnase, pas sur le carrousel à bagages",
          "small_image" => "/m/b/mb01-blue-0.jpg",
          "tax_class_id" => "0",
          "thumbnail" => "/m/b/mb01-blue-0.jpg",
          "url_key" => "sac-de-marin-joute",
        ],
        "linked" => [
          [
            "linked_sku" => "24-MB02",
            "linked_type" => "simple",
            "type" => "related",
            "main_sku" => "24-MB01",
            "position" => 2,
            "extension" => [
              "one_thing" => ["white", "black", "red"],
              "another_thing" => "Ships from Quatamala",
            ],
          ],
        ],
        "categories" => ["1"],
        "extension" => [
          "next_thing" => ["teak", "cherry", "rose"],
          "subsequent_thing" => "Discontinued after March 2019",
        ],
      ],
      [
        "product_id" => 5,
        "store_id" => 0,
        "attribute_set_id" => 15,
        "attribute_set_label" => "",
        "sku" => "24-MB07",
        "name" => "Field Messenger",
        "type" => "simple",
        "price" => "48.12",
        "special_price" => "",
        "final_price" => "",
        "msrp" => "",
        "status" => 1,
        "visibility" => 1,
        "attributes" => [
          "activity" => "22,23",
          "category_ids" => "1",
          "color" => "49",
          "description" => "<p> Le Rival Field Messenger réunit tous vos essentiels du campus, du studio ou de la piste dans un design unique de cuir souple et texturé, avec deux poches extérieures qui gardent tous vos objets plus petits à portée de main. plus d'espace. </ p>\n<ul>\n<li> Construction en cuir. </ li>\n<li> Sangle de transport en tissu réglable. </ li>\n<li> Dimensions: 18 \" x 10 \" x 4 \". </ li>\n</ ul> ",
          "eco_collection" => "0",
          "erin_recommends" => "0",
          "features_bags" => "73,75,78",
          "gift_message_available" => "0",
          "gift_wrapping_available" => "0",
          "has_options" => "0",
          "image" => "/m/b/mb06-gray-0.jpg",
          "is_returnable" => "0",
          "material" => "35,37,41",
          "msrp_display_actual_price_type" => "0",
          "new" => "1",
          "options_container" => "container2",
          "performance_fabric" => "0",
          "required_options" => "0",
          "sale" => "0",
          "short_description" => "Le Rival Field Messenger réunit tous vos essentiels du campus, du studio ou de la piste dans un design unique de cuir souple et texturé",
          "small_image" => "/m/b/mb06-gray-0.jpg",
          "strap_bags" => "61,62,66,67",
          "style_bags" => "27,28,29",
          "tax_class_id" => "2",
          "thumbnail" => "/m/b/mb06-gray-0.jpg",
          "url_key" => "sac-de-champ",
        ],
        "linked" => [
          [
            "linked_sku" => "24-MB07",
            "linked_type" => "simple",
            "type" => "related",
            "main_sku" => "24-MB06",
            "position" => 2,
            "extension" => [
              "one_thing" => ["yellow", "blue", "tope"],
              "another_thing" => "Ships from Quatamala",
            ],
          ],
          [
            "linked_sku" => "24-MB07",
            "linked_type" => "simple",
            "type" => "upsell",
            "main_sku" => "24-MB06",
            "position" => 2,
            "extension" => [
              "one_thing" => ["oberon", 1 => "julep", 2 => "sophit"],
              "another_thing" => "Only between May and September",
            ],
          ],
        ],
        "categories" => ["1"],
        "extension" => [
          "next_thing" => ["teak", "cherry", "rose"],
          "subsequent_thing" => "Ships in pairs only",
        ],
      ],
    ];
    $result = $this->container->get('acm_sku.product_manager')
      ->synchronizeProducts($simpleProductsMoreData);
    $this->assertSame($result["success"], TRUE);
    // 2 skus created.
    $this->assertSame($result["created"], 2);

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
