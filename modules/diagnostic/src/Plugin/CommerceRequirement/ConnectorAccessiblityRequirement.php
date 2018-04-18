<?php

namespace Drupal\acm_diagnostic\Plugin\CommerceRequirement;

use Acquia\Hmac\Exception\MalformedRequestException;
use Drupal\acm\Connector\APIWrapper;
use Drupal\acm_diagnostic\CommerceRequirementBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConnectorAccessiblityRequirement.
 *
 * @CommerceRequirement(
 *   id = "connector_accessibility",
 *   title = @Translation("Connector Accessibility"),
 * )
 */
class ConnectorAccessiblityRequirement extends CommerceRequirementBase {

  /**
   * API Wrapper instance.
   *
   * @var \Drupal\acm\Connector\APIWrapper
   */
  private $api;

  /**
   * Construct the connector accessibility requirement check.
   *
   * @param array $configuration
   *   The configuration for the plugin.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin defintion.
   * @param \Drupal\acm\Connector\APIWrapper $api
   *   The APIWrapper for connecting to the connector.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, APIWrapper $api) {
    $this->api = $api;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('acm.api')
    );
  }

  /**
   * Verify that the expected functionality is working correctly.
   *
   * @return bool
   *   The status of the command.
   */
  public function verify() {

    try {
      $this->api->getCustomer('me+l5@nik4u.com');
    }
    catch (MalformedRequestException $exception) {
      $this->setValue('Unable to verify the connector');
      $this->setDescription($this->t("Unable to verify connector with message: %s", [
        '%s' => $exception->getMessage(),
      ]));
      return REQUIREMENT_WARNING;
    }
    catch (\Exception $exception) {
      $this->setValue('Connection to the connector could not be established');
      $this->setDescription($this->t('Connector is unavailable with message: %s', [
        '%s' => $exception->getMessage(),
      ]));
      return REQUIREMENT_ERROR;
    }

    return REQUIREMENT_OK;
  }

}
