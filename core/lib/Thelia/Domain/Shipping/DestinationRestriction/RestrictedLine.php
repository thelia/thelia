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

/**
 * A product of the cart its destination refuses, with the reason its group gives. It
 * never carries the destinations of the group: a refusal names what the buyer holds,
 * not the map of what the shop does not ship.
 */
final readonly class RestrictedLine
{
    public function __construct(
        public int $productId,
        public string $productTitle,
        public string $reason,
    ) {
    }

    /**
     * The product as a refusal names it: its title, then the reason of its group.
     */
    public function label(): string
    {
        return '' === $this->reason ? $this->productTitle : \sprintf('%s (%s)', $this->productTitle, $this->reason);
    }
}
