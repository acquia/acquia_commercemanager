<?php

namespace Drupal\acm;

/**
 * Provides an extension for rendering addresses.
 */
class ACMAddressTwigExtension extends \Twig_Extension {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'acm_address';
  }

  /**
   * In this function we can declare the extension function.
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('acm_format_address',
        [$this, 'formatAddress'],
        ['is_safe' => ['html']]
      ),
    ];
  }

  /**
   * Formats an address.
   *
   * @param object|array $address
   *   The address to format.
   *
   * @return string
   *   The formatted address.
   */
  public function formatAddress($address) {
    $address_formatter = new ACMAddressFormatter();
    return $address_formatter->render((object) $address);
  }

}
