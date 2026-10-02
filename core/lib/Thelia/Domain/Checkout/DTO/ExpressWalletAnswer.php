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

namespace Thelia\Domain\Checkout\DTO;

/**
 * What a module read off its wallet's confirmation, once it trusts it.
 *
 * The sheet opens in the checkout, once the buyer is identified and has chosen the
 * addresses and the carrier, and it only pays. So the answer carries the total the sheet
 * showed and nothing about the cart or the buyer: the shop already holds those, and a
 * module that could name them would be a module able to order someone else's cart.
 *
 * @param array<string, bool> $consentAnswers the answers the buyer gave in the checkout
 */
final readonly class ExpressWalletAnswer
{
    public function __construct(
        public float $totalTaxIncludedShownToTheBuyer,
        public array $consentAnswers = [],
    ) {
    }
}
