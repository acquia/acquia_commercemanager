<?php

namespace Drupal\acm_customer\Plugin\rest\resource;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\UserInterface;

/**
 * Class CustomerDeleteResource.
 *
 * @package Drupal\acm_customer\Plugin
 *
 * @ingroup acm_customer
 *
 * @RestResource(
 *   id = "acm_customer_delete",
 *   label = @Translation("Acquia Commerce Customer Delete"),
 *   uri_paths = {
 *     "canonical" = "/customer/delete",
 *     "https://www.drupal.org/link-relations/create" = "/customer/delete"
 *   }
 * )
 */
class CustomerDeleteResource extends ResourceBase {

  /**
   * Post.
   *
   * Handle Connector deleting a customer.
   *
   * @param array $data
   *   Post data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   HTTP Response.
   */
  public function post(array $data) {
    // If 'email' key is not available.
    if (!isset($data['email'])) {
      $this->logger->error('Invalid data to delete customer.');
      $response['success'] = (bool) (FALSE);
      return (new ModifiedResourceResponse($response));
    }

    $email = $data['email'];
    $user = $this->myUserLoadByMail($email);

    // If there is user with given email.
    if ($user instanceof UserInterface) {
      try {
        $user->delete();

        $this->logger->info('Deleted user with uid %id and email %email.', [
          '%id' => $user->id(),
          '%email' => $email,
        ]);
      }
      catch (EntityStorageException $e) {
        $this->logger->error('Failed to delete user with email %email. Exception: @message', [
          '%email' => $email,
          '@message' => $e->getMessage(),
        ]);
      }
    }
    else {
      $this->logger->warning('User with email %email does not exist.', ['%email' => $email]);
    }

    // For exception or missing user we have added entries in logs.
    // We don't want ACM to try again if message is processed successfully.
    $response['success'] = TRUE;
    return (new ModifiedResourceResponse($response));
  }

  /**
   * Wrapper around user_load_by_mail to allow for stubbing during testing.
   *
   * @return \Drupal\user\UserInterface|null
   *   User entity object or null.
   */
  public function myUserLoadByMail($email) {
    return user_load_by_mail($email);
  }

}
