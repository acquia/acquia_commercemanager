<?php

namespace Drupal\acm\Response;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Provides an exception that represents the need for an HTTP redirect.
 *
 * Allows nested forms to perform HTTP redirects in an easy way.
 */
class NeedsRedirectException extends EnforcedResponseException {

  /**
   * The URL being redirected to.
   *
   * @var string
   */
  protected $redirectUrl;

  /**
   * Constructs a new NeedsRedirectException object.
   *
   * @param string $url
   *   The URL to redirect to.
   * @param int $status_code
   *   The redirect status code.
   * @param string[] $headers
   *   Headers to pass with the redirect.
   */
  public function __construct($url, $status_code = 302, array $headers = []) {
    if (!UrlHelper::isValid($url)) {
      throw new \InvalidArgumentException('Invalid URL provided.');
    }

    $this->redirectUrl = $url;
    $response = new TrustedRedirectResponse($url, $status_code, $headers);
    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->setCacheMaxAge(0);
    $response->addCacheableDependency($cacheable_metadata);
    parent::__construct($response);
  }

  /**
   * Gets the URL that is being redirected to.
   *
   * @return string
   *   The URL.
   */
  public function getRedirectUrl() {
    return $this->redirectUrl;
  }

}
