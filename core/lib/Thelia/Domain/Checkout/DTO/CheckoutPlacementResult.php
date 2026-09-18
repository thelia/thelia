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

/**
 * The order that came out of the placement, and what is left for the front to do.
 *
 * The status is re-read from the order after the payment module was called, because a
 * module may have settled the payment while it was in there: assuming "not paid" from
 * the fact that an order was just created would be wrong for every such module.
 */
final readonly class CheckoutPlacementResult
{
    /**
     * @param string $orderStatusCode one of the OrderStatus::CODE_* values, read back after payment
     * @param bool   $alreadyPlaced   true when this cart already had an order and that order is what
     *                                came back: a retried request, a double click, a client that lost
     *                                the answer to the first call
     */
    public function __construct(
        public int $orderId,
        public string $orderReference,
        public string $orderStatusCode,
        public bool $paid,
        public PaymentAction $paymentAction,
        public bool $alreadyPlaced,
    ) {
    }
}
