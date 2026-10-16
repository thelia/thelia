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

namespace Thelia\Tests\Integration\Domain\Payment;

use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Payment\Exception\PaymentMethodNotOwnedException;
use Thelia\Domain\Payment\Exception\StoredMethodNotSelectableException;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Payment\UsesStoredMethodsPaymentModule;

/**
 * The stored payment method is chosen with the payment module, through the facade the
 * theme calls and the one the front API calls, and the rule is the same for both.
 */
final class StoredPaymentMethodSelectionTest extends IntegrationTestCase
{
    use UsesStoredMethodsPaymentModule;

    private FixtureFactory $factory;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->module = $this->installTheStoredMethodsPaymentModule(static::getContainer(), $this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        $this->uninstallTheStoredMethodsPaymentModule(static::getContainer());

        parent::tearDown();
    }

    public function testTheBuyerChoosesOneOfTheirMethodsWithItsModule(): void
    {
        $customer = $this->customer();
        $cart = $this->factory->cart($customer);
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');

        $this->getService(CartFacade::class)->setPaymentModule(new CheckoutDTO(
            $cart,
            paymentModuleId: $this->module->getId(),
            customerPaymentMethodId: $method->getId(),
        ));

        self::assertSame($this->module->getId(), $this->reread($cart)->getPaymentModuleId());
        self::assertSame($method->getId(), $this->reread($cart)->getCustomerPaymentMethodId());
    }

    public function testAMethodOfAnotherCustomerIsRefusedAndNothingIsWritten(): void
    {
        $cart = $this->factory->cart($this->customer());
        $methodOfSomebodyElse = $this->storedMethodRow($this->customer(), $this->module, 'Visa ending 4242', '2030-12-31');

        try {
            $this->select($cart, $this->module, $methodOfSomebodyElse->getId());
            self::fail('A method of another customer cannot pay this cart.');
        } catch (PaymentMethodNotOwnedException) {
        }

        self::assertNull($this->reread($cart)->getCustomerPaymentMethodId());
        self::assertNull($this->reread($cart)->getPaymentModuleId(), 'The module is not chosen either: the choice is refused as a whole.');
    }

    public function testAMethodOfAnotherModuleIsRefused(): void
    {
        $customer = $this->customer();
        $cart = $this->factory->cart($customer);
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');

        $this->expectException(StoredMethodNotSelectableException::class);

        $this->select($cart, $this->chequeModule(), $method->getId());
    }

    public function testAnExpiredMethodIsRefused(): void
    {
        $customer = $this->customer();
        $cart = $this->factory->cart($customer);
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2020-01-31');

        $this->expectException(StoredMethodNotSelectableException::class);

        $this->select($cart, $this->module, $method->getId());
    }

    public function testAGuestIsRefused(): void
    {
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());
        $cart = $this->factory->cart($guest);
        $method = $this->storedMethodRow($guest, $this->module, 'Visa ending 4242', '2030-12-31');

        $this->expectException(StoredMethodNotSelectableException::class);

        $this->select($cart, $this->module, $method->getId());
    }

    public function testChangingTheModuleForgetsTheChosenMethod(): void
    {
        $customer = $this->customer();
        $cart = $this->factory->cart($customer);
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->select($cart, $this->module, $method->getId());

        // What the theme posts when the buyer picks another module: no stored method.
        $this->getService(CartFacade::class)->setPaymentModule(new CheckoutDTO(
            $this->reread($cart),
            paymentModuleId: $this->chequeModule()->getId(),
        ));

        self::assertSame($this->chequeModule()->getId(), $this->reread($cart)->getPaymentModuleId());
        self::assertNull($this->reread($cart)->getCustomerPaymentMethodId());
    }

    public function testChoosingANewMethodOfTheSameModuleForgetsTheStoredOne(): void
    {
        $customer = $this->customer();
        $cart = $this->factory->cart($customer);
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->select($cart, $this->module, $method->getId());

        $this->select($this->reread($cart), $this->module, null);

        self::assertNull($this->reread($cart)->getCustomerPaymentMethodId());
    }

    /**
     * The row goes, the choice goes with it: the cart then pays with a new method.
     */
    public function testForgettingTheMethodEmptiesTheChoice(): void
    {
        $customer = $this->customer();
        $cart = $this->factory->cart($customer);
        $method = $this->storedMethodRow($customer, $this->module, 'Visa ending 4242', '2030-12-31');
        $this->select($cart, $this->module, $method->getId());

        $method->delete($this->getPropelConnection());

        self::assertNull($this->reread($cart)->getCustomerPaymentMethodId());
        self::assertSame($this->module->getId(), $this->reread($cart)->getPaymentModuleId());
    }

    private function select(Cart $cart, Module $module, ?int $customerPaymentMethodId): void
    {
        $this->getService(CheckoutFacade::class)->selectPaymentModule(new CheckoutDTO(
            $cart,
            paymentModuleId: $module->getId(),
            customerPaymentMethodId: $customerPaymentMethodId,
        ));
    }

    private function reread(Cart $cart): Cart
    {
        return CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection())
            ?? throw new \RuntimeException('The cart went away.');
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function chequeModule(): Module
    {
        return ModuleQuery::create()->findOneByCode('Cheque')
            ?? throw new \RuntimeException('No Cheque module installed — run bin/test-prepare.');
    }
}
