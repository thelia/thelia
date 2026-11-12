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

namespace Thelia\Domain\Pricing\PriceList;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A price list that is on, inside its period and assigned to the customer or to their group,
 * in the currency asked, or exclusive in any currency; $priority is its rank (the smallest first).
 */
#[Exclude]
final readonly class ApplicablePriceList
{
    public function __construct(
        public int $id,
        public string $code,
        public bool $exclusive,
        public int $priority,
        public bool $assignedToCustomer,
        public int $currencyId,
    ) {
    }
}
