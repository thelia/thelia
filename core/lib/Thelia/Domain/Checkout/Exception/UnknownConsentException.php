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
 * An answer was given to a consent this shop is not asking for.
 *
 * Refused rather than ignored: a caller stating an agreement to something the shop does
 * not know about is a caller working from a list of consents that is no longer the
 * shop's — turned off, deleted, or copied from another environment — and recording the
 * answers it did get right would freeze a proof the buyer never agreed to on screen.
 *
 * It names the code it could not place, because a client sending several is otherwise
 * left to guess which one the shop rejected.
 */
class UnknownConsentException extends CheckoutException
{
    public function __construct(
        public readonly string $consentCode,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'This shop is not asking for the consent "%consent".',
            $code,
            $previous,
            ['%consent' => $consentCode],
        );
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::ConsentUnknown->value;
    }

    public function violationDetails(): array
    {
        return ['consentCode' => $this->consentCode];
    }
}
