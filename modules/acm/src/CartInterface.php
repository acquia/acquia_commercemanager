<?php

namespace Drupal\acm;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Cart entity.
 *
 * @ingroup acm
 */
interface CartInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
