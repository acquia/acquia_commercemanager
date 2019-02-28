<?php

namespace Drupal\acm_customer\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

/**
 * Class AcmCustomerDrushCommands.
 *
 * @package Drupal\acm_customer\Commands
 */
class AcmCustomerDrushCommands extends DrushCommands {

  const BATCH_SIZE = 20;

  /**
   * Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * AcmCustomerDrushCommands constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database Connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger Channel Factory.
   */
  public function __construct(Connection $connection,
                              LoggerChannelFactoryInterface $logger_factory) {
    $this->connection = $connection;
    $this->setLogger($logger_factory->get('AcmCustomerDrushCommands'));
  }

  /**
   * Push all the customers available in Drupal to upstream system.
   *
   * @command acm_customer:push-customers
   *
   * @validate-module-enabled acm_customer
   *
   * @aliases push-customers
   *
   * @usage drush push-customers
   *   Push all the customers available in Drupal to upstream system.
   */
  public function pushCustomersData() {
    $query = $this->connection->select('users_field_data', 'user');
    $query->addField('user', 'mail');
    $query->condition('status', 0, '>');
    $query->condition('acm_customer_id', 0, '>');
    $result = $query->countQuery()->execute()->fetchAssoc();

    if ($result['expression'] == 0) {
      $this->logger->warning('No customers with active status available to push.');
      return;
    }

    $question = dt('There are @count customers as of now, are you sure you want to push for all?', [
      '@count' => $result['expression'],
    ]);

    if (!$this->confirm($question)) {
      throw new UserAbortException();
    }

    $query = $this->connection->select('users_field_data', 'user');
    $query->addField('user', 'mail');
    $query->condition('status', 0, '>');
    $query->condition('acm_customer_id', 0, '>');
    $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $customers = array_column($result, 'mail');

    $batch = [
      'title' => 'Push Customers Data',
      'init_message' => 'Pushing customers data...',
      'error_message' => 'Error occurred while pushing customers, please check logs.',
    ];

    foreach (array_chunk($customers, self::BATCH_SIZE) as $customers_batch) {
      $batch['operations'][] = [
        [__CLASS__, 'pushCustomerDataInBatch'],
        [$customers_batch],
      ];
    }

    batch_set($batch);
    drush_backend_batch_process();

    $this->logger()->info('Finished pushing customers data, please check logs for more details.');
  }

  /**
   * Push specific customer available in Drupal to upstream system.
   *
   * @param string $mail
   *   E-Mail address of the customer to push.
   *
   * @command acm_customer:push-customer
   *
   * @validate-module-enabled acm_customer
   *
   * @aliases push-customer
   *
   * @usage drush push-customer abc@xyz.com
   *   Push specific customer available in Drupal to upstream system.
   */
  public function pushCustomerData(string $mail) {
    self::pushCustomerDataToUpstream($mail);
    $this->logger()->info('Pushed customer data for @mail, please check logs for more details.', [
      '@mail' => $mail,
    ]);
  }

  /**
   * Batch callback to push customers data in batches.
   *
   * @param array $customers
   *   Array containing customer mails to push.
   * @param mixed $context
   *   Batch context.
   */
  public static function pushCustomerDataInBatch(array $customers, &$context) {
    foreach ($customers as $customer) {
      self::pushCustomerDataToUpstream($customer);
    }
  }

  /**
   * Wrapper function to push data for particular customer.
   *
   * @param string $mail
   *   E-Mail address of the customer to push.
   */
  public static function pushCustomerDataToUpstream(string $mail) {
    $logger = \Drupal::logger('AcmCustomerDrushCommands');

    /** @var \Drupal\user\Entity\User $user */
    $user = user_load_by_mail($mail);

    if (empty($user)) {
      $logger->warning('User with @mail not found, skipping.', [
        '@mail' => $mail,
      ]);
    }
    elseif (empty($user->get('acm_customer_id')->getString())) {
      $logger->warning('User with @mail does not have customer id, skipping.', [
        '@mail' => $mail,
      ]);
    }

    $customer_array = [
      'customer_id' => $user->get('acm_customer_id')->getString(),
      'firstname' => $user->get('field_first_name')->getString(),
      'lastname' => $user->get('field_last_name')->getString(),
      'email' => $user->getEmail(),
    ];

    /** @var \Drupal\acm\Connector\APIWrapper $api_wrapper */
    $api_wrapper = \Drupal::service('acm.api');

    try {
      if ($api_wrapper->updateCustomer($customer_array)) {
        $logger->info('Successfully pushed for user with @mail.', [
          '@mail' => $mail,
        ]);
      }
      else {
        $logger->warning('Something went wrong while pushing user with @mail.', [
          '@mail' => $mail,
        ]);
      }
    }
    catch (\Exception $e) {
      $logger->error('Failed to push for user with @mail, message: @message.', [
        '@mail' => $mail,
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
