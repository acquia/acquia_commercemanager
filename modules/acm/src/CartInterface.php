<?php

namespace Drupal\acm;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Cart entity.
 *
 * @ingroup acm
 */
interface CartInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
