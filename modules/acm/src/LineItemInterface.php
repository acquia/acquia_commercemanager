<?php

namespace Drupal\acm;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a LineItem entity.
 *
 * @ingroup acm
 */
interface LineItemInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
