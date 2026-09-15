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

namespace Thelia\Domain\Customer;

use Thelia\Model\Customer;

/**
 * What a guest registration ended up doing: the row the order will hang off, and
 * whether this registration opened it or found it already there.
 *
 * The distinction matters to whoever hands out credentials on the strength of it. A
 * visitor who opened the row is the only one who has ever touched it; a visitor who
 * landed on an existing row typed an address somebody else had used before, and knows
 * nothing more about that person's account than the address.
 */
final readonly class GuestRegistration
{
    public function __construct(
        public Customer $customer,
        public bool $createdTheCustomer,
    ) {
    }
}
