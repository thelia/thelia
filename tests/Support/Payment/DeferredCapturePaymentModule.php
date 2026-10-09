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
use Thelia\Domain\Payment\DTO\PaymentOperationResult;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\PaymentModuleManagingOrderStatusInterface;
use Thelia\Module\PaymentModuleWithCaptureInterface;

/**
 * A payment module that reserves the amount first and takes it later, the way a
 * card provider with a capture delay does, with the provider played by static state
 * the test sets before calling.
 *
 * Registered under a `module` row by the tests that need it; the core instantiates it
 * from that row's namespace, so it needs no container.
 */
final class DeferredCapturePaymentModule extends AbstractPaymentModule implements PaymentModuleWithCaptureInterface, PaymentModuleManagingOrderStatusInterface
{
    public static bool $deferredCapture = true;

    /** Whether the module moves the order status itself, from its own configuration. */
    public static bool $managesOrderStatus = false;

    /** @var PaymentOperationResult|\Throwable|null what the next capture answers, or throws */
    public static PaymentOperationResult|\Throwable|null $nextCaptureAnswer = null;

    /** @var list<array{order: int, amount: float, transaction: int}> */
    public static array $captureCalls = [];

    /** @var list<int> ids of the orders whose authorization was released */
    public static array $voidCalls = [];

    /** What happens elsewhere while the provider is being called — another worker taking the journal. */
    public static ?\Closure $whileCapturing = null;

    public static function reset(): void
    {
        self::$deferredCapture = true;
        self::$nextCaptureAnswer = null;
        self::$captureCalls = [];
        self::$voidCalls = [];
        self::$whileCapturing = null;
        self::$managesOrderStatus = false;
    }

    public function pay(Order $order): ?Response
    {
        return null;
    }

    public function isValidPayment(): bool
    {
        return true;
    }

    public function managesOrderStatus(): bool
    {
        return self::$managesOrderStatus;
    }

    public function supportsDeferredCapture(): bool
    {
        return self::$deferredCapture;
    }

    public function capture(Order $order, float $amount, OrderPaymentTransaction $transaction): PaymentOperationResult
    {
        self::$captureCalls[] = ['order' => (int) $order->getId(), 'amount' => $amount, 'transaction' => (int) $transaction->getId()];

        if (null !== self::$whileCapturing) {
            (self::$whileCapturing)();
        }

        $answer = self::$nextCaptureAnswer;
        self::$nextCaptureAnswer = null;

        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        // The reference names the journal line: unique across requests, as a provider's is.
        // A counter would restart at each HTTP request and hand out CAP-1 twice.
        return $answer ?? PaymentOperationResult::succeeded('CAP-'.$transaction->getId());
    }

    public function voidAuthorization(Order $order, OrderPaymentTransaction $transaction): PaymentOperationResult
    {
        self::$voidCalls[] = (int) $order->getId();

        return PaymentOperationResult::succeeded('VOID-'.$transaction->getId());
    }
}
