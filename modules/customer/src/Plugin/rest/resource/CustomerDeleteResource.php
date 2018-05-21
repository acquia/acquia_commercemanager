<?php

namespace Drupal\acm_customer\Plugin\rest\resource;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;

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
   * @return \Drupal\rest\ResourceResponse
   *   HTTP Response.
   */
  public function post(array $data = []) {
    // If 'email' key is not available.
    if (!$data['email']) {
      $this->logger->error('Invalid data to delete customer.');
      $response['success'] = (bool) (FALSE);
      return (new ResourceResponse($response));
    }

    $email = $data['email'];
    /* @var \Drupal\user\Entity\User $user */
    $user = $this->myUserLoadByMail($email);
    // If there is user with given email.
    if ($user) {
      try {
        $user->delete();
        $this->logger->notice('Deleted user with uid %id and email %email.', ['%id' => $user->id(), '%email' => $email]);
        $response['success'] = (bool) (TRUE);
        return (new ResourceResponse($response));
      }
      catch (EntityStorageException $e) {
        $this->logger->error($e->getMessage());
        $response['success'] = (bool) (FALSE);
        return (new ResourceResponse($response));
      }
    }
    else {
      $this->logger->warning('User with email %email doesn\'t exist.', ['%email' => $email]);
      $response['success'] = (bool) (FALSE);
      return (new ResourceResponse($response));
    }
  }

  /**
   * Wrapper around user_load_by_mail to allow for stubbing during testing.
   */
  public function myUserLoadByMail($email) {
    return user_load_by_mail($email);
  }

}
