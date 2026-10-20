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

use Thelia\Model\Cart;

/**
 * A wallet's payment, to be turned into an order of the cart the checkout prepared.
 *
 * `totalTaxIncludedShownToTheBuyer` is the one field that is never trusted: it is what
 * the sheet displayed, and the shop compares it to what it computes itself. A mismatch
 * cancels the whole thing: a buyer who agreed to one number must not be charged another,
 * whatever the wallet claims.
 *
 * @param array<string, bool> $consentAnswers the answers the buyer gave in the checkout
 */
final readonly class ExpressCheckoutRequest
{
    public function __construct(
        public Cart $cart,
        public int $paymentModuleId,
        public float $totalTaxIncludedShownToTheBuyer,
        public array $consentAnswers = [],
    ) {
    }
}
