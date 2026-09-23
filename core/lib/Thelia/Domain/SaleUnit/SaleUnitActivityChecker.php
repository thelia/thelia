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

namespace Thelia\Domain\SaleUnit;

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\SaleUnitQuery;

/**
 * Whether this shop sells from more than one place at all, so that a shop with
 * no active sale unit keeps the code paths, the query plans and the caches it
 * had before the feature existed.
 *
 * One indexed existence check, asked of the database once per request.
 */
class SaleUnitActivityChecker implements ResetInterface
{
    private ?bool $hasActiveSaleUnit = null;

    public function isMultiSaleUnitMode(): bool
    {
        return $this->hasActiveSaleUnit ??= SaleUnitQuery::create()
            ->filterByActive(true)
            ->exists();
    }

    public function reset(): void
    {
        $this->hasActiveSaleUnit = null;
    }
}
