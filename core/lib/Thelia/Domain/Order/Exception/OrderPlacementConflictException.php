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

namespace Thelia\Domain\Order\Exception;

use Thelia\Domain\Checkout\Exception\CheckoutException;

/**
 * The database broke a lock conflict between this order and another one being placed at
 * the same moment on the same stock: a deadlock (1213), or on MariaDB 11.6 and later a row
 * changed since the transaction took its picture (1020). The order was rolled back whole;
 * placed again a moment later it is judged on the stock the other order left.
 *
 * A refusal rather than a failure: nothing is broken, and a caller can retry.
 */
final class OrderPlacementConflictException extends CheckoutException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Another order took the same products at the same moment. Please try again.', previous: $previous);
    }
}
