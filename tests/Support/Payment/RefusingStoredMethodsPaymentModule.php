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
 * A second payment module whose provider always refuses to forget a stored
 * method, distinct from StoredMethodsPaymentModule so a test can register one
 * method through each and see that a refusal from one does not hold the other
 * back: {@see \Thelia\Domain\Payment\CustomerPaymentMethodService::tellTheProviderToForget()}
 * catches the refusal, and the anonymizer runs every callback regardless.
 */
final class RefusingStoredMethodsPaymentModule extends AbstractPaymentModule implements PaymentModuleWithStoredMethodsInterface
{
    /**
     * @var list<int>
     */
    public static array $askedToForgetMethodIds = [];

    public static function reset(): void
    {
        self::$askedToForgetMethodIds = [];
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
        self::$askedToForgetMethodIds[] = (int) $method->getId();

        throw new StoredMethodRefusedException();
    }
}
