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

use Thelia\Exception\TheliaProcessException;

/**
 * This cart has already been turned into an order, and the order still stands.
 *
 * Raised from inside the transaction the order is written in, after the row of the cart
 * was locked: that is the only place where "no order yet" is still true at the moment the
 * first row is written. Everything above it — the applicative re-read of the placement,
 * the lock the placement takes — narrows the window; this closes it, including across two
 * application servers, which a lock held in PHP does not.
 *
 * It names the order that already exists, because the caller that met it has one thing
 * left to do: hand that order back instead of writing another.
 */
final class CartAlreadyOrderedException extends TheliaProcessException
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $cartId,
    ) {
        parent::__construct(\sprintf('Cart %d has already been ordered, as order %d.', $cartId, $orderId));
    }
}
