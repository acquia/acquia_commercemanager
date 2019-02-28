<?php

namespace Drupal\acm_sku\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\acm_sku\Entity\SKUTypeInterface;

/**
 * Class SKUController.
 *
 * @package Drupal\acm_sku\Controller
 */
class SKUController extends ControllerBase {

  /**
   * Provides the SKU submission form.
   *
   * @param \Drupal\acm_sku\Entity\SKUTypeInterface $acm_sku_type
   *   The SKU type entity for the SKU.
   *
   * @return array
   *   A SKU submission form.
   */
  public function add(SKUTypeInterface $acm_sku_type) {
    $sku = $this->entityManager()
      ->getStorage('acm_sku')
      ->create(['type' => $acm_sku_type->id()]);

    $form = $this->entityFormBuilder()->getForm($sku);

    return $form;
  }

  /**
   * The _title_callback for the acm_sku.sku_add route.
   *
   * @param \Drupal\acm_sku\Entity\SKUTypeInterface $acm_sku_type
   *   The current SKU type.
   *
   * @return string
   *   The page title.
   */
  public function addPageTitle(SKUTypeInterface $acm_sku_type) {
    return $this->t('Create @name', ['@name' => $acm_sku_type->label()]);
  }

  /**
   * Displays add SKU links for available SKU types.
   *
   * Redirects to /admin/commerce/sku/add/[type] if only one content type is
   * available.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A render array for a list of the SKU types that can be added; however,
   *   if there is only one SKU type defined for the site, the function
   *   will return a RedirectResponse to the SKU add page for that one SKU
   *   type.
   */
  public function addPage() {
    $build = [
      '#theme' => 'sku_add_list',
      '#cache' => [
        'tags' => $this->entityManager()->getDefinition('acm_sku_type')->getListCacheTags(),
      ],
    ];

    $content = [];

    foreach ($this->entityManager()->getStorage('acm_sku_type')->loadMultiple() as $type) {
      $content[$type->id()] = $type;
    }

    // Bypass the sku/add listing if only one content type is available.
    if (count($content) == 1) {
      $type = array_shift($content);
      return $this->redirect('acm_sku.sku_add', ['acm_sku_type' => $type->id()]);
    }

    $build['#content'] = $content;

    return $build;
  }

}
