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
use Thelia\Domain\Subscription\Enum\RecurringChargeOutcome;
use Thelia\Domain\Subscription\RecurringChargeResult;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\RecurringPaymentModuleInterface;

/**
 * The reference implementation of RecurringPaymentModuleInterface, for the tests.
 *
 * A real module asks its provider to charge the stored method of the order's customer, with
 * the idempotency key, and turns the answer into a RecurringChargeResult; it never changes
 * the status of the order, and answers retryable() instead of throwing when the provider
 * cannot be reached. Here the answer is chosen by the test ($outcome), and every charge is
 * recorded ($charges) so that a test can tell how many times the provider was asked, and
 * with which key.
 *
 * Installed under a `module` row by UsesRecurringTestPaymentModule. What a test sets lives in
 * static state, which the test resets.
 */
final class RecurringTestPaymentModule extends AbstractPaymentModule implements RecurringPaymentModuleInterface
{
    public static RecurringChargeOutcome $outcome = RecurringChargeOutcome::Paid;

    public static bool $chargesOffSession = true;

    public static bool $offersStoredMethods = true;

    /**
     * False for a module that leaves the stock to the payment: the order takes it when it
     * turns paid, not when it is placed.
     */
    public static bool $managesStockOnCreation = true;

    /**
     * Run once, inside the next charge, before the provider answers: how a test makes a
     * second billing happen while the first one waits on the provider.
     */
    public static ?\Closure $duringNextCharge = null;

    /**
     * @var list<array{orderId: int, methodId: int, idempotencyKey: string}>
     */
    public static array $charges = [];

    public static function reset(): void
    {
        self::$outcome = RecurringChargeOutcome::Paid;
        self::$chargesOffSession = true;
        self::$offersStoredMethods = true;
        self::$managesStockOnCreation = true;
        self::$duringNextCharge = null;
        self::$charges = [];
    }

    public function supportsRecurringCharges(): bool
    {
        return self::$chargesOffSession;
    }

    public function chargeOffSession(Order $order, CustomerPaymentMethod $method, string $idempotencyKey): RecurringChargeResult
    {
        self::$charges[] = [
            'orderId' => (int) $order->getId(),
            'methodId' => (int) $method->getId(),
            'idempotencyKey' => $idempotencyKey,
        ];

        if (null !== self::$duringNextCharge) {
            $duringCharge = self::$duringNextCharge;
            self::$duringNextCharge = null;
            $duringCharge();
        }

        return match (self::$outcome) {
            RecurringChargeOutcome::Paid => RecurringChargeResult::paid('txn_'.$idempotencyKey),
            RecurringChargeOutcome::Declined => RecurringChargeResult::declined('Insufficient funds'),
            RecurringChargeOutcome::ActionRequired => RecurringChargeResult::actionRequired('The issuer asks for an authentication'),
            RecurringChargeOutcome::Retryable => RecurringChargeResult::retryable('The provider did not answer in time'),
        };
    }

    /**
     * @return list<string> the idempotency key of each charge, in order
     */
    public static function chargedKeys(): array
    {
        return array_map(static fn (array $charge): string => $charge['idempotencyKey'], self::$charges);
    }

    public function supportsStoredMethods(): bool
    {
        return self::$offersStoredMethods;
    }

    public function payWithStoredMethod(Order $order, CustomerPaymentMethod $method): ?Response
    {
        return null;
    }

    public function forgetStoredMethod(CustomerPaymentMethod $method): void
    {
    }

    public function pay(Order $order): ?Response
    {
        return null;
    }

    public function manageStockOnCreation(): bool
    {
        return self::$managesStockOnCreation;
    }

    public function isValidPayment(): bool
    {
        return true;
    }

    public function destroy(?ConnectionInterface $con = null, $deleteModuleData = false): void
    {
    }
}
