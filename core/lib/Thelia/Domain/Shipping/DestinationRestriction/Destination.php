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

namespace Thelia\Domain\Shipping\DestinationRestriction;

use Thelia\Model\Address;
use Thelia\Model\CartAddress;

/**
 * Where a line of the cart goes: a country, and the region inside it when the
 * country has regions.
 */
final readonly class Destination
{
    public function __construct(
        public int $countryId,
        public ?int $stateId = null,
    ) {
    }

    public static function of(Address|CartAddress $address): self
    {
        return new self((int) $address->getCountryId(), $address->getStateId() ? (int) $address->getStateId() : null);
    }
}
