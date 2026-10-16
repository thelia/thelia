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
use Thelia\Domain\SaleUnit\Exception\SaleUnitStillAssignedException;
use Thelia\Model\Base\SaleUnit as BaseSaleUnit;

class SaleUnit extends BaseSaleUnit
{
    /**
     * An administrator restricted to this unit only would be left with no unit,
     * which reads as every unit: the deletion is refused while one is attached.
     */
    public function preDelete(?ConnectionInterface $con = null): bool
    {
        $administratorCount = AdminSaleUnitQuery::create()
            ->filterBySaleUnitId((int) $this->getId())
            ->count($con);

        if ($administratorCount > 0) {
            throw new SaleUnitStillAssignedException((int) $this->getId(), $administratorCount);
        }

        return parent::preDelete($con);
    }
}
