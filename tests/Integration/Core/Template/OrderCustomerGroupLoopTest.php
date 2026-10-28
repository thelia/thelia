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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Test\IntegrationTestCase;

/**
 * The group and the discount rate frozen on an order are what a template reads from
 * the order loop, whatever the customer belongs to today.
 */
final class OrderCustomerGroupLoopTest extends IntegrationTestCase
{
    public function testLoopExposesTheFrozenGroupAndRate(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('loop-pro', 15.0, false);
        $customer = $factory->customer($factory->customerTitle());
        $factory->assignCustomerGroup($customer, $group);
        $order = $factory->order($customer);
        $order->setCustomerGroupId($group->getId())->setCustomerGroupDiscountRate('15.000000')->setCustomerGroupDisplayTaxedPrice(0)->save();
        $withoutGroup = $factory->order($customer);
        $withoutGroup->setCustomerGroupDiscountRate('7.500000')->save();

        $rows = $this->rows(['id' => $order->getId().','.$withoutGroup->getId()]);

        self::assertCount(2, $rows);
        $byId = array_column($rows, null, 'ID');
        self::assertEquals($group->getId(), $byId[$order->getId()]['CUSTOMER_GROUP_ID']);
        self::assertSame('loop-pro', $byId[$order->getId()]['CUSTOMER_GROUP_CODE']);
        self::assertSame(15.0, $byId[$order->getId()]['CUSTOMER_GROUP_DISCOUNT_RATE']);
        self::assertFalse($byId[$order->getId()]['CUSTOMER_GROUP_DISPLAY_TAXED_PRICE']);
        self::assertTrue($byId[$withoutGroup->getId()]['CUSTOMER_GROUP_DISPLAY_TAXED_PRICE']);
        self::assertEmpty($byId[$withoutGroup->getId()]['CUSTOMER_GROUP_ID']);
        self::assertEmpty($byId[$withoutGroup->getId()]['CUSTOMER_GROUP_CODE']);
        self::assertSame(7.5, $byId[$withoutGroup->getId()]['CUSTOMER_GROUP_DISCOUNT_RATE']);
    }

    /**
     * Frozen on the order: the group id, the rate and the taxed or untaxed display. The
     * code is not: it is read on the group as it is now, as the documentation says.
     */
    public function testTheCodeFollowsTheGroupWhileTheRateAndTheIdStayFrozen(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('before-rename', 15.0);
        $order = $factory->order($factory->customer($factory->customerTitle()));
        $order->setCustomerGroupId($group->getId())->setCustomerGroupDiscountRate('15.000000')->save();

        $group->setCode('after-rename')->setDiscount('40.000000')->save();

        $row = $this->rows(['id' => $order->getId()])[0];
        self::assertEquals($group->getId(), $row['CUSTOMER_GROUP_ID']);
        self::assertSame('after-rename', $row['CUSTOMER_GROUP_CODE']);
        self::assertSame(15.0, $row['CUSTOMER_GROUP_DISCOUNT_RATE']);
        self::assertSame('after-rename', $order->getCustomerGroupCode());
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $arguments): array
    {
        $rows = [];
        foreach ($this->getService(LoopExecutor::class)->execute('order', $arguments + ['customer' => '*', 'backend_context' => true]) as $row) {
            $rows[] = $row->getVarVal();
        }

        return $rows;
    }
}
