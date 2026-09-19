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

namespace Thelia\Domain\Pricing\Rule\Engine;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The price a sale element has between two instants, and the rule responsible.
 *
 * A null start means "since the rules were computed", a null end means "until a rule
 * is changed or turned off": both bounds are open, and the end is excluded.
 */
#[Exclude]
final readonly class PriceSegment
{
    public function __construct(
        public ?\DateTimeInterface $validFrom,
        public ?\DateTimeInterface $validUntil,
        public ChainResult $result,
    ) {
    }

    public function coversAt(\DateTimeInterface $now): bool
    {
        if (null !== $this->validFrom && $this->validFrom > $now) {
            return false;
        }

        return null === $this->validUntil || $this->validUntil > $now;
    }

    public function isOverAt(\DateTimeInterface $now): bool
    {
        return null !== $this->validUntil && $this->validUntil <= $now;
    }
}
