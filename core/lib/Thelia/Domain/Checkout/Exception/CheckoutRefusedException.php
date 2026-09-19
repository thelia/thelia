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

use Thelia\Domain\Checkout\DTO\CheckoutViolation;

/**
 * The placement refused a cart, and says everything that is wrong with it at once.
 *
 * Deliberately not one of the single-family refusals: those answer the question "what is
 * the next thing this buyer has to do", which is what a tunnel of screens asks. This one
 * answers "what is wrong with this checkout", which is what a caller submitting the whole
 * thing in one request asks, and the difference is a round trip per field.
 */
final class CheckoutRefusedException extends CheckoutException
{
    /**
     * @param list<CheckoutViolation> $violations never empty: a refusal with nothing to report is not a refusal
     */
    public function __construct(
        public readonly array $violations,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('This order cannot be placed yet.', $code, $previous);
    }
}
