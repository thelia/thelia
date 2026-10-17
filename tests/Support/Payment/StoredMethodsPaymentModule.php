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
use Thelia\Domain\Payment\Exception\StoredMethodRefusedException;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\PaymentModuleWithStoredMethodsInterface;

/**
 * A payment module whose provider keeps payment methods.
 *
 * Registered under a `module` row by the tests that need it. It is not in the compiled
 * container, so the core instantiates it from that row's namespace at every call, unless
 * the test installs it as a service (UsesStoredMethodsPaymentModule), which the payment
 * listing and Action\Module::pay() need: what a test sets, and what the provider was
 * asked, lives in static state, which the test resets.
 */
final class StoredMethodsPaymentModule extends AbstractPaymentModule implements PaymentModuleWithStoredMethodsInterface
{
    public static bool $offersStoredMethods = true;

    public static bool $providerRefusesToForget = false;

    /**
     * What the provider says when it refuses, null for the core's own wording.
     */
    public static ?string $refusalReason = null;

    /**
     * @var list<int>
     */
    public static array $forgottenMethodIds = [];

    /**
     * What the core asked the module, in order: "forget", "destroy", "pay" and
     * "payWithStoredMethod".
     *
     * @var list<string>
     */
    public static array $calls = [];

    /**
     * The id of the stored method each "payWithStoredMethod" was handed, in order.
     *
     * @var list<int>
     */
    public static array $paidWithMethodIds = [];

    public static function reset(): void
    {
        self::$offersStoredMethods = true;
        self::$providerRefusesToForget = false;
        self::$refusalReason = null;
        self::$forgottenMethodIds = [];
        self::$calls = [];
        self::$paidWithMethodIds = [];
    }

    public function destroy(?ConnectionInterface $con = null, $deleteModuleData = false): void
    {
        self::$calls[] = 'destroy';
    }

    public function pay(Order $order): ?Response
    {
        self::$calls[] = 'pay';

        return null;
    }

    public function isValidPayment(): bool
    {
        return true;
    }

    public function supportsStoredMethods(): bool
    {
        return self::$offersStoredMethods;
    }

    public function payWithStoredMethod(Order $order, CustomerPaymentMethod $method): ?Response
    {
        self::$calls[] = 'payWithStoredMethod';
        self::$paidWithMethodIds[] = (int) $method->getId();

        return null;
    }

    /**
     * An order is presented again on a retry, the way a provider whose reference varies
     * at each attempt allows.
     */
    public function supportsPaymentRetry(): bool
    {
        return true;
    }

    public function forgetStoredMethod(CustomerPaymentMethod $method): void
    {
        self::$calls[] = 'forget';

        if (self::$providerRefusesToForget) {
            throw new StoredMethodRefusedException(self::$refusalReason);
        }

        self::$forgottenMethodIds[] = (int) $method->getId();
    }
}
