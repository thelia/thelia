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

namespace Thelia\Tests\Api\Admin;

use Thelia\Api\Service\OrderReturnHydrator;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderReturnLineTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnLineQuery;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * A return opened from the administration is made of new lines only: naming a
 * line of another return moves nothing.
 */
final class OrderReturnExistingLineAdminApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testALineOfAnotherReturnIsNotMovedIntoTheReturnBeingOpened(): void
    {
        $existingOrder = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $existingProduct = $this->orderProductFor($existingOrder, 3.0);
        $existingReturn = (new OrderReturn())
            ->setOrder($existingOrder)
            ->setCustomerId($existingOrder->getCustomerId())
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_REQUESTED));
        $existingReturn->save($this->getPropelConnection());
        $existingLine = (new OrderReturnLine())
            ->setOrderReturn($existingReturn)
            ->setOrderProduct($existingProduct)
            ->setProductSaleElementsId($existingProduct->getProductSaleElementsId())
            ->setQuantity(3.0)
            ->setQuantityReceived(0.0);
        $existingLine->save($this->getPropelConnection());
        OrderReturnLineTableMap::clearInstancePool();
        $before = OrderReturnLineQuery::create()->findPk($existingLine->getId(), $this->getPropelConnection())?->toArray();

        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $product = $this->orderProductFor($order, 2.0);

        $response = $this->jsonRequest('POST', '/api/admin/order_returns', [
            'order' => '/api/admin/orders/'.$order->getId(),
            'orderReturnLines' => [[
                '@id' => '/api/admin/order_return_lines/'.$existingLine->getId(),
                'orderProduct' => '/api/admin/order_products/'.$product->getId(),
                'quantity' => 1.0,
            ]],
        ], token: $this->authenticateAsAdmin());

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(OrderReturnHydrator::EXISTING_LINE_MESSAGE, json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['detail'] ?? null);

        OrderReturnLineTableMap::clearInstancePool();
        self::assertSame($before, OrderReturnLineQuery::create()->findPk($existingLine->getId(), $this->getPropelConnection())?->toArray());
        self::assertSame(0, OrderReturnQuery::create()->filterByOrderId((int) $order->getId())->count($this->getPropelConnection()));
    }

    private function orderProductFor(Order $order, float $quantity): OrderProduct
    {
        $orderProduct = (new OrderProduct())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef('PSE-'.uniqid())
            ->setProductSaleElementsId(1)
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }
}
