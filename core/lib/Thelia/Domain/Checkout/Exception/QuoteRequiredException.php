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
 * The shop only sells through quotes: an ordinary cart cannot be ordered, a quote has to be asked
 * for first.
 */
final class QuoteRequiredException extends CheckoutException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('This shop only sells through quotes: ask for a quote for this cart.', 0, $previous);
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::QuoteRequired->value;
    }
}
