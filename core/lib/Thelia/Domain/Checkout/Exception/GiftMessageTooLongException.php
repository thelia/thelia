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
 * The note for the recipient is longer than the shop accepts.
 *
 * Refused rather than cut: the note is printed on the parcel, and half a sentence there
 * is worse than none. The browser counts the characters left as a courtesy; this is what
 * decides.
 */
class GiftMessageTooLongException extends CheckoutException
{
    public function __construct(
        public readonly int $maximumLength,
        public readonly int $submittedLength,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'The message for the recipient must be %max characters at most.',
            $code,
            $previous,
            ['%max' => (string) $maximumLength],
        );
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::GiftMessageTooLong->value;
    }

    public function violationDetails(): array
    {
        return ['maximumLength' => $this->maximumLength, 'submittedLength' => $this->submittedLength];
    }
}
