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

use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Test\ApiTestCase;

/**
 * An administrator reads the group and the rate frozen on an order, and cannot rewrite them.
 */
final class OrderCustomerGroupApiTest extends ApiTestCase
{
    public function testAdminReadsTheFrozenGroupAndRate(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('admin-pro', 12.5, false);
        $order = $factory->order();
        $order->setCustomerGroupId($group->getId())->setCustomerGroupDiscountRate('12.500000')->setCustomerGroupDisplayTaxedPrice(0)->save();

        $response = $this->jsonRequest('GET', '/api/admin/orders/'.$order->getId(), token: $this->authenticateAsAdmin());

        self::assertJsonResponseSuccessful($response);
        $data = json_decode($response->getContent(), true);
        self::assertSame($group->getId(), $data['customerGroupId']);
        self::assertSame('admin-pro', $data['customerGroupCode']);
        self::assertSame(12.5, $data['customerGroupDiscountRate']);
        self::assertFalse($data['customerGroupDisplayTaxedPrice']);
    }

    public function testAdminCannotWriteTheFrozenGroup(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('admin-frozen', 12.5);
        $other = $factory->customerGroup('admin-other', 50.0);
        $order = $factory->order();
        $line = $factory->orderProduct($order);
        (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('2')->save($this->getPropelConnection());
        $order->setCustomerGroupId($group->getId())->setCustomerGroupDiscountRate('12.500000')->save();

        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'deliveryRef' => 'TRACK-42',
            'orderProducts' => ['/api/admin/order_products/'.$line->getId()],
            'customerGroupId' => $other->getId(),
            'customerGroupDiscountRate' => 50.0,
            'customerGroupDisplayTaxedPrice' => false,
        ], token: $this->authenticateAsAdmin(), format: 'merge-patch+json');

        self::assertJsonResponseSuccessful($response);

        OrderTableMap::clearInstancePool();
        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertSame($group->getId(), $reloaded->getCustomerGroupId());
        self::assertSame(12.5, (float) $reloaded->getCustomerGroupDiscountRate());
        self::assertSame(1, $reloaded->getCustomerGroupDisplayTaxedPrice());
    }
}
