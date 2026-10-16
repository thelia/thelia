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

namespace Thelia\Tests\Support\Payment;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Map\CustomerPaymentMethodTableMap;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\PaymentModuleWithStoredMethodsInterface;

/**
 * A payment module whose provider misbehaves with the transaction of the core when it is
 * told to forget a method: it rolls back the connection the core handed it a transaction
 * on, the way third-party code that manages its own transaction does.
 */
final class RollingBackStoredMethodsPaymentModule extends AbstractPaymentModule implements PaymentModuleWithStoredMethodsInterface
{
    public function destroy(?ConnectionInterface $con = null, $deleteModuleData = false): void
    {
    }

    public function pay(Order $order): ?Response
    {
        return null;
    }

    public function isValidPayment(): bool
    {
        return true;
    }

    public function supportsStoredMethods(): bool
    {
        return true;
    }

    public function payWithStoredMethod(Order $order, CustomerPaymentMethod $method): ?Response
    {
        return null;
    }

    public function forgetStoredMethod(CustomerPaymentMethod $method): void
    {
        Propel::getWriteConnection(CustomerPaymentMethodTableMap::DATABASE_NAME)->rollBack();
    }
}
