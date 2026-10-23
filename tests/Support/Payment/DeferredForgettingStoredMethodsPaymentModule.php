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
use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\DeferredStoredMethodForgettingPaymentModuleInterface;

/**
 * A payment module that needs, to forget a stored method, data going with its row:
 * it reads whether the row is still stored when the core lets it prepare, and again
 * when its prepared call runs. Registered under a `module` row by the tests that need
 * it; what it saw lives in static state, which the test resets.
 */
final class DeferredForgettingStoredMethodsPaymentModule extends AbstractPaymentModule implements DeferredStoredMethodForgettingPaymentModuleInterface
{
    /**
     * Method id => whether its row was still stored when the module prepared.
     *
     * @var array<int, bool>
     */
    public static array $storedWhenPrepared = [];

    /**
     * Method id => whether its row was still stored when the prepared call ran.
     *
     * @var array<int, bool>
     */
    public static array $storedWhenForgotten = [];

    /**
     * @var list<int>
     */
    public static array $forgottenWithoutPreparation = [];

    public static function reset(): void
    {
        self::$storedWhenPrepared = [];
        self::$storedWhenForgotten = [];
        self::$forgottenWithoutPreparation = [];
    }

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
        self::$forgottenWithoutPreparation[] = (int) $method->getId();
    }

    public function prepareToForgetStoredMethod(CustomerPaymentMethod $method): \Closure
    {
        $methodId = (int) $method->getId();
        self::$storedWhenPrepared[$methodId] = self::isStored($methodId);

        return static function () use ($methodId): void {
            self::$storedWhenForgotten[$methodId] = self::isStored($methodId);
        };
    }

    private static function isStored(int $methodId): bool
    {
        return CustomerPaymentMethodQuery::create()->filterById($methodId)->count() > 0;
    }
}
