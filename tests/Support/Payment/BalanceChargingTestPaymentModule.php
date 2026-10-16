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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\BalanceChargingPaymentModuleInterface;

/**
 * A provider that declares it charges only the balance the instant parts leave, the way a
 * module adapted to split payments does.
 *
 * Registered the same way as InstantTestPaymentModule. The orders it was asked to pay are
 * kept in static state, which the test resets.
 */
final class BalanceChargingTestPaymentModule extends AbstractPaymentModule implements BalanceChargingPaymentModuleInterface
{
    /**
     * @var list<int> the orders the module was asked to pay, in order
     */
    public static array $paidOrderIds = [];

    /**
     * @var list<list<string>> the states of the parts of the order, in position order, at the
     *                         moment the module was asked to pay it
     */
    public static array $partStatesWhenAskedToPay = [];

    public static function reset(): void
    {
        self::$paidOrderIds = [];
        self::$partStatesWhenAskedToPay = [];
    }

    public function pay(Order $order): ?Response
    {
        self::$paidOrderIds[] = (int) $order->getId();
        self::$partStatesWhenAskedToPay[] = array_values(array_map(
            static fn (OrderPaymentPart $part): string => (string) $part->getState(),
            iterator_to_array(OrderPaymentPartQuery::create()->filterByOrderId($order->getId())->orderByPosition()->find()),
        ));

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
}
