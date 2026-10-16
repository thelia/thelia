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

namespace Thelia\Domain\Payment\DTO;

/**
 * What an instant payment module says it applied to a cart.
 */
final readonly class InstantShare
{
    public function __construct(
        public int $moduleId,
        public string $amountApplied, // decimal string as returned by getAmountAppliedToCart()
    ) {
    }
}
