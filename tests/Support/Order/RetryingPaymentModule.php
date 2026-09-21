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

namespace Thelia\Tests\Support\Order;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;

/**
 * A payment module that declares an order may be presented to it more than once, the
 * way a provider whose transaction reference varies at each attempt can.
 *
 * Registered under a `module` row by the tests that need it; the core instantiates it
 * from that row's namespace, so it needs no container.
 */
final class RetryingPaymentModule extends AbstractPaymentModule
{
    public function pay(Order $order): ?Response
    {
        return null;
    }

    public function isValidPayment(): bool
    {
        return true;
    }

    public function supportsPaymentRetry(): bool
    {
        return true;
    }

    /**
     * Takes the stock on creation, so that a stock moved once, or given back, is
     * observable on a retry.
     */
    public function manageStockOnCreation(): bool
    {
        return true;
    }
}
