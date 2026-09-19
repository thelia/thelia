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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Domain\Order\Service\OrderAddressPersister;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\State;
use Thelia\Test\IntegrationTestCase;

/**
 * The invoice address frozen on the order must carry the state of the INVOICE address,
 * not the one of the delivery address.
 */
final class OrderAddressPersisterStateTest extends IntegrationTestCase
{
    public function testTheInvoiceOrderAddressKeepsItsOwnState(): void
    {
        $factory = $this->createFixtureFactory();
        $connection = $this->getPropelConnection();
        $country = $factory->country();

        $deliveryState = (new State())->setCountryId($country->getId())->setVisible(1)->setIsocode('PD');
        $deliveryState->save($connection);

        $invoiceState = (new State())->setCountryId($country->getId())->setVisible(1)->setIsocode('PI');
        $invoiceState->save($connection);

        $customer = $factory->customer($factory->customerTitle());
        $cart = $factory->cart($customer);

        $deliveryCartAddress = $factory->cartAddress(null, $country);
        $deliveryCartAddress->setStateId($deliveryState->getId())->save($connection);

        $invoiceCartAddress = $factory->cartAddress(null, $country);
        $invoiceCartAddress->setStateId($invoiceState->getId())->save($connection);

        $cart
            ->setAddressDeliveryId($deliveryCartAddress->getId())
            ->setAddressInvoiceId($invoiceCartAddress->getId())
            ->save($connection);

        $order = new Order();

        $this->getService(OrderAddressPersister::class)->prepareOrderAddresses($order, $cart, false, $connection);

        $invoiceOrderAddress = OrderAddressQuery::create()->findPk($order->getInvoiceOrderAddressId(), $connection);
        $deliveryOrderAddress = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId(), $connection);

        self::assertNotNull($invoiceOrderAddress);
        self::assertNotNull($deliveryOrderAddress);
        self::assertSame($deliveryState->getId(), $deliveryOrderAddress->getStateId());
        self::assertSame($invoiceState->getId(), $invoiceOrderAddress->getStateId());
    }
}
