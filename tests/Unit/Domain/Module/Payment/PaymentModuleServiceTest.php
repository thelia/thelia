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

namespace Thelia\Tests\Unit\Domain\Module\Payment;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Collection\ObjectCollection;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Resource\PaymentModule;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Module\Payment\PaymentModuleService;
use Thelia\Model\Cart;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Module\PaymentModuleInterface;

/**
 * Only the core listener (priority 128) fills the amount limits of `IsValidPaymentEvent`. A module listening with a
 * higher priority may decide alone and stop the propagation: the limits are then never set, and reading them must not
 * break the payment module list.
 */
final class PaymentModuleServiceTest extends TestCase
{
    public function testAListenerThatStopsThePropagationLeavesTheAmountLimitsUnset(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            TheliaEvents::MODULE_PAYMENT_IS_VALID,
            static fn (IsValidPaymentEvent $event) => $event->stopPropagation(),
            256,
        );
        $dispatcher->addListener(
            TheliaEvents::MODULE_PAYMENT_IS_VALID,
            static fn (IsValidPaymentEvent $event) => $event->setValidModule(true)->setMinimumAmount(1.0)->setMaximumAmount(2.0),
            128,
        );

        $paymentModule = $this->paymentModule($dispatcher);

        self::assertFalse($paymentModule->getValid());
        self::assertSame('Cheque', $paymentModule->getCode());
        self::assertNull($paymentModule->getMinimumAmount());
        self::assertNull($paymentModule->getMaximumAmount());
    }

    public function testTheAmountLimitsSetByTheListenersReachThePaymentModule(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            TheliaEvents::MODULE_PAYMENT_IS_VALID,
            static fn (IsValidPaymentEvent $event) => $event->setMinimumAmount(10.0)->setMaximumAmount(500.0),
            128,
        );

        $paymentModule = $this->paymentModule($dispatcher);

        self::assertSame(10.0, $paymentModule->getMinimumAmount());
        self::assertSame(500.0, $paymentModule->getMaximumAmount());
    }

    private function paymentModule(EventDispatcher $dispatcher): PaymentModule
    {
        $container = new Container();

        $moduleInstance = $this->createStub(PaymentModuleInterface::class);
        $moduleInstance->method('getCode')->willReturn('Cheque');

        $module = $this->createStub(Module::class);
        $module->method('getId')->willReturn(7);
        $module->method('getPaymentModuleInstance')->willReturn($moduleInstance);
        $module->method('getModuleI18ns')->willReturn(new ObjectCollection());

        $lang = $this->createStub(Lang::class);
        $lang->method('getLocale')->willReturn('en_US');

        $service = new PaymentModuleService(new RequestStack(), $dispatcher, $container);

        $paymentModule = (new \ReflectionMethod(PaymentModuleService::class, 'getPaymentModule'))
            ->invoke($service, $dispatcher, $module, $this->createStub(Cart::class), $lang);

        self::assertInstanceOf(PaymentModule::class, $paymentModule);

        return $paymentModule;
    }
}
