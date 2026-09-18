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
 * Whether the cart may be ordered, and everything standing in the way if it may not.
 *
 * Every refusal is reported at once, in the order of the tunnel: a client submitting a
 * whole checkout has a form to correct, not a screen to go back to.
 *
 * This is also the body a refused placement comes back with, so a client has one payload
 * to read whether it asked before placing or found out while placing.
 */
final readonly class CheckoutValidationOutput
{
    /**
     * @param list<CheckoutViolationOutput> $violations
     */
    public function __construct(
        public bool $ready,
        public array $violations = [],
    ) {
    }

    /**
     * @param list<CheckoutViolation> $violations
     */
    public static function ofDomainViolations(array $violations): self
    {
        $reported = array_map(
            static fn (CheckoutViolation $violation): CheckoutViolationOutput => CheckoutViolationOutput::fromViolation($violation),
            $violations,
        );

        return new self([] === $reported, $reported);
    }

    /**
     * @return array{ready: bool, violations: list<array{stepCode: string, code: string, message: string, details: array<string, mixed>}>}
     */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready,
            'violations' => array_map(
                static fn (CheckoutViolationOutput $violation): array => $violation->toArray(),
                $this->violations,
            ),
        ];
    }
}
