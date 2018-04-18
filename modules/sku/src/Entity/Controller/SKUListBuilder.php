<?php

namespace Drupal\acm_sku\Entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for acm_sku entity.
 *
 * @ingroup acm_sku
 */
class SKUListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['table'] = parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['sku'] = $this->t('SKU');
    $header['title'] = $this->t('Name');
    $header['type'] = $this->t('Type');
    $header['price'] = $this->t('Price');

    if (\Drupal::languageManager()->isMultilingual()) {
      $header['language_name'] = [
        'data' => $this->t('Language'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ];
    }

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\acm_sku\Entity\SKUInterface $entity */
    $row['id'] = $entity->id();
    $row['sku'] = $entity->getSKU();
    $row['title'] = $entity->toLink();
    $row['type'] = $entity->bundle();

    $language_manager = \Drupal::languageManager();
    if ($language_manager->isMultilingual()) {
      $langcode = $entity->language()->getId();
      $row['language_name'] = $language_manager->getLanguageName($langcode);
    }

    $row['price'] = $entity->getAdminGridDisplayFormattedPrice();

    return $row + parent::buildRow($entity);
  }

}
