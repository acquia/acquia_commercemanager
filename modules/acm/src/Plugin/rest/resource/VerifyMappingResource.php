<?php

namespace Drupal\acm\Plugin\rest\resource;

use Drupal\acm\VerifyMappingInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ConnectorProductResource.
 *
 * @package Drupal\acm\Plugin
 *
 * @ingroup acm
 *
 * @RestResource(
 *   id = "verify_mapping",
 *   label = @Translation("Acquia Commerce Verify Mapping"),
 *   uri_paths = {
 *     "canonical" = "/acquia/verify"
 *   }
 * )
 */
class VerifyMappingResource extends ResourceBase {

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $currentRequest;

  /**
   * The class used to do the verification.
   *
   * @var \Drupal\acm\VerifyMappingInterface
   */
  private $verifyMapping;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\acm\VerifyMappingInterface $verify_mapping
   *   A Verify Mapping instance.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    // Inject the model that will do the verification.
    VerifyMappingInterface $verify_mapping,
    Request $current_request
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );
    $this->verifyMapping = $verify_mapping;
    $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('acm'),
      // Use the container to get the class (or 'service' maybe) like this:
      $container->get('acm.verify_mapping'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Get.
   *
   * Handle Connector GET some verification of the mapping.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Uncached HTTP Response object.
   */
  public function get() {
    $storeId = '';
    $requestHeaders = $this->currentRequest->headers;
    if ($requestHeaders->has('X-ACM-UUID')) {
      $storeId = $requestHeaders->get('X-ACM-UUID');
    }

    \Drupal::logger('acm')->info("Verifying mapping for acm_uuid " . $storeId . ".");

    // Return an array from the model that does the verification.
    $response = $this->verifyMapping->verify($storeId);

    // Drupal's ModifiedResourceResponse is for non-cached content.
    // We don't want to cache the verification.
    return (new ModifiedResourceResponse($response));
  }

}
