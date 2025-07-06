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
use Thelia\Model\Base\Accessory as BaseAccessory;
use Thelia\Model\Tools\PositionManagementTrait;

class Accessory extends BaseAccessory
{
    use PositionManagementTrait;

    /**
     * Calculate next position relative to our product.
     */
    protected function addCriteriaToPositionQuery($query): void
    {
        $query->filterByProductId($this->getProductId());
    }

    public function preInsert(?ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        parent::preInsert($con);

        return true;
    }
}
