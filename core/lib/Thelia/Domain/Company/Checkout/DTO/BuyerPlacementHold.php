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

namespace Thelia\Domain\Company\Checkout\DTO;

/**
 * The buyer of an order that is about to be placed, as a company buyer: the account and the row
 * that binds it to its company, which the placement locks.
 */
final readonly class BuyerPlacementHold
{
    public function __construct(
        public int $customerId,
        public int $companyCustomerId,
    ) {
    }
}
