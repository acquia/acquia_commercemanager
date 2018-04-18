<?php

namespace Drupal\acm;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a LineItem entity.
 *
 * @ingroup acm
 */
interface LineItemInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
