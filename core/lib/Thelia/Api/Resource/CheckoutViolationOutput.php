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

namespace Thelia\Api\Resource;

use Thelia\Domain\Checkout\DTO\CheckoutViolation;

/**
 * One thing the cart still has to settle, as the front API hands it over.
 *
 * The domain violation is copied rather than handed over as it is: what a client
 * branches on is a published contract, and it must not move because a field was added to
 * a DTO of the domain.
 */
final readonly class CheckoutViolationOutput
{
    /**
     * @param string               $stepCode the checkout step that refused, so a client can send the buyer back to it
     * @param string               $code     a stable machine code, see CheckoutViolationCode
     * @param string               $message  the refusal as the buyer reads it, already translated
     * @param array<string, mixed> $details  what the refusal knows beyond its wording
     */
    public function __construct(
        public string $stepCode,
        public string $code,
        public string $message,
        public array $details = [],
    ) {
    }

    public static function fromViolation(CheckoutViolation $violation): self
    {
        return new self(
            $violation->stepCode,
            $violation->code,
            $violation->message,
            $violation->details,
        );
    }

    /**
     * @return array{stepCode: string, code: string, message: string, details: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'stepCode' => $this->stepCode,
            'code' => $this->code,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
