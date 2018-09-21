<?php

namespace Drupal\acm\Plugin\CommerceDashboardItem;

use Drupal\acm\CommerceDashboardItemBase;
use Drupal\acm\Connector\APIWrapperInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class QueueStatus.
 *
 * @CommerceDashboardItem(
 *   id = "queue_status",
 *   title = @Translation("Queue status"),
 *   weight = -100,
 *   group = "tile",
 * )
 */
class QueueStatus extends CommerceDashboardItemBase {

  /**
   * Purge form ID.
   */
  const FORM_ID = '\Drupal\acm\Form\PurgeQueueForm';

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * API Wrapper.
   *
   * @var \Drupal\acm\Connector\APIWrapperInterface
   */
  protected $api;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, APIWrapperInterface $api, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->api = $api;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('acm.api'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $form = [];
    try {
      $form = $this->formBuilder->getForm(self::FORM_ID);
    }
    catch (EnforcedResponseException $e) {
      $form = $this->formBuilder->getForm(self::FORM_ID);
    }
    catch (\Exception $e) {
      \Drupal::logger('acm')->error($e->getCode() . ' ' . $e->getMessage());
    }
    try {
      $number = $this->api->getQueueStatus();
      return [
        '#theme' => "dashboard_item__" . $this->pluginDefinition['group'],
        '#title' => $this->title(),
        '#value' => [
          'text' => [
            '#markup' => "<div class='heading-a'>" . $number . "</div><span>items</span>",
          ],
          'form' => $form,
        ],
        '#attributes' => [
          'class' => ['text-align-center'],
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        '#theme' => "dashboard_item__" . $this->pluginDefinition['group'],
        '#title' => $this->title(),
        '#value' => [
          '#markup' => $e->getMessage(),
        ],
      ];
    }
  }

}
