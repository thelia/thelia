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

use Thelia\Domain\Checkout\Exception\CheckoutException;

/**
 * One thing a cart still has to settle before it can be ordered.
 *
 * Three things a caller needs and one it may use: the step to send the buyer back to,
 * the machine code to branch on, the sentence to show them, and whatever the refusal
 * knows beyond its wording — the code of the consent left unticked, for instance.
 */
final readonly class CheckoutViolation
{
    /**
     * @param string               $stepCode the `checkout_step` code of the step that refused, so that a
     *                                       caller can send the buyer back to the screen that settles it
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

    public static function fromRefusal(string $stepCode, CheckoutException $refusal): self
    {
        return new self(
            $stepCode,
            $refusal->violationCode(),
            $refusal->getMessage(),
            $refusal->violationDetails(),
        );
    }
}
