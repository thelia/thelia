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

namespace Thelia\Tests\Integration\Domain\Legal;

use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Order\Service\OrderAddressPersister;
use Thelia\Model\OrderAddressQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The legal identifiers of a business address follow the path `company` already takes:
 * copied from the address to the cart, then frozen on the order.
 *
 * Frozen is the point of the last step. An invoice states what the buyer declared when the
 * order was placed, so a later correction of the customer's own address must not reach back
 * into an order that has already been billed.
 */
final class AddressLegalIdentifiersPropagationTest extends ActionIntegrationTestCase
{
    private const SIRET = '30326504500003';
    private const VAT_NUMBER = 'FR40303265045';

    public function testTheCartAddressCarriesTheIdentifiersOfTheAddress(): void
    {
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $address = $this->factory->address($customer, $this->factory->country(), $customerTitle);

        $address
            ->setCompany('Acme SAS')
            ->setSiret(self::SIRET)
            ->setVatNumber(self::VAT_NUMBER)
            ->save($this->getPropelConnection());

        $cartAddress = $this->getService(CartAddressService::class)
            ->getOrCreateCartAddressFromAddress($address);

        self::assertSame('Acme SAS', $cartAddress->getCompany());
        self::assertSame(self::SIRET, $cartAddress->getSiret());
        self::assertSame(self::VAT_NUMBER, $cartAddress->getVatNumber());
    }

    public function testTheOrderFreezesTheIdentifiersAndLaterEditsDoNotReachIt(): void
    {
        $connection = $this->getPropelConnection();
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $country = $this->factory->country();
        $address = $this->factory->address($customer, $country, $customerTitle);

        $address
            ->setCompany('Acme SAS')
            ->setSiret(self::SIRET)
            ->setVatNumber(self::VAT_NUMBER)
            ->save($connection);

        $cartAddressService = $this->getService(CartAddressService::class);
        $deliveryCartAddress = $cartAddressService->getOrCreateCartAddressFromAddress($address);
        $invoiceCartAddress = $cartAddressService->getOrCreateCartAddressFromAddress($address);

        $cart = $this->factory->cart($customer);
        $cart
            ->setAddressDeliveryId($deliveryCartAddress->getId())
            ->setAddressInvoiceId($invoiceCartAddress->getId())
            ->save($connection);

        $order = $this->factory->order($customer);

        $this->getService(OrderAddressPersister::class)
            ->prepareOrderAddresses($order, $cart, false, $connection);
        $order->save($connection);

        $invoiceOrderAddress = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId());
        self::assertNotNull($invoiceOrderAddress);
        self::assertSame('Acme SAS', $invoiceOrderAddress->getCompany());
        self::assertSame(self::SIRET, $invoiceOrderAddress->getSiret());
        self::assertSame(self::VAT_NUMBER, $invoiceOrderAddress->getVatNumber());

        // The customer moves the business to another legal entity.
        $address
            ->setCompany('Other Corp')
            ->setSiret('73282932000009')
            ->setVatNumber('FR44732829320')
            ->save($connection);

        $reloaded = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId());
        self::assertNotNull($reloaded);
        self::assertSame('Acme SAS', $reloaded->getCompany(), 'The billed order keeps what it was placed with');
        self::assertSame(self::SIRET, $reloaded->getSiret());
        self::assertSame(self::VAT_NUMBER, $reloaded->getVatNumber());
    }

    public function testAnAddressWithoutACompanyNameFreezesNoIdentifier(): void
    {
        $connection = $this->getPropelConnection();
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $address = $this->factory->address($customer, $this->factory->country(), $customerTitle);

        $cartAddress = $this->getService(CartAddressService::class)
            ->getOrCreateCartAddressFromAddress($address);

        self::assertNull($cartAddress->getSiret());
        self::assertNull($cartAddress->getVatNumber());
    }
}
