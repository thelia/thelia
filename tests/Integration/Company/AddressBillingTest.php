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

namespace Thelia\Tests\Integration\Company;

use Thelia\Domain\Cart\Service\CartSelectionService;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Company;
use Thelia\Model\CompanyQuery;
use Thelia\Model\Customer;

/**
 * The buyer of a company that has a billing address is invoiced there, whatever address they
 * choose at the checkout; a company without one, a guest and a customer without a company choose
 * as they always did.
 */
final class AddressBillingTest extends AddressTestCase
{
    public function testTheBillingAddressOfTheCompanyReplacesTheChoiceOfTheBuyer(): void
    {
        $world = $this->twoCompanies();
        $headquarters = $this->billingAddressOf($world['first'], $world['administratorOfFirst']);
        $home = $this->addressOf($world['colleague'], null, 'Home');
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');

        foreach ([$home, $warehouse, $headquarters] as $chosen) {
            $cart = $this->cartOf($world['colleague']);
            $this->select($cart, invoice: $chosen);

            self::assertSame($headquarters->getId(), $this->invoiceAddressOf($cart), 'Chosen: '.$chosen->getLabel());
        }

        $cartOfAdministrator = $this->cartOf($world['administratorOfFirst']);
        $this->select($cartOfAdministrator, invoice: $this->addressOf($world['administratorOfFirst'], null, 'Private'));
        self::assertSame($headquarters->getId(), $this->invoiceAddressOf($cartOfAdministrator));
    }

    public function testTheBuyerKeepsTheChoiceOfTheDeliveryAddress(): void
    {
        $world = $this->twoCompanies();
        $this->billingAddressOf($world['first'], $world['administratorOfFirst']);
        $warehouse = $this->addressOf($world['creator'], $world['first'], 'Warehouse');
        $home = $this->addressOf($world['colleague'], null, 'Home');

        $cart = $this->cartOf($world['colleague']);
        $this->select($cart, delivery: $home);
        self::assertSame($home->getId(), CartQuery::create()->findPk($cart->getId())?->getCartAddressRelatedByAddressDeliveryId()?->getAddressId());

        $this->select($cart, delivery: $warehouse);
        self::assertSame($warehouse->getId(), CartQuery::create()->findPk($cart->getId())?->getCartAddressRelatedByAddressDeliveryId()?->getAddressId());
    }

    public function testAnAddressTheCompanyDoesNotShareIsNotImposed(): void
    {
        $world = $this->twoCompanies();
        $depot = $this->addressOf($world['buyerOfSecond'], $world['second'], 'Depot');
        CompanyQuery::create()->filterById($world['first']->getId())->update(['BillingAddressId' => $depot->getId()]);
        $home = $this->addressOf($world['colleague'], null, 'Home');

        $cart = $this->cartOf($world['colleague']);
        $this->select($cart, invoice: $home);

        self::assertSame($home->getId(), $this->invoiceAddressOf($cart), 'An address of another company is no billing address of this one.');
    }

    public function testACompanyWithoutBillingAddressLeavesTheChoiceToTheBuyer(): void
    {
        $world = $this->twoCompanies();
        $this->billingAddressOf($world['first'], $world['administratorOfFirst']);
        $depot = $this->addressOf($world['buyerOfSecond'], $world['second'], 'Depot');
        $privateOfSecond = $this->addressOf($world['buyerOfSecond'], null, 'Private');

        foreach ([$depot, $privateOfSecond] as $chosen) {
            $cart = $this->cartOf($world['buyerOfSecond']);
            $this->select($cart, invoice: $chosen);

            self::assertSame($chosen->getId(), $this->invoiceAddressOf($cart), 'Chosen: '.$chosen->getLabel());
        }
    }

    public function testAGuestAndACustomerWithoutCompanyChooseTheirInvoiceAddress(): void
    {
        $world = $this->twoCompanies();
        $this->billingAddressOf($world['first'], $world['administratorOfFirst']);

        foreach ([$this->guest(), $this->customer()] as $customer) {
            $own = $this->addressOf($customer, null, 'Own');
            $cart = $this->cartOf($customer);
            $this->select($cart, invoice: $own);

            self::assertSame($own->getId(), $this->invoiceAddressOf($cart));
        }
    }

    private function billingAddressOf(Company $company, Customer $administrator): Address
    {
        $headquarters = $this->addressOf($administrator, $company, 'Headquarters');
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $headquarters->getId()]);

        return $headquarters;
    }

    private function select(Cart $cart, ?Address $invoice = null, ?Address $delivery = null): void
    {
        $selection = $this->getService(CartSelectionService::class);

        if (null !== $invoice) {
            $selection->setInvoiceAddress(new CheckoutDTO($cart, invoiceAddressId: (int) $invoice->getId()));
        }

        if (null !== $delivery) {
            $selection->setDeliveryAddress(new CheckoutDTO($cart, deliveryAddressId: (int) $delivery->getId()));
        }
    }

    private function invoiceAddressOf(Cart $cart): ?int
    {
        return CartQuery::create()->findPk($cart->getId())?->getCartAddressRelatedByAddressInvoiceId()?->getAddressId();
    }
}
