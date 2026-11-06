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

namespace Thelia\Domain\Catalog\Stock\Exception;

use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;

/**
 * A step of the checkout asked to hold the stock of a cart more often than a buyer does: the cart,
 * or the address it comes from, is over its budget of holds.
 *
 * Not a shortage: nothing is missing from the stock, and a caller that answers a shortage with
 * "another cart took it" would be telling a robot to try again at once. It is answered with the
 * delay to wait (429 and Retry-After).
 */
final class StockReservationRateLimitedException extends TheliaProcessException
{
    public function __construct(public readonly int $retryAfterSeconds, ?\Throwable $previous = null)
    {
        parent::__construct(
            Translator::getInstance()->trans('Too many attempts to hold these products. Please try again later.'),
            previous: $previous,
        );
    }
}
