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

namespace Thelia\Tests\Integration\Module;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Module\Payment\PaymentCartContext;
use Thelia\Model\Cart;
use Thelia\Model\Order;
use Thelia\Model\TaxRuleCountry;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Test\IntegrationTestCase;

final class CurrentOrderTotalAmountTest extends IntegrationTestCase
{
    public function testTheCartBeingPaidIsPricedWithoutAnySessionAndWithItsPostage(): void
    {
        $cart = $this->aCartWithPostage();
        $module = $this->aPaymentModule();
        $module->setRequest(Request::create('http://localhost/api/front/account/checkout/1/place'));

        $context = $this->getService(PaymentCartContext::class);

        [$taxed, $taxedWithoutPostage, $untaxed, $untaxedWithoutPostage] = $context->within(
            $cart,
            static fn (): array => [
                $module->getCurrentOrderTotalAmount(),
                $module->getCurrentOrderTotalAmount(with_postage: false),
                $module->getCurrentOrderTotalAmount(with_tax: false),
                $module->getCurrentOrderTotalAmount(with_tax: false, with_postage: false),
            ],
        );

        self::assertGreaterThan(0, $taxedWithoutPostage);
        self::assertEqualsWithDelta(5.0, $taxed - $taxedWithoutPostage, 0.001);
        self::assertEqualsWithDelta(4.0, $untaxed - $untaxedWithoutPostage, 0.001);
        self::assertNull($context->cart());
    }

    public function testTheSessionCartIsPricedTheSameWayWhenNoCartIsBeingPaid(): void
    {
        $cart = $this->aCartWithPostage();
        $module = $this->aPaymentModule();

        $request = $this->getService(RequestStack::class)->getMainRequest();
        self::assertInstanceOf(Request::class, $request);
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);
        $session->setSessionCart($cart);
        $module->setRequest($request);

        try {
            $fromTheSession = $module->getCurrentOrderTotalAmount();
        } finally {
            $session->setSessionCart(null);
        }

        $fromTheCartBeingPaid = $this->getService(PaymentCartContext::class)->within(
            $cart,
            static fn () => $module->getCurrentOrderTotalAmount(),
        );

        self::assertGreaterThan(5.0, $fromTheSession);
        self::assertEqualsWithDelta($fromTheCartBeingPaid, $fromTheSession, 0.001);
    }

    public function testACartWithNoDeliveryAddressYetIsTaxedWhereItsCustomerLives(): void
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country(['isocode' => '056', 'isoalpha2' => 'BE', 'isoalpha3' => 'BEL', 'shopCountry' => false]);
        $customer = $factory->customer($factory->customerTitle());
        $factory->address($customer, $country)->setIsDefault(1)->save($this->getPropelConnection());

        $taxRule = $factory->taxRule(['isDefault' => false]);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($country->getId())
            ->setTaxId($factory->tax(['requirements' => ['percent' => '20'], 'title' => 'VAT 20'])->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $factory->product(
            $factory->category(),
            $taxRule,
            $factory->currency(),
            ['baseQuantity' => 10],
        ));
        $cart->clearCartItems();

        $module = $this->aPaymentModule();
        $module->setRequest(Request::create('http://localhost/api/front/account/checkout/1/place'));

        [$taxed, $untaxed] = $this->getService(PaymentCartContext::class)->within(
            $cart,
            static fn (): array => [
                $module->getCurrentOrderTotalAmount(with_postage: false),
                $module->getCurrentOrderTotalAmount(with_tax: false, with_postage: false),
            ],
        );

        self::assertGreaterThan(0, $untaxed);
        self::assertEqualsWithDelta($untaxed * 1.2, $taxed, 0.01);
    }

    public function testOutsideAnyRequestTheAmountIsZeroInsteadOfAnError(): void
    {
        $requestStack = $this->getService(RequestStack::class);
        $poppedRequests = [];

        while (null !== $request = $requestStack->pop()) {
            $poppedRequests[] = $request;
        }

        try {
            self::assertSame(0, $this->aPaymentModule()->getCurrentOrderTotalAmount());
        } finally {
            foreach (array_reverse($poppedRequests) as $request) {
                $requestStack->push($request);
            }
        }
    }

    private function aCartWithPostage(): Cart
    {
        $factory = $this->createFixtureFactory();
        $cart = $factory->cart();
        $factory->cartItem($cart, $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
            ['baseQuantity' => 10],
        ));

        $cart->setPostage('5')->setPostageTax('1')->save($this->getPropelConnection());
        $cart->clearCartItems();

        return $cart;
    }

    private function aPaymentModule(): AbstractPaymentModule
    {
        $module = new class extends AbstractPaymentModule {
            public function pay(Order $order): null
            {
                return null;
            }

            public function isValidPayment(): bool
            {
                return $this->getCurrentOrderTotalAmount() > 0;
            }
        };

        $module->setContainer(static::getContainer());

        return $module;
    }
}
