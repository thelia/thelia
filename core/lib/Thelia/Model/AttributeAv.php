<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\AttributeAv as BaseAttributeAv;
use Thelia\Model\Tools\PositionManagementTrait;

class AttributeAv extends BaseAttributeAv
{
    use PositionManagementTrait;

    /**
     * when dealing with position, be sure to work insite the current attribute.
     */
    protected function addCriteriaToPositionQuery($query): void
    {
        $query->filterByAttributeId($this->getAttributeId());
    }

    public function preInsert(?ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        return true;
    }
}
