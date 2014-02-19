<?php

namespace Colissimo\Model;

use Colissimo\Model\Base\ColissimoFreeshippingQuery as BaseColissimoFreeshippingQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'colissimo_freeshipping' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class ColissimoFreeshippingQuery extends BaseColissimoFreeshippingQuery
{
    public function getLast() {
        return $this->orderById('desc')->findOne()->getActive();
    }
} // ColissimoFreeshippingQuery
