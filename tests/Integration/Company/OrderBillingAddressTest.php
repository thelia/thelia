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

use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Company;
use Thelia\Model\CompanyQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;

/**
 * The buyer of a company that has a billing address is invoiced there when the order is placed,
 * whatever the cart held: a cart prepared before the buyer joined the company, or before the
 * company chose or changed its billing address, is invoiced like any other.
 */
final class OrderBillingAddressTest extends OrderCompanyTestCase
{
    public function testACartPreparedBeforeTheBuyerJoinedIsInvoicedToTheCompany(): void
    {
        $company = $this->company();
        $headquarters = $this->billingAddressOf($company, 'Lyon');
        $buyer = $this->customer();
        $cart = $this->cartOf($buyer, '100');

        $this->facade->attach($company, $buyer, CompanyRoleCode::BUYER, null, null, null);
        $order = $this->placeFromTheCartAddresses($cart, $buyer);

        $invoice = $this->invoiceOf($order);
        self::assertSame($headquarters->getAddress1(), $invoice->getAddress1());
        self::assertSame('Lyon', $invoice->getCity());
        self::assertSame($company->getId(), $order->getCompanyId());
    }

    public function testACartInvoicedToTheFormerBillingAddressIsInvoicedToTheNewOne(): void
    {
        $company = $this->company();
        $former = $this->billingAddressOf($company, 'Nantes');
        $buyer = $this->buyerOf($company);
        $cart = $this->cartOf($buyer, '100');
        $cart->setAddressInvoiceId((int) $this->fixtures->cartAddress($former, null, null, ['city' => 'Nantes'])->getId())->save($this->getPropelConnection());

        $current = $this->billingAddressOf($company, 'Lille');
        $order = $this->placeFromTheCartAddresses($cart, $buyer);

        self::assertSame('Lille', $this->invoiceOf($order)->getCity());
        self::assertSame($current->getAddress1(), $this->invoiceOf($order)->getAddress1());
    }

    public function testTheDeliveryAddressStaysTheOneOfTheCart(): void
    {
        $company = $this->company();
        $this->billingAddressOf($company, 'Lyon');
        $buyer = $this->buyerOf($company);
        $cart = $this->cartOf($buyer, '100');
        $deliveryStreet = $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddress1();

        $order = $this->placeFromTheCartAddresses($cart, $buyer);

        $placed = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());
        self::assertSame($deliveryStreet, $placed?->getAddress1());
        self::assertNotSame('Lyon', $placed?->getCity());
    }

    public function testAnInvoiceAddressThatIsTheDeliveryAddressOfTheCartIsNotRewrittenUnderTheDelivery(): void
    {
        $company = $this->company();
        $this->billingAddressOf($company, 'Lyon');
        $buyer = $this->buyerOf($company);
        $cart = $this->cartOf($buyer, '100');
        $deliveryStreet = $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddress1();
        $cart->setAddressInvoiceId($cart->getAddressDeliveryId())->save($this->getPropelConnection());

        $order = $this->placeFromTheCartAddresses($cart, $buyer);

        self::assertSame('Lyon', $this->invoiceOf($order)->getCity());
        self::assertSame($deliveryStreet, OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId())?->getAddress1(), 'The delivery is still where the buyer asked.');
    }

    public function testACompanyWithoutBillingAddressAndACustomerWithoutCompanyKeepTheInvoiceOfTheCart(): void
    {
        $company = $this->company();
        $member = $this->buyerOf($company);
        $alone = $this->customer();

        foreach ([$member, $alone] as $customer) {
            $cart = $this->cartOf($customer, '100');
            $keptStreet = $cart->getCartAddressRelatedByAddressInvoiceId()?->getAddress1();

            $order = $this->placeFromTheCartAddresses($cart, $customer);

            self::assertSame($keptStreet, $this->invoiceOf($order)->getAddress1());
        }
    }

    public function testAnOrderNotPlacedAtTheKeyboardKeepsTheInvoiceOfTheCart(): void
    {
        $company = $this->company();
        $this->billingAddressOf($company, 'Lyon');
        $buyer = $this->buyerOf($company);
        $cart = $this->cartOf($buyer, '100');
        $keptStreet = $cart->getCartAddressRelatedByAddressInvoiceId()?->getAddress1();

        $order = $this->placeFromTheCartAddresses($cart, $buyer, atTheKeyboard: false);

        self::assertSame($keptStreet, $this->invoiceOf($order)->getAddress1(), 'An installment of a subscription or an order of the back office is invoiced as it says.');
    }

    private function billingAddressOf(Company $company, string $city): Address
    {
        $administrator = $this->customer();
        $address = $this->addressOf($administrator, $company, 'Headquarters '.$city);
        $address->setAddress1('1 Corporate Way '.$city)->setCity($city)->setZipcode('69001')->save($this->getPropelConnection());
        CompanyQuery::create()->filterById($company->getId())->update(['BillingAddressId' => $address->getId()]);

        return $address;
    }

    private function placeFromTheCartAddresses(Cart $cart, Customer $customer, bool $atTheKeyboard = true): Order
    {
        return $this->getService(OrderFacade::class)->createOrder(
            $this->dispatcher,
            $this->sessionOrder(),
            $cart->getCurrency() ?? $this->fixtures->currency(),
            $this->lang(),
            $cart,
            $customer,
            useOrderDefinedAddresses: false,
            recordConsentAnswers: $atTheKeyboard,
        );
    }

    private function invoiceOf(Order $order): OrderAddress
    {
        return OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId()) ?? throw new \LogicException('The order has an invoice address.');
    }
}
