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

namespace Thelia\Domain\Pricing\Rule\Preview;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One sale element of the sample, priced before and after the rule under review.
 */
#[Exclude]
final readonly class PreviewLine
{
    public function __construct(
        public int $productSaleElementsId,
        public int $productId,
        public string $reference,
        public float $untaxedBefore,
        public float $taxedBefore,
        public float $untaxedAfter,
        public float $taxedAfter,
        public bool $changedByTheRule,
    ) {
    }
}
