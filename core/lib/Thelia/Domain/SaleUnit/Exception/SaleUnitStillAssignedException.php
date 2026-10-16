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

namespace Thelia\Domain\SaleUnit\Exception;

/**
 * A sale unit some administrators are restricted to cannot be deleted: an
 * administrator left with no unit would reach every one of them. Detach the
 * administrators first, or turn the unit off.
 */
class SaleUnitStillAssignedException extends \RuntimeException
{
    public function __construct(int $saleUnitId, int $administratorCount)
    {
        parent::__construct(\sprintf(
            'The sale unit %d cannot be deleted: %d administrator(s) are restricted to it. Detach them from it first, or turn the unit off.',
            $saleUnitId,
            $administratorCount,
        ));
    }
}
