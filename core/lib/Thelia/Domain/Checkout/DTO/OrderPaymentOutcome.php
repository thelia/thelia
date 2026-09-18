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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Order;

/**
 * What came back from ORDER_PAY: the order that was written, and the answer of the
 * payment module, which is not always there.
 *
 * The two used to be inseparable — the checkout returned the response and went looking
 * for the order in the session — which is exactly what a caller with no session cannot
 * do.
 */
final readonly class OrderPaymentOutcome
{
    public function __construct(
        public Order $placedOrder,
        public ?Response $paymentResponse,
    ) {
    }
}
