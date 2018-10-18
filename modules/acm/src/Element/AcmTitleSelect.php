<?php

namespace Drupal\acm\Element;

use Drupal\Core\Render\Element\Select;

/**
 * Provides an ACM Title select form element.
 *
 * @FormElement("acm_title_select")
 */
class AcmTitleSelect extends Select {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    $info = parent::getInfo();
    // Added first so options are available before other process callbacks.
    array_unshift($info['#process'], [$class, 'processAcmTitleSelect']);
    return $info;
  }

  /**
   * Adds in an option list of applicable titles.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #options, #description, #required,
   *   #attributes, #id, #name, #type, #min, #max, #step, #value, #size. The
   *   #name property will be sanitized before output. This is currently done by
   *   initializing Drupal\Core\Template\Attribute with all the attributes.
   *
   * @return array
   *   The $element with prepared variables.
   */
  public static function processAcmTitleSelect(array $element) {
    $element['#options'] = [
      'Mr' => t('Mr'),
      'Mrs' => t('Mrs'),
      'Ms' => t('Ms'),
      'Miss' => t('Miss'),
      'Dr' => t('Dr'),
      'Prof.' => t('Prof.'),
      'Brigadier' => t('Brigadier'),
      'Reverend' => t('Reverend'),
    ];
    return $element;
  }

}
