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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Thelia\Api\Resource\Order as OrderResource;
use Thelia\Domain\Order\Service\OrderFactory;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\Order;
use Thelia\Test\IntegrationTestCase;

/**
 * The order carries the sale unit it was placed on, stamped by the server from
 * the resolved context — never taken from the client — and NULL for a national,
 * single-shop order.
 */
final class SaleUnitOrderTaggingTest extends IntegrationTestCase
{
    private OrderFactory $orderFactory;

    private SaleUnitContext $saleUnitContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderFactory = $this->getService(OrderFactory::class);
        $this->saleUnitContext = $this->getService(SaleUnitContext::class);
    }

    protected function tearDown(): void
    {
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();

        parent::tearDown();
    }

    public function testAnOrderIsStampedWithTheCurrentSaleUnit(): void
    {
        $fixtures = $this->createFixtureFactory();
        $saleUnit = $fixtures->saleUnit();
        $sessionOrder = $fixtures->order();
        $customer = $sessionOrder->getCustomer();

        $this->saleUnitContext->setSaleUnit($saleUnit);

        $order = $this->orderFactory->createFromSessionOrder(
            $sessionOrder,
            $fixtures->currency(),
            $fixtures->lang(),
            \Thelia\Model\CartQuery::create()->findPk($sessionOrder->getCartId(), $this->getPropelConnection()),
            $customer,
        );

        self::assertSame($saleUnit->getId(), $order->getSaleUnitId());
    }

    public function testAnOrderPlacedOutsideAnySaleUnitIsNational(): void
    {
        $fixtures = $this->createFixtureFactory();
        $sessionOrder = $fixtures->order();

        $this->saleUnitContext->setSaleUnit(null);

        $order = $this->orderFactory->createFromSessionOrder(
            $sessionOrder,
            $fixtures->currency(),
            $fixtures->lang(),
            \Thelia\Model\CartQuery::create()->findPk($sessionOrder->getCartId(), $this->getPropelConnection()),
            $sessionOrder->getCustomer(),
        );

        self::assertNull($order->getSaleUnitId(), 'no sale unit resolved: a national order, exactly as before the feature');
    }

    /**
     * The order handed to the checkout is built from what the visitor sent: whatever
     * sale unit it carries is never the one the order is stamped with.
     */
    public function testASaleUnitTheIncomingOrderCarriesIsNeverKept(): void
    {
        $fixtures = $this->createFixtureFactory();
        $current = $fixtures->saleUnit();
        $other = $fixtures->saleUnit();
        $sessionOrder = $fixtures->order();
        $sessionOrder->setSaleUnitId($other->getId());
        $cart = \Thelia\Model\CartQuery::create()->findPk($sessionOrder->getCartId(), $this->getPropelConnection());

        $this->saleUnitContext->setSaleUnit($current);
        $order = $this->orderFactory->createFromSessionOrder($sessionOrder, $fixtures->currency(), $fixtures->lang(), $cart, $sessionOrder->getCustomer());
        self::assertSame($current->getId(), $order->getSaleUnitId(), 'The order belongs to the unit the server resolved.');

        $this->saleUnitContext->setSaleUnit(null);
        $order = $this->orderFactory->createFromSessionOrder($sessionOrder, $fixtures->currency(), $fixtures->lang(), $cart, $sessionOrder->getCustomer());
        self::assertNull($order->getSaleUnitId(), 'No unit resolved: a national order, whatever the incoming order said.');
    }

    /**
     * Nothing a caller of the API sends can set the stamp: the property belongs to no
     * group the order is written with, so API Platform never denormalizes it.
     */
    public function testTheApiNeverWritesTheSaleUnitOfAnOrder(): void
    {
        $propertyMetadataFactory = static::getContainer()->get('api_platform.metadata.property.metadata_factory');

        $written = $propertyMetadataFactory->create(OrderResource::class, 'saleUnitId', ['serializer_groups' => [OrderResource::GROUP_ADMIN_WRITE]]);
        $read = $propertyMetadataFactory->create(OrderResource::class, 'saleUnitId', ['serializer_groups' => [OrderResource::GROUP_ADMIN_READ]]);

        self::assertTrue($read->isReadable(), 'The stamp is read back by the API.');
        self::assertFalse($written->isWritable(), 'The stamp is never taken from an API payload.');
    }

    public function testTheStampSurvivesTheSave(): void
    {
        $fixtures = $this->createFixtureFactory();
        $saleUnit = $fixtures->saleUnit();
        $sessionOrder = $fixtures->order();

        $this->saleUnitContext->setSaleUnit($saleUnit);

        $order = $this->orderFactory->createFromSessionOrder(
            $sessionOrder,
            $fixtures->currency(),
            $fixtures->lang(),
            \Thelia\Model\CartQuery::create()->findPk($sessionOrder->getCartId(), $this->getPropelConnection()),
            $sessionOrder->getCustomer(),
        );
        $order->setRef('SU-TAG-'.$saleUnit->getId())->save($this->getPropelConnection());

        $reread = (new \Thelia\Model\OrderQuery())->findPk($order->getId(), $this->getPropelConnection());
        self::assertInstanceOf(Order::class, $reread);
        self::assertSame($saleUnit->getId(), $reread->getSaleUnitId());
    }
}
