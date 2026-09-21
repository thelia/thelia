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

namespace Thelia\Domain\Checkout\Exception;

use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;

/**
 * A gift wrapping was asked for that this shop does not offer.
 *
 * Only the identifier of a wrapping ever reaches the server, never its price, so this is
 * the single place an unusable choice is caught: a wrapping the merchant turned off
 * between the moment the page was rendered and the moment the buyer clicked, one deleted
 * outright, or an identifier that was never this shop's.
 */
class UnknownGiftWrappingException extends CheckoutException
{
    public function __construct(
        public readonly int $giftWrappingId,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'This shop does not offer the gift wrapping you asked for.',
            $code,
            $previous,
        );
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::GiftWrappingUnknown->value;
    }

    public function violationDetails(): array
    {
        return ['giftWrappingId' => $this->giftWrappingId];
    }
}
