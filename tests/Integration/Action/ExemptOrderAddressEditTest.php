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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CountryQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ExemptOrderAddressEditTest extends ActionIntegrationTestCase
{
    private const NUMBER = 'BE0123456789';

    private const VERIFIED_AT = '2026-09-20 10:00:00';

    public function testCorrectingTheZipcodeOfAnExemptOrderKeepsItsProof(): void
    {
        [$order, $invoiceAddress] = $this->exemptOrder();

        $this->editInvoiceAddress($order, $invoiceAddress, company: 'Acme', vatNumber: self::NUMBER, zipcode: '1050');

        $reloaded = $this->reloaded($invoiceAddress);
        self::assertSame('1050', $reloaded->getZipcode());
        self::assertSame(1, $reloaded->getVatExempted());
        self::assertSame(self::NUMBER, $reloaded->getVatNumber());
        self::assertSame(self::VERIFIED_AT, $reloaded->getVatVerifiedAt('Y-m-d H:i:s'));
        self::assertEqualsWithDelta(22.0, (float) $reloaded->getVatExemptedAmount(), 0.0001);
    }

    public function testEmptyingTheCompanyOfAnExemptOrderKeepsTheNumberAndTheVerificationItWasExemptedOn(): void
    {
        [$order, $invoiceAddress] = $this->exemptOrder();

        $this->editInvoiceAddress($order, $invoiceAddress, company: '', vatNumber: self::NUMBER, zipcode: '1000');

        $reloaded = $this->reloaded($invoiceAddress);
        self::assertSame(1, $reloaded->getVatExempted(), 'Control: the order stays exempt (C9).');
        self::assertSame(self::NUMBER, $reloaded->getVatNumber(), 'An exempt order must keep the number its exemption rests on.');
        self::assertSame(self::VERIFIED_AT, $reloaded->getVatVerifiedAt('Y-m-d H:i:s'), 'An exempt order must keep the date of the verification it rests on.');
    }

    public function testReplacingTheNumberOfAnExemptOrderDoesNotLeaveAnUnverifiedNumberOnAnExemptInvoice(): void
    {
        [$order, $invoiceAddress] = $this->exemptOrder();

        $this->editInvoiceAddress($order, $invoiceAddress, company: 'Acme', vatNumber: 'BE0999999999', zipcode: '1000');

        $reloaded = $this->reloaded($invoiceAddress);
        self::assertSame(1, $reloaded->getVatExempted(), 'Control: the order stays exempt (C9).');
        self::assertFalse(
            self::NUMBER !== $reloaded->getVatNumber() && null === $reloaded->getVatVerifiedAt(),
            \sprintf('An exempt order now carries %s, never verified, in place of %s.', (string) $reloaded->getVatNumber(), self::NUMBER),
        );
    }

    /**
     * @return array{0: Order, 1: OrderAddress}
     */
    private function exemptOrder(): array
    {
        $order = $this->factory->order();
        $invoiceAddress = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId());
        self::assertNotNull($invoiceAddress);
        $belgium = CountryQuery::create()->findOneByIsoalpha2('BE');
        self::assertNotNull($belgium);

        $this->getPropelConnection()
            ->prepare('UPDATE `order_address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = ?, `vat_verified_name` = ?, `vat_exempted` = 1, `vat_exempted_amount` = ?, `country_id` = ?, `zipcode` = ?, `city` = ? WHERE `id` = ?')
            ->execute(['Acme', self::NUMBER, self::VERIFIED_AT, 'Acme SPRL', '22.000000', $belgium->getId(), '1000', 'Bruxelles', $invoiceAddress->getId()]);

        return [$order, $this->reloaded($invoiceAddress)];
    }

    private function editInvoiceAddress(Order $order, OrderAddress $invoiceAddress, string $company, string $vatNumber, string $zipcode): void
    {
        $event = new OrderAddressEvent(
            title: $invoiceAddress->getCustomerTitleId(),
            firstname: (string) $invoiceAddress->getFirstname(),
            lastname: (string) $invoiceAddress->getLastname(),
            address1: (string) $invoiceAddress->getAddress1(),
            address2: (string) $invoiceAddress->getAddress2(),
            address3: (string) $invoiceAddress->getAddress3(),
            zipcode: $zipcode,
            city: (string) $invoiceAddress->getCity(),
            country: $invoiceAddress->getCountryId(),
            phone: (string) $invoiceAddress->getPhone(),
            company: $company,
            cellphone: $invoiceAddress->getCellphone(),
            state: $invoiceAddress->getStateId(),
            siret: null,
            vatNumber: $vatNumber,
        );
        $event->setOrderAddress($invoiceAddress);
        $event->setOrder($order);

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_ADDRESS);
    }

    public function testTheDeliveryAddressOfAnExemptOrderStaysEditable(): void
    {
        [$order] = $this->exemptOrder();
        $deliveryAddress = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());
        self::assertNotNull($deliveryAddress);

        $this->editInvoiceAddress($order, $deliveryAddress, company: 'Other', vatNumber: 'BE0999999999', zipcode: '1050');

        $reloaded = $this->reloaded($deliveryAddress);
        self::assertSame('Other', $reloaded->getCompany());
        self::assertSame('BE0999999999', $reloaded->getVatNumber());
    }

    private function reloaded(OrderAddress $orderAddress): OrderAddress
    {
        $reloaded = OrderAddressQuery::create()->findPk($orderAddress->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
