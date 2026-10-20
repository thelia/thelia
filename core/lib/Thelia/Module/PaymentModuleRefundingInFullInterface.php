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

namespace Thelia\Module;

use Thelia\Model\Order;

/**
 * A payment module whose provider, for some orders, can only give the payment back in full:
 * a card payment not remitted yet is cancelled whole, never refunded in part.
 *
 * Optional: a module that refunds any amount leaves it aside. When it answers true for an
 * order, the refund service refuses any other amount than everything still refundable, and the
 * back office offers that amount alone.
 */
interface PaymentModuleRefundingInFullInterface extends PaymentModuleWithRefundInterface
{
    /**
     * Whether, as things stand for the order, its payment can only be given back in full.
     */
    public function refundsOnlyInFull(Order $order): bool;
}
